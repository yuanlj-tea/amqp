<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Message\AMQPMessage;

class TestConsume extends \Amqp\Consume\AbstractConsume
{
    public static function exec(AMQPMessage $msg)
    {
        var_dump($msg->getBody(), '消费消息');
        throw new \Exception('test');
    }

    public static function maxRetryCallback(AMQPMessage $msg)
    {
        $body = $msg->body;
        var_dump($body, '达到最大重试次数');
    }

    public static function t()
    {
        $config = require __DIR__ . '/../../publish/amqp.php';
        self::consume($config, 'queue_key_demo');
    }
}

TestConsume::t();