<?php
return [
    'host' => '127.0.0.1',
    'port' => '5672',
    'user' => 'admin',
    'pwd' => 'admin',
    'vhost' => '/',

    // rabbitmq集群配置，从集群里随机创建连接
    'hosts' => [
        [
            'host' => '127.0.0.1',
            'port' => '5672',
            'user' => 'admin',
            'pwd' => 'admin',
            'vhost' => '/'
        ],
        [
            'host' => '127.0.0.1',
            'port' => '5673',
            'user' => 'admin',
            'pwd' => 'admin',
            'vhost' => '/'
        ],
    ],

    // 队列相关配置
    'queue_key_demo' => [
        'exchange_name' => 'exchange_name_demo',
        'queue_name' => 'queue_name_demo',
        'route_key' => 'route_key_demo',
        'exchange_type' => \PhpAmqpLib\Exchange\AMQPExchangeType::DIRECT,
        // 交换机参数
        'exchange_param' => \Amqp\ExchangeParams::DEFAULT_PARAMS,
        // 队列参数
        'queue_param' => \Amqp\QueueParams::DEFAULT_PARAMS,
        'option' => [
            'x-max-priority' => 10,
        ],
        // 死信队列配置
        /*'dlx_params' => [
            'dlx_exchange_name' => 'dlx_exchange_name',
            'dlx_queue_name' => 'dlx_queue_name',
            'dlx_routing_key' => 'dlx_routing_key',
            'dlx_msg_ttl' => 1000*60,
        ],*/
    ],
];