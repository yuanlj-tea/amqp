<?php
require_once __DIR__ . '/../../vendor/autoload.php';

class Index
{
    /**
     * @var \Amqp\IAmqp
     */
    private $amqp;

    public function __construct($config, $key)
    {
        $this->amqp = \Amqp\AmqpFacade::getAmqp($config, $key);
    }

    public function produce($payload)
    {
        $this->amqp->produce($payload);
    }

    public function consume()
    {
        $callback = function (\PhpAmqpLib\Message\AMQPMessage $message) {
            var_dump($message->body);
        };
        $this->amqp->consume($callback);
    }

    public function lag()
    {
        return $this->amqp->lag();
    }
}

$config = require __DIR__ . '/../../publish/amqp.php';
$o = new Index($config, 'queue_key_demo');
$o->produce('test1');
// $o->consume();
// var_dump($o->lag());


