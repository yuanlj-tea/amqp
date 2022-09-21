### Installation

Use composer:

```php
oomposer require yuanlj-tea/amqp
```

### Usage

配置文件

```php
配置文件模板路径，复制一份到自己的项目：
/publish/amqp.php
  
配置文件内容：

<?php
return [
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
```

### usage demo

生产

```php
$config = require __DIR__ . '/../../publish/amqp.php';
$amqp = \Amqp\AmqpFacade::getAmqp($config, 'queue_key_demo');
$amqp->produce('foo');
```



消费

```php
$callback = function (\PhpAmqpLib\Message\AMQPMessage $message) {
		var_dump($message->body);
};
$config = require __DIR__ . '/../../publish/amqp.php';
$amqp = \Amqp\AmqpFacade::getAmqp($config, 'queue_key_demo');
$amqp->consume($callback);
```

消费

`继承\Amqp\Consume\AbstractConsume抽象类`

```php
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Message\AMQPMessage;

class TestConsume extends \Amqp\Consume\AbstractConsume
{
  	/**
     * 消费数据后执行逻辑
     * @param AMQPMessage $msg
     * @return mixed
     */
    public static function exec(AMQPMessage $msg)
    {
        var_dump($msg->getBody(), '消费消息');
        throw new \Exception('test');
    }

  	/**
     * 重试次数达到最大后，执行逻辑
     * @param AMQPMessage $msg
     * @return mixed
     */
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
```

