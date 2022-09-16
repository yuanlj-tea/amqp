<?php

namespace Amqp;

use PhpAmqpLib\Message\AMQPMessage;

interface IAmqp
{
    /**
     * 发布消息
     * @param string $payload
     * @param int $deliveryMode
     * @return mixed
     */
    public function produce(string $payload, $deliveryMode = AMQPMessage::DELIVERY_MODE_NON_PERSISTENT);

    /**
     * 批量发布消息
     * @param array $batchPayload
     * @param int $deliveryMode
     * @return mixed
     */
    public function batchProduce(array $batchPayload, $deliveryMode = AMQPMessage::DELIVERY_MODE_NON_PERSISTENT);

    /**
     * 确认发布
     * @param string $payload
     * @param int $deliveryMode
     * @return mixed
     */
    public function confirmProduce(string $payload, $deliveryMode = AMQPMessage::DELIVERY_MODE_NON_PERSISTENT);

    /**
     * 消费消息
     * @param \Closure $closure
     * @param bool $autoAck
     * @param int $prefetchCount
     * @return mixed
     */
    public function consume(\Closure $closure, bool $autoAck = false, $prefetchCount = 20);

    /**
     * 批量消费消息
     * @param int $batchSize
     * @param \Closure|null $closure
     * @return mixed
     */
    public function batchConsume(int $batchSize = 50, \Closure $closure = null);

    /**
     * 获取队列堆积数量
     * @return mixed
     */
    public function lag();
}