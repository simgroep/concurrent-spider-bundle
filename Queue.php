<?php

namespace Simgroep\ConcurrentSpiderBundle;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * This class is a gateway to the queing system that contains all the jobs for webpages that should be crawled.
 */
class Queue
{
    /**
     * @var \PhpAmqpLib\Connection\AMQPConnection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $queueName;

    /**
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
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

    /**
     * Close the connection to RabbitMQ.
     */
    public function __destruct()
    {
        if ($this->connection->isConnected()) {
            $this->connection->close();
        }
    }

    /**
     * Creates (if not yet created) and returns an AMQP channel.
     *
     * @return \PhpAmqpLib\Channel\AMQPChannel
     */
    protected function getChannel()
    {
        if (null === $this->channel) {
            $this->channel = $this->connection->channel();
            $this->channel->queue_declare($this->queueName, false, false, false, false);
            $this->channel->basic_qos(null, 1, null);
        }

        return $this->channel;
    }

    /**
     * Publish a job to the queue.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     *
     * @return \Simgroep\ConcurrentSpiderBundle\Queue
     */
    public function publish(AMQPMessage $message)
    {
        $this->getChannel()->basic_publish($message, '', $this->queueName);

        return $this;
    }

    public function publishJob(CrawlJob $crawlJob)
    {
        $message = new AMQPMessage(
            json_encode($crawlJob->toArray()),
            ['delivery_mode' => 1]
        );

        return $this->publish($message);
    }

    /**
     * Listen to a queue and consume webpages to crawl.
     *
     * @param \Callable $callback The method that should be called for every job
     *
     * @return \Simgroep\ConcurrentSpiderBundle\Queue
     */
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

        while (count($channel->callbacks)) {
            $channel->wait();
        }

        return $this;
    }

    /**
     * Reject a job and remove it from the queue.
     *
     * @param \Callable $callback The method that should be called for every job
     *
     * @return \Simgroep\ConcurrentSpiderBundle\Queue
     */
    public function rejectMessage(AMQPMessage $message)
    {
        $message->delivery_info['channel']->basic_reject($message->delivery_info['delivery_tag'], false);

        return $this;
    }

    /**
     * Rejects a job and put's it back on the queue.
     *
     * This will cause the message to be taken back from the queue and make it to process it again.
     *
     * @param \Callable $callback The method that should be called for every job
     *
     * @return \Simgroep\ConcurrentSpiderBundle\Queue
     */
    public function rejectMessageAndRequeue(AMQPMessage $message)
    {
        $message->delivery_info['channel']->basic_reject($message->delivery_info['delivery_tag'], true);

        return $this;
    }

    /**
     * Acknowledge the queue that the message is processed succesfully.
     *
     * @param \Callable $callback The method that should be called for every job
     *
     * @return \Simgroep\ConcurrentSpiderBundle\Queue
     */
    public function acknowledge(AMQPMessage $message)
    {
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);

        return $this;
    }
}
