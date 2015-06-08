<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\Command;

use Exception;
use PHPUnit_Framework_TestCase;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use VDB\Uri\Uri;

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

    public function testIfExceptionResultsInEmergency()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(array('__destruct', 'listen', 'rejectMessage'))
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
            ->will($this->returnValue(false));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(array('setUserAgent'))
            ->getMock();

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(array('getClient'))
            ->getMock();

        $requestHandler
            ->expects($this->once())
            ->method('getClient')
            ->will($this->returnValue($client));

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(array('getCurrentUri', 'getEventDispatcher', 'getRequestHandler', 'crawlUrl'))
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getEventDispatcher')
            ->will($this->returnValue($eventDispatcher));

        $spider
            ->expects($this->once())
            ->method('getRequestHandler')
            ->will($this->returnValue($requestHandler));

        $spider
            ->expects($this->once())
            ->method('crawlUrl')
            ->will($this->throwException(new Exception()));

        $uri = new Uri('https://github.com');
        $userAgent = 'I am some agent';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(array('info', 'warning', 'emergency'))
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('emergency');

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs(array($queue, $indexer, $spider, $userAgent, $logger))
            ->setMethods(null)
            ->getMock();

        $input = new StringInput('');
        $output = new NullOutput();

        $message = new AMQPMessage();
        $message->body = json_encode(
            array(
                'uri' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => array()
            )
        );

        $command->crawlUrl($message);
    }

    public function testIfServiceNotAvailableMakesTheMessageToRequeue()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(array('__destruct', 'listen', 'rejectMessageAndRequeue'))
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('rejectMessageAndRequeue')
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
            ->will($this->returnValue(false));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(array('setUserAgent'))
            ->getMock();

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(array('getClient'))
            ->getMock();

        $requestHandler
            ->expects($this->once())
            ->method('getClient')
            ->will($this->returnValue($client));

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(array('getCurrentUri', 'getEventDispatcher', 'getRequestHandler', 'crawlUrl'))
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getEventDispatcher')
            ->will($this->returnValue($eventDispatcher));

        $spider
            ->expects($this->once())
            ->method('getRequestHandler')
            ->will($this->returnValue($requestHandler));

        $exception = $this
            ->getMockBuilder('Guzzle\Http\Exception\ClientErrorResponseException')
            ->disableOriginalConstructor()
            ->setMethods(array('getResponse'))
            ->getMock();

        $response = $this
            ->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->setMethods(array('getStatusCode'))
            ->getMock();

        $exception
            ->expects($this->exactly(2))
            ->method('getResponse')
            ->will($this->returnValue($response));

        $spider
            ->expects($this->once())
            ->method('crawlUrl')
            ->will($this->throwException($exception));

        $uri = new Uri('https://github.com');
        $userAgent = 'I am some agent';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(array('info', 'warning', 'emergency'))
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('emergency');

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs(array($queue, $indexer, $spider, $userAgent, $logger))
            ->setMethods(null)
            ->getMock();

        $input = new StringInput('');
        $output = new NullOutput();

        $message = new AMQPMessage();
        $message->body = json_encode(
            array(
                'uri' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => array()
            )
        );

        $command->crawlUrl($message);
    }

    public function testCanLogError()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(array())
            ->getMock();

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array())
            ->getMock();

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

        $userAgent = 'I am some agent';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(array('error'))
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('error')
            ->with($this->equalTo('Test'));

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs(array($queue, $indexer, $spider, $userAgent, $logger))
            ->setMethods(null)
            ->getMock();

        $command->logMessage('error', 'Test', 'https://github.com');

    }
}
