<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\Queue;

class QueueTest extends PHPUnit_Framework_TestCase
{

    public function testPublish()
    {
        $queueName = 'queue1';

        $message = $this->getMockBuilder('PhpAmqpLib\Message\AMQPMessage')
                ->getMock();

        $channel = $this->getChannel($queueName);
        $channel->expects($this->once())
                ->method('basic_publish')
                ->with($message, $this->equalTo(''), $this->equalTo($queueName));

        $connection = $this->getMockBuilder('PhpAmqpLib\Connection\AMQPConnection')
                ->disableOriginalConstructor()
                ->setMethods(['channel', 'isConnected', 'close'])
                ->getMock();
        $connection->expects($this->once())
                ->method('channel')
                ->will($this->returnValue($channel));
        $connection->expects($this->once())
                ->method('isConnected')
                ->will($this->returnValue(true));
        $connection->expects($this->once())
                ->method('close');

        $queue = new Queue($connection, $queueName);
        $queue->publish($message);
    }

    public function testListen()
    {
        $queueName = 'queue2';

        $callback = new QueueCallableClass();

        $channel = $this->getChannel($queueName, true);
        $channel->expects($this->once())
                ->method('basic_consume')
                ->with($this->equalTo($queueName), $this->equalTo(''), $this->equalTo(false), $this->equalTo(false), $this->equalTo(false), $this->equalTo(false), $callback);

        $connection = $this->getMockBuilder('PhpAmqpLib\Connection\AMQPConnection')
                ->disableOriginalConstructor()
                ->setMethods(['channel', 'isConnected', 'close'])
                ->getMock();
        $connection->expects($this->once())
                ->method('channel')
                ->will($this->returnValue($channel));
        $connection->expects($this->once())
                ->method('isConnected')
                ->will($this->returnValue(true));
        $connection->expects($this->once())
                ->method('close');

        $queue = new Queue($connection, $queueName);
        $queue->listen($callback);
    }

    public function testRejectMessage()
    {
        $queueName = 'queue3';

        $channel = $this->getMockBuilder('PhpAmqpLib\Channel\AMQPChannel')
                ->disableOriginalConstructor()
                ->setMethods(['basic_reject'])
                ->getMock();
        $channel->expects($this->once())
                ->method('basic_reject')
                ->with($this->equalTo('dummyTag'), $this->equalTo(false));

        $message = $this->getMockBuilder('PhpAmqpLib\Message\AMQPMessage')
                ->getMock();
        $message->delivery_info = [];
        $message->delivery_info['channel'] = $channel;
        $message->delivery_info['delivery_tag'] = 'dummyTag';

        $connection = $this->getMockBuilder('PhpAmqpLib\Connection\AMQPConnection')
                ->disableOriginalConstructor()
                ->setMethods(['isConnected', 'close'])
                ->getMock();
        $connection->expects($this->once())
                ->method('isConnected')
                ->will($this->returnValue(true));
        $connection->expects($this->once())
                ->method('close');

        $queue = new Queue($connection, $queueName);
        $queue->rejectMessage($message);
    }

    public function testRejectMessageAndRequeue()
    {
        $queueName = 'queue4';

        $channel = $this->getMockBuilder('PhpAmqpLib\Channel\AMQPChannel')
                ->disableOriginalConstructor()
                ->setMethods(['basic_reject'])
                ->getMock();
        $channel->expects($this->once())
                ->method('basic_reject')
                ->with($this->equalTo('dummyTag2'), $this->equalTo(true));

        $message = $this->getMockBuilder('PhpAmqpLib\Message\AMQPMessage')
                ->getMock();
        $message->delivery_info = [];
        $message->delivery_info['channel'] = $channel;
        $message->delivery_info['delivery_tag'] = 'dummyTag2';

        $connection = $this->getMockBuilder('PhpAmqpLib\Connection\AMQPConnection')
                ->disableOriginalConstructor()
                ->setMethods(['isConnected', 'close'])
                ->getMock();
        $connection->expects($this->once())
                ->method('isConnected')
                ->will($this->returnValue(true));
        $connection->expects($this->once())
                ->method('close');

        $queue = new Queue($connection, $queueName);
        $queue->rejectMessageAndRequeue($message);
    }

    public function testAcknowledge()
    {
        $queueName = 'queue5';

        $channel = $this->getMockBuilder('PhpAmqpLib\Channel\AMQPChannel')
                ->disableOriginalConstructor()
                ->setMethods(['basic_ack'])
                ->getMock();
        $channel->expects($this->once())
                ->method('basic_ack')
                ->with($this->equalTo('dummyTag3'));

        $message = $this->getMockBuilder('PhpAmqpLib\Message\AMQPMessage')
                ->getMock();
        $message->delivery_info = [];
        $message->delivery_info['channel'] = $channel;
        $message->delivery_info['delivery_tag'] = 'dummyTag3';

        $connection = $this->getMockBuilder('PhpAmqpLib\Connection\AMQPConnection')
                ->disableOriginalConstructor()
                ->setMethods(['isConnected', 'close'])
                ->getMock();
        $connection->expects($this->once())
                ->method('isConnected')
                ->will($this->returnValue(true));
        $connection->expects($this->once())
                ->method('close');

        $queue = new Queue($connection, $queueName);
        $queue->acknowledge($message);
    }

    /**
     * Get Channel
     *
     * @param string $queueName
     * @return PhpAmqpLib\Channel\AMQPChannel
     */
    protected function getChannel($queueName, $wait = false)
    {
        $channel = $this->getMockBuilder('PhpAmqpLib\Channel\AMQPChannel')
            ->disableOriginalConstructor()
            ->setMethods(['queue_declare', 'basic_qos', 'basic_publish', 'basic_consume', 'wait'])
            ->getMock();
        $channel->expects($this->once())
            ->method('queue_declare')
            ->with($this->equalTo($queueName), $this->equalTo(false), $this->equalTo(false), $this->equalTo(false), $this->equalTo(false));
        $channel->expects($this->once())
            ->method('basic_qos')
            ->with($this->equalTo(null), $this->equalTo(1), $this->equalTo(null));
        if ($wait === true) {
            $channel->callbacks = [1, 2];
            
            $channel->expects($this->exactly(2))
                ->method('wait')
                ->will($this->returnCallback(function () use ($channel) {
                    # gradually reset callbacks property so wait() will no longer be called
                    array_pop($channel->callbacks);
                }));
        }

        return $channel;
    }

}

class QueueCallableClass
{

    public function __invoke()
    {

    }

}
