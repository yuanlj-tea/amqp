<?php

namespace Amqp;

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

    public function consume(\Closure $closure, bool $autoAck = false, $prefetchCount = 20)
    {
        $this->autoAck = $autoAck;
        $this->queueBind();

        try {
            $this->consumerTag = $this->getConsumerTag();
            $this->channel->basic_qos(0, $prefetchCount, false);
        } catch (\Throwable $t) {

        }
    }
}