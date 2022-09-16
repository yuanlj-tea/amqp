<?php

namespace Amqp;

use Amqp\Exception\InvalidParam;
use PhpAmqpLib\Exchange\AMQPExchangeType;

class AmqpFacade
{
    private $config;

    private $params;

    /**
     * @var AbstractAmqp
     */
    private $amqp;

    /**
     * @var ExchangeParams
     */
    private $exchangeParams;

    /**
     * @var QueueParams
     */
    private $queueParams;

    /**
     * @var QueueDeclareArgs
     */
    private $queueDeclareArgs;

    public function __construct(array $config, string $key)
    {
        $this->config = $config;
        $this->checkParams($key);

        $this->amqp = new Amqp($config);
        $this->exchangeParams = new ExchangeParams();
        $this->queueParams = new QueueParams();
        $this->queueDeclareArgs = new QueueDeclareArgs();
    }

    private function checkParams($key)
    {
        if (!isset($this->config[$key]) || !is_array($this->config[$key])) {
            throw new InvalidParam('invalid config and key');
        }
        $this->params = $params = $this->config[$key];
        if (
            !isset($params['exchange_name']) ||
            !isset($params['queue_name']) ||
            !isset($params['route_key']) ||
            !isset($params['exchange_type'])
        ) {
            throw new InvalidParam('缺少参数');
        }
        return $this;
    }

    private function setExchange()
    {
        $exchangeName = $this->params['exchange_name'];
        $exchangeType = $this->params['exchange_type'];
        $this->exchangeParams->setName($exchangeName);
        $this->exchangeParams->setType($exchangeType);
        if (isset($this->params['exchange_param'])) {
            $this->exchangeParams->setExchangeParams($this->params['exchange_param']);
        }
        $this->amqp->setExchangeParams($this->exchangeParams);

        return $this;
    }

    private function setQueue()
    {
        $queueName = $this->params['queue_name'];
        $routeKey = $this->params['route_key'];
        $this->queueParams->setName($queueName);
        $this->queueParams->setRouteKey($routeKey);
        if (isset($this->params['queue_param'])) {
            $this->queueParams->setQueueParams($this->params['queue_param']);
        }
        $this->amqp->setQueueParams($this->queueParams);

        return $this;
    }

    private function setQueueDeclareOptions()
    {
        if (isset($this->params['option'])) {
            $this->queueDeclareArgs->setQueueDeclareArg($this->params['option']);
        }

        return $this;
    }

    private function setDlxQueue()
    {
        if (!isset($this->params['dlx_params'])) {
            return;
        }
        $dlx = $this->params['dlx_params'];
        if (
            !isset($dlx['dlx_exchange_name']) ||
            !isset($dlx['dlx_queue_name']) ||
            !isset($dlx['dlx_routing_key']) ||
            !isset($dlx['dlx_msg_ttl'])
        ) {
            throw new InvalidParam('死信队列缺少参数');
        }
        $this->amqp->getChannel()->exchange_declare($dlx['dlx_exchange_name'], AMQPExchangeType::DIRECT);
        $this->amqp->getChannel()->queue_declare($dlx['dlx_queue_name']);
        $this->amqp->getChannel()->queue_bind($dlx['dlx_queue_name'], $dlx['dlx_exchange_name'], $dlx['dlx_routing_key']);

        $this->queueDeclareArgs->setDeadLetterArg($dlx['dlx_exchange_name'], $dlx['dlx_queue_name'], $dlx['dlx_routing_key'], $dlx['dlx_msg_ttl']);
    }

    public static function getAmqp(array $config, string $key): IAmqp
    {
        $facade = new self($config, $key);
        $facade->setExchange()
            ->setQueue()
            ->setQueueDeclareOptions()
            ->setDlxQueue();
        $amqp = $facade->amqp;
        $amqp->setQueueDeclareArgs($facade->queueDeclareArgs)
            ->initDeclare();

        return $amqp;
    }
}