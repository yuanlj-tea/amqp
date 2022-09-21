<?php

namespace Amqp;

use Amqp\Exception\InvalidParam;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Message\AMQPMessage;

class Amqp extends AbstractAmqp implements IAmqp
{
    /**
     * 生产消息
     * @param string $payload
     * @param int $deliveryMode
     */
    public function produce(string $payload, $deliveryMode = AMQPMessage::DELIVERY_MODE_NON_PERSISTENT)
    {
        $msg = $this->buildMsg($payload, $deliveryMode);
        $this->channel->basic_publish($msg, $this->exchangeParams->getName(), $this->queueParams->getRouteKey());
    }

    /**
     * 批量生产
     * @param array $batchPayload
     * @param int $deliveryMode
     */
    public function batchProduce(array $batchPayload, $deliveryMode = AMQPMessage::DELIVERY_MODE_NON_PERSISTENT)
    {
        foreach ($batchPayload as $v) {
            $payload = is_string($v) ? $v : json_encode($v);
            $msg = $this->buildMsg($payload, $deliveryMode);
            $this->channel->batch_basic_publish($msg, $this->exchangeParams->getName(), $this->queueParams->getRouteKey());
        }
        $this->channel->publish_batch();
    }

    /**
     * 确认发布
     * @param string $payload
     * @param int $deliveryMode
     * @return bool
     */
    public function confirmProduce(string $payload, $deliveryMode = AMQPMessage::DELIVERY_MODE_NON_PERSISTENT)
    {
        // 消息是否最终成功
        $finalSucc = false;
        // 是否成功到达交换机
        $isAck = false;
        // mq是否发生内部错误
        $isNack = false;
        // 消息是否被退回
        $isReturn = false;

        // 开启发送确认机制
        $this->channel->confirm_select();

        // 成功到达交换机时执行
        $this->channel->set_ack_handler(function (AMQPMessage $message) use (&$isAck) {
            $isAck = true;
        });

        // nack,rabbitmq内部错误时触发
        $this->channel->set_nack_handler(function (AMQPMessage $message) use (&$isNack) {
            $isNack = true;
        });

        // 消息到达交换机，但没有进入合适的队列，消息回退时出发
        $this->channel->set_return_listener(function (AMQPMessage $message) use (&$isReturn) {
            $isReturn = true;
        });

        $msg = $this->buildMsg($payload, $deliveryMode);
        // 生产者发布消息时设置mandatory=true，表示消息无法路由到队列时，会退回给生产者
        $this->channel->basic_publish($msg, $this->exchangeParams->getName(), $this->queueParams->getName(), true);

        // 阻塞等待server确认
        $this->channel->wait_for_pending_acks_returns();

        if ($isAck && !$isNack && !$isReturn) {
            $finalSucc = true;
        }

        return $finalSucc;
    }

    /**
     * 消费数据
     * @param \Closure $closure
     * @param bool $autoAck
     * @param int $prefetchCount
     * @throws \Throwable
     */
    public function consume(callable $function, bool $autoAck = false, $prefetchCount = 20)
    {
        $this->autoAck = $autoAck;
        $this->queueBind();

        try {
            $this->consumerTag = $this->getConsumerTag();
            $this->channel->basic_qos(0, $prefetchCount, false);
            /**
             * queue 要取得消息的队列名
             * consumer_tag 消费者标签
             * no_local false这个功能属于AMQP的标准,但是rabbitMQ并没有做实现
             * no_ack  false收到消息后,是否不需要回复确认即被认为被消费
             * exclusive false排他消费者,即这个队列只能由一个消费者消费.适用于任务不允许进行并发处理的情况下
             * nowait  false不返回执行结果,但是如果排他开启的话,则必须需要等待结果的,如果两个一起开就会报错
             * callback  null回调函数
             */
            $this->channel->basic_consume(
                $this->queueParams->getName(),
                $this->consumerTag,
                false,
                $this->autoAck,
                false,
                false,
                function (AMQPMessage $msg) use ($function) {
                    try {
                        call_user_func($function, $msg);
                        if (!$this->autoAck) {
                            $msg->ack();
                        }
                    } catch (\Throwable $t) {
                        if (!$this->autoAck) {
                            // 数据库连接异常直接抛出异常由supervisor重新拉起常驻进程；其它异常重新入队列做重试，达到最大重试次数后入死信队列(如有配置)或丢弃消息
                            $body = $msg->getBody();
                            $md5 = md5($body);
                            if (isset($this->retryMap[$md5])) {
                                $this->retryMap[$md5]++;
                            } else {
                                $this->retryMap[$md5] = 1;
                            }
                            if ($this->retryMap[$md5] > self::MAX_TRY_TIMES) {
                                $msg->nack(false);
                                unset($this->retryMap[$md5]);
                                if (is_callable($this->maxRetryCallback)) {
                                    call_user_func($this->maxRetryCallback, $msg);
                                }
                            } else {
                                $msg->nack(true);
                            }
                        }
                        $this->exceptionHandle($t);
                    }
                }
            );

            if (extension_loaded('pcntl')) {
                pcntl_async_signals(true);
                pcntl_signal(SIGINT, [$this, 'shutdown']);
                pcntl_signal(SIGTERM, [$this, 'shutdown']);
            }

            while (count($this->channel->callbacks) && $this->run) {
                $this->channel->wait();
            }
        } catch (AMQPConnectionClosedException $e) {
            $this->connection->close();
            $this->connection->reconnect();
        } catch (\Throwable $t) {
            throw $t;
        }
    }

    /**
     * 批量消费
     * @param int $batchSize
     * @param \Closure|null $closure
     * @throws InvalidParam
     * @throws \Throwable
     */
    public function batchConsume(int $batchSize = 50, \Closure $closure = null)
    {
        if ($batchSize <= 0 || $batchSize > 500) {
            throw new InvalidParam('invalid batch size');
        }
        try {
            if (extension_loaded('pcntl')) {
                pcntl_async_signals(true);
                pcntl_signal(SIGINT, [$this, 'shutdown']);
                pcntl_signal(SIGTERM, [$this, 'shutdown']);
            }

            while (true && $this->run) {
                $data = [];
                $tag = null;

                while (count($data) < $batchSize) {
                    $msg = $this->channel->basic_get($this->queueParams->getName());
                    if (is_null($msg) || !$msg instanceof AMQPMessage) {
                        break;
                    }

                    $tag = $msg->getDeliveryTag();
                    $payload = $msg->getBody();
                    array_push($data, $payload);
                }

                if (count($data) == 0) {
                    sleep(1);
                } else {
                    if (is_callable($closure)) {
                        call_user_func($closure, $data);
                    }
                    if (!is_null($tag)) {
                        $this->channel->basic_ack($tag, true);
                    }
                }
            }

        } catch (AMQPConnectionClosedException $e) {
            $this->connection->close();
            $this->connection->reconnect();
        } catch (\Throwable $t) {
            throw $t;
        }
    }

    /**
     * 获取队列堆积数量
     * @return mixed
     */
    public function lag()
    {
        list($queue, $lag) = $this->channel->queue_declare($this->queueParams->getName(), true);
        return $lag;
    }

    public function shutdown()
    {
        echo "gracefully stopping worker..\n";

        if (function_exists('pcntl_signal') === false) {
            $this->run = false;
        }
        $curChannel = $this->channel;
        if ($curChannel->is_open() == false) {
            return;
        }

        try {
            if (isset($this->channel->callbacks[$this->consumerTag]) && $this->channel->is_open() === true) {
                $this->channel->basic_cancel($this->consumerTag);
            }
        } catch (AMQPConnectionClosedException $e) {
            $this->run = false;
        }
        $this->run = false;
    }

    /**
     * 处理断线重连异常
     * @param \Throwable $t
     * @throws \Throwable
     */
    public function exceptionHandle(\Throwable $t)
    {
        if ($this->checkConnectError($t)) {
            throw $t;
        }
    }

    public function checkConnectError(\Throwable $e)
    {
        if (strpos($e->getMessage(), 'Error while sending QUERY packet') !== false || strpos($e->getMessage(), 'MySQL server has gone away') !== false) {
            return true;
        }
        return false;
    }
}