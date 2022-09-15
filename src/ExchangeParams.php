<?php

namespace Amqp;


use Amqp\Exception\InvalidParam;

class ExchangeParams
{
    // 允许的交换机类型
    const ALLOW_TYPE = [
        'direct',
        'fanout',
        'topic'
    ];

    /**
     * 创建交换机的默认参数
     * passive: 消极处理， 判断是否存在队列，存在则返回，不存在直接抛出 PhpAmqpLib\Exception\AMQPProtocolChannelException 异常
     * durable：true、false true：服务器重启会保留下来Exchange。警告：仅设置此选项，不代表消息持久化。即不保证重启后消息还在
     * autoDelete:true、false.true:当已经没有消费者时，服务器是否可以删除该Exchange
     */
    const DEFAULT_PARAMS = [false, true, false];

    /**
     * @var string 交换机名
     */
    private $name;

    /**
     * @var string 交换机类型
     */
    private $type;

    /**
     * 交换机参数
     * @var array
     */
    private $exchangeParams = self::DEFAULT_PARAMS;

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName(string $name): ExchangeParams
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType(string $type): ExchangeParams
    {
        if (!in_array($type, self::ALLOW_TYPE)) {
            throw new InvalidParam('invlid exchange type');
        }
        $this->type = $type;
        return $this;
    }

    /**
     * @return array
     */
    public function getExchangeParams(): array
    {
        return $this->exchangeParams;
    }

    /**
     * @param array $exchangeParams
     */
    public function setExchangeParams(array $exchangeParams): ExchangeParams
    {
        if (empty($exchangeParams)) {
            throw new InvalidParam('exchangeParams不能为空');
        }
        if (count($exchangeParams) != 3) {
            throw new InvalidParam('错误的exchangeParams参数数量');
        }
        foreach ($exchangeParams as $v) {
            if (!is_bool($v)) {
                throw new InvalidParam('错误的exchangeParams参数类型');
            }
        }

        $this->exchangeParams = $exchangeParams;
        return $this;
    }

    public function clearParams()
    {
        $this->exchangeParams = self::DEFAULT_PARAMS;
    }
}