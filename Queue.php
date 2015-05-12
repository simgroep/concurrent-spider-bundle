<?php

namespace Simgroep\ConcurrentSpiderBundle;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Queue
{
    protected $connection;
    protected $queueName;
    protected $channel;

    /**
     * Constructor.
     *
     * @param \PhpAmqpLib\Connection\AMQPConnection $connection
     * @param string                                $queueName
     */
    public function __construct(AMQPConnection $connection, $queueName)
    {
        $this->connection = $connection;
        $this->queueName = $queueName;
    }

    public function __destruct()
    {
    /*    if ($this->connection->isConnected()) {
            $this->getChannel()->close();
            $this->connection->close();
    }
     */
    }

    protected function getChannel()
    {
        if (null === $this->channel) {
            $this->channel = $this->connection->channel();
            $this->channel->queue_declare($this->queueName, false, false, false, false);
            $this->channel->basic_qos(null, 1, null);
        }

        return $this->channel;
    }

    public function publish(AMQPMessage $message)
    {
        $this->getChannel()->basic_publish($message, '', $this->queueName);

        return $this;
    }

    public function listen(Callable $callback)
    {
        $channel = $this->getChannel();

        $channel->basic_consume(
            $this->queueName,
            '',
            false,
            false,
            false,
            false,
            $callback
        );

        while(count($channel->callbacks)) {
            $channel->wait();
        }

        return $this;
    }

    public function rejectMessage(AMQPMessage $message)
    {
        $message->delivery_info['channel']->basic_reject($message->delivery_info['delivery_tag'], false);

        return $this;
    }

    public function rejectMessageAndRequeue(AMQPMessage $message)
    {
        $message->delivery_info['channel']->basic_reject($message->delivery_info['delivery_tag'], true);

        return $this;
    }

    public function acknowledge(AMQPMessage $message)
    {
        try {
            $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
        } catch (\PhpAmqpLib\Exception\AMQPProtocolChannelException $e) {
            echo "awkard \n";
        }

        return $this;

    }
}
