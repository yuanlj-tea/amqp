<?php

namespace Amqp\Consume;

use Amqp\AmqpFacade;
use PhpAmqpLib\Message\AMQPMessage;

abstract class AbstractConsume
{
    public static function consume(array $config, string $key)
    {
        $amqp = AmqpFacade::getAmqp($config, $key);
        $amqp->setMaxRetryCallback([static::class, 'maxRetryCallback'])
            ->consume([static::class, 'exec']);
    }

    /**
     * 消费数据后执行逻辑
     * @param AMQPMessage $msg
     * @return mixed
     */
    abstract public static function exec(AMQPMessage $msg);

    /**
     * 重试次数达到最大后，执行逻辑
     * @param AMQPMessage $msg
     * @return mixed
     */
    abstract public static function maxRetryCallback(AMQPMessage $msg);
}