<?php

namespace Amqp;

use Amqp\Exception\InvalidParam;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

abstract class AbstractAmqp
{
    /**
     * @var AbstractAmqp[]
     */
    private static $instance = [];

    /**
     * amqp server config
     * @var array
     */
    private $config = [];

    /**
     * @var AMQPStreamConnection
     */
    protected $connection;

    /**
     * @var AMQPChannel
     */
    protected $channel;

    /**
     * 是否自动ack应答
     * @var bool
     */
    protected $autoAck = false;

    protected $run = true;

    /**
     * @var ExchangeParams
     */
    protected $exchangeParams;

    /**
     * @var QueueParams
     */
    protected $queueParams;

    /**
     * @var QueueDeclareArgs
     */
    protected $queueDeclareArgs;

    /**
     * @var string 消费者tag
     */
    protected $consumerTag;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->createConnectRandomly();
    }

    public function __destruct()
    {
        $this->closeConnect();
    }

    public static function getInstance($config)
    {
        $hash = md5(json_encode($config));
        if (!isset(self::$instance[$hash]) || !self::$instance[$hash] instanceof self) {
            self::$instance[$hash] = new static($config);
        }
        return self::$instance[$hash];
    }

    /**
     * @return ExchangeParams
     */
    public function getExchangeParams(): ExchangeParams
    {
        return $this->exchangeParams;
    }

    /**
     * @param ExchangeParams $exchangeParams
     */
    public function setExchangeParams(ExchangeParams $exchangeParams): AbstractAmqp
    {
        $this->exchangeParams = $exchangeParams;
        return $this;
    }

    /**
     * @return QueueParams
     */
    public function getQueueParams(): QueueParams
    {
        return $this->queueParams;
    }

    /**
     * @param QueueParams $queueParams
     */
    public function setQueueParams(QueueParams $queueParams): AbstractAmqp
    {
        $this->queueParams = $queueParams;
        return $this;
    }

    /**
     * @return QueueDeclareArgs
     */
    public function getQueueDeclareArgs(): QueueDeclareArgs
    {
        return $this->queueDeclareArgs;
    }

    /**
     * @param QueueDeclareArgs $queueDeclareArgs
     */
    public function setQueueDeclareArgs(QueueDeclareArgs $queueDeclareArgs): AbstractAmqp
    {
        $this->queueDeclareArgs = $queueDeclareArgs;
        return $this;
    }

    /**
     * 随机创建amqp连接
     */
    public function createConnectRandomly()
    {
        $hosts = $this->config['hosts'] ?? [];
        if (!(!empty($hosts) && is_array($hosts) && count($hosts) > 1)) {
            $this->createConnect();
            return;
        }

        shuffle($hosts);
        $lastException = null;
        foreach ($hosts as $hostdef) {
            $this->validConfig($hostdef);
            $host = $hostdef['host'];
            $port = $hostdef['port'];
            $user = $hostdef['user'];
            $pwd = $hostdef['pwd'];
            $vhost = $hostdef['vhost'];
            try {
                $this->createConnect($host, $port, $user, $pwd, $vhost);
                return;
            } catch (\Throwable $t) {
                $lastException = $t;
            }
        }
        throw $lastException;
    }

    /**
     * 创建amqp连接
     * @param string $host
     * @param string $port
     * @param string $user
     * @param string $pwd
     * @param string $vhost
     * @throws InvalidParam
     */
    public function createConnect($host = '', $port = '', $user = '', $pwd = '', $vhost = '')
    {
        $host = $host ?: ($this->config['host'] ?? '');
        $port = $port ?: ($this->config['port'] ?? '');
        $user = $user ?: ($this->config['user'] ?? '');
        $pwd = $pwd ?: ($this->config['pwd'] ?? '');
        $vhost = $vhost ?: ($this->config['vhost'] ?? '');
        if (empty($host) || empty($port) || empty($user) || empty($pwd) || empty($vhost)) {
            throw new InvalidParam('rabbitmq配置错误');
        }
        // 创建amqp连接
        $this->connection = new AMQPStreamConnection($host, $port, $user, $pwd, $vhost);
        // 创建channel
        $this->channel = $this->connection->channel();
    }

    private function validConfig($conf)
    {
        if (!isset($conf['host'])) {
            throw new InvalidParam("'host' key is required.");
        }
        if (!isset($conf['port'])) {
            throw new InvalidParam("'port' key is required.");
        }
        if (!isset($conf['user'])) {
            throw new InvalidParam("'user' key is required.");
        }
        if (!isset($conf['pwd'])) {
            throw new InvalidParam("'pwd' key is required.");
        }
        if (!isset($conf['vhost'])) {
            throw new InvalidParam("'vhost' key is required.");
        }
    }

    /**
     * 声明交换机
     */
    protected function exchangeDeclare()
    {
        /**
         * 创建交换机$channel->exchange_declare($exhcange_name,$type,$passive,$durable,$auto_delete);
         * passive: 消极处理， 判断是否存在队列，存在则返回，不存在直接抛出 PhpAmqpLib\Exception\AMQPProtocolChannelException 异常
         * durable：true、false true：服务器重启会保留下来Exchange。警告：仅设置此选项，不代表消息持久化。即不保证重启后消息还在
         * autoDelete:true、false.true:当已经没有消费者时，服务器是否可以删除该Exchange
         */
        $exchangeName = $this->exchangeParams->getName();
        $exchangeType = $this->exchangeParams->getType();
        list($passive, $durable, $autodelete) = $this->exchangeParams->getExchangeParams();

        $this->channel->exchange_declare($exchangeName, $exchangeType, $passive, $durable, $autodelete);
    }

    /**
     * 声明队列
     */
    protected function queueDeclare()
    {
        /**
         * passive: 消极处理， 判断是否存在队列，存在则返回，不存在直接抛出 PhpAmqpLib\Exception\AMQPProtocolChannelException 异常
         * durable：true、false true：在服务器重启时，能够存活
         * exclusive ：是否为当前连接的专用队列，在连接断开后，会自动删除该队列
         * autodelete：当没有任何消费者使用时，自动删除该队列
         * nowait：如果为True则表示不等待服务器回执信息.函数将返回NULL,可以提高访问速度
         * arguments: 自定义规则
         * see：https://blog.csdn.net/wwwwxyxy/article/details/84654787
         */
        $params = $this->queueParams;
        $queueName = $params->getName();
        $queueParams = $params->getQueueParams();
        list($passive, $durable, $exclusive, $autodelete, $nowait) = $queueParams;
        $this->channel->queue_declare($queueName, $passive, $durable, $exclusive, $autodelete, $nowait, new AMQPTable($this->queueDeclareArgs->getQueueDeclareArg()));
    }

    protected function queueBind()
    {
        $this->channel->queue_bind($this->queueParams->getName(), $this->exchangeParams->getName(), $this->queueParams->getRouteKey());
    }

    /**
     * 初始化声明交换机、队列
     */
    public function initDeclare()
    {
        $this->exchangeDeclare();
        $this->queueDeclare();
        $this->queueBind();

        return $this;
    }

    /**
     * 关闭连接
     * @throws \Exception
     */
    public function closeConnect()
    {
        $this->channel->close();
        $this->connection->close();
    }

    /**
     * 清理单例的参数
     * @return $this
     */
    public function clearParams()
    {
        $this->exchangeParams->clearParams();
        $this->queueParams->clearParams();
        $this->queueDeclareArgs->clearParam();

        return $this;
    }

    public function buildMsg(string $payload, $deliveryMode = AMQPMessage::DELIVERY_MODE_NON_PERSISTENT)
    {
        return new AMQPMessage($payload, ['delivery_mode' => $deliveryMode]);
    }

    /**
     * 获取消费者Tag
     * @return string
     */
    protected function getConsumerTag($bizType = '')
    {
        $pid = '';
        if (function_exists('posix_getpid')) {
            $pid = posix_getpid();
        }

        return sprintf("%s%s:%s:%s", $bizType, gethostname(), mt_rand(100000, 9999999), $pid);
    }
}