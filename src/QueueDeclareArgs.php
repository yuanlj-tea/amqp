<?php

namespace Amqp;

class QueueDeclareArgs
{
    const DLX_EXCHANGE = 'x-dead-letter-exchange';

    const DLX_QUEUE = 'x-dead-letter-queue';

    const DLX_ROUTING_KEY = 'x-dead-letter-routing-key';

    const DLX_MSG_TTL = 'x-message-ttl';

    private $queueDeclareArg = [];

    /**
     * @return array
     */
    public function getQueueDeclareArg(): array
    {
        return $this->queueDeclareArg;
    }

    /**
     * @param array $queueDeclareArg
     */
    public function setQueueDeclareArg(array $queueDeclareArg): QueueDeclareArgs
    {
        if (!empty($queueDeclareArg)) {
            $this->queueDeclareArg = array_merge($this->queueDeclareArg, $queueDeclareArg);
        }
        return $this;
    }

    /**
     * 设置死信队列的参数
     * @param $dlxExchange string 死信交换机名
     * @param $dlxQueue string 死信队列名
     * @param $dlxRoutingKey
     * @param int $messageTtl 单位：毫秒
     * @return $this
     */
    public function setDeadLetterArg($dlxExchange, $dlxQueue, $dlxRoutingKey, $msgTtl = 0): QueueDeclareArgs
    {
        $arg = [];
        if (!empty($dlxExchange)) {
            $arg[self::DLX_EXCHANGE] = $dlxExchange;
        }
        if (!empty($dlxQueue)) {
            $arg[self::DLX_QUEUE] = $dlxQueue;
        }
        if (!empty($dlxRoutingKey)) {
            $arg[self::DLX_ROUTING_KEY] = $dlxRoutingKey;
        }
        if (!empty($msgTtl)) {
            $arg[self::DLX_MSG_TTL] = $msgTtl;
        }
        if (!empty($arg)) {
            $this->queueDeclareArg = array_merge($this->queueDeclareArg, $arg);
        }

        return $this;
    }

    public function clearParam()
    {
        $this->queueDeclareArg = [];
    }
}