<?php

namespace Simgroep\ConcurrentSpiderBundle;

use PhpAmqpLib\Connection\AbstractConnection;
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
     * @var boolean
     */
    protected $revisitDisabled;

    /**
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    protected $channel;

    /**
     * Constructor.
     *
     * @param AbstractConnection $connection
     * @param string             $queueName
     * @param boolean            $revisitDisabled
     */
    public function __construct(AbstractConnection $connection, $queueName, $revisitDisabled = true)
    {
        $this->connection = $connection;
        $this->queueName = $queueName;
        $this->revisitDisabled = $revisitDisabled;
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

            $this->declareQueue($this->queueName);

            if ($this->revisitDisabled == false) {
                $this->declareQueue('revisit');
            }

            $this->channel->basic_qos(null, 1, null);
        }

        return $this->channel;
    }

    /**
     * Publish a job to the queue.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     * @param string|null                     $queueName
     *
     * @return \Simgroep\ConcurrentSpiderBundle\Queue
     */
    public function publish(AMQPMessage $message, $queueName = null)
    {
        if (null == $queueName) {
            $queueName = $this->queueName;
        }

        $this->getChannel()->basic_publish($message, '', $queueName);

        return $this;
    }

    /**
     * @param \Simgroep\ConcurrentSpiderBundle\CrawlJob $crawlJob
     *
     * @return \Simgroep\ConcurrentSpiderBundle\Queue
     */
    public function publishJob(CrawlJob $crawlJob)
    {
        $message = new AMQPMessage(
            json_encode($crawlJob->toArray()),
            ['delivery_mode' => 1]
        );

        return $this->publish($message, $crawlJob->getQueueName());
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

        $this->consumeBasic($this->queueName, $callback, $channel);

        if ($this->revisitDisabled == false) {
            $this->consumeBasic('revisit', $callback, $channel);
        }

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

    /**
     * Declare a queue on channel
     *
     * @param string   $queueName
     */
    protected function declareQueue($queueName)
    {
        $this->channel->queue_declare($queueName, false, false, false, false);
    }

    /**
     * Basic consume of queue
     *
     * @param string                          $queue
     * @param \Callable                       $callback The method that should be called for every job
     * @param \PhpAmqpLib\Channel\AMQPChannel $channel
     */
    protected function consumeBasic($queue, Callable $callback, $channel)
    {
        $channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            $callback
        );
    }

    /**
     * Get queue name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->queueName;
    }
}
