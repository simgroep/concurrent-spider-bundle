<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\Command;

use PHPUnit_Framework_TestCase;
use PhpAmqpLib\Message\AMQPMessage;
use VDB\Uri\Uri;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class CrawlCommandTest extends PHPUnit_Framework_TestCase
{
    public function testIfJobIsSkippedWhenUrlIsAlreadyIndexed()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(array('rejectMessage', '__destruct', 'listen'))
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('rejectMessage')
            ->with($this->isInstanceOf('PhpAmqpLib\Message\AMQPMessage'));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array('isUrlIndexed'))
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexed')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(true));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(array('getCurrentUri', 'getEventDispatcher'))
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getEventDispatcher')
            ->will($this->returnValue($eventDispatcher));

        $uri = new Uri('https://github.com');
        $userAgent = 'I am some agent';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(array('info', 'warning', 'emergency'))
            ->getMock();

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs(array($queue, $indexer, $spider, $userAgent, $logger))
            ->setMethods(null)
            ->getMock();

        $input = new StringInput('');
        $output = new NullOutput();
        $command->run($input, $output);

        $message = new AMQPMessage();
        $message->body = json_encode(
            array(
                'uri' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => array()
            )
        );

        $this->assertNull($command->crawlUrl($message));
    }
}
