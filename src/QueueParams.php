<?php

namespace Amqp;

use Amqp\Exception\InvalidParam;

class QueueParams
{
    /**
     * 创建queue的默认参数
     * passive: 消极处理， 判断是否存在队列，存在则返回，不存在直接抛出 PhpAmqpLib\Exception\AMQPProtocolChannelException 异常
     * durable：true、false true：在服务器重启时，能够存活
     * exclusive ：是否为当前连接的专用队列，在连接断开后，会自动删除该队列
     * autodelete：当没有任何消费者使用时，自动删除该队列
     * arguments: 自定义规则
     * see：https://blog.csdn.net/wwwwxyxy/article/details/84654787
     */
    const DEFAULT_PARAMS = [false, true, false, false, true];

    /**
     * @var string 队列名
     */
    private $name;

    /**
     * @var string
     */
    private $routeKey;

    /**
     * @var array 队列参数
     */
    private $queueParams = self::DEFAULT_PARAMS;

    /**
     * @return array
     */
    public function getQueueParams(): array
    {
        return $this->queueParams;
    }

    /**
     * @param array $queueParams
     */
    public function setQueueParams(array $queueParams): QueueParams
    {
        if (empty($queueParams)) {
            throw new InvalidParam('queueParams不能为空');
        }
        if (count($queueParams) != 5) {
            throw new InvalidParam('错误的queueParams参数数量');
        }
        foreach ($queueParams as $v) {
            if (!is_bool($v)) {
                throw new InvalidParam('错误的queueParams参数类型');
            }
        }
        $this->queueParams = $queueParams;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): QueueParams
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getRouteKey(): string
    {
        return $this->routeKey;
    }

    /**
     * @param string $routeKey
     */
    public function setRouteKey(string $routeKey): void
    {
        $this->routeKey = $routeKey;
    }

    public function clearParams()
    {
        $this->queueParams = self::DEFAULT_PARAMS;
    }
}