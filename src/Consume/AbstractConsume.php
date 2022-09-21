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

    abstract public static function exec(AMQPMessage $msg);

    abstract public static function maxRetryCallback(AMQPMessage $msg);
}