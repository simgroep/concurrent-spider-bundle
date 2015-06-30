<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\Command;

use Exception;
use PHPUnit_Framework_TestCase;
use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use VDB\Uri\Exception\UriSyntaxException;

class CrawlCommandTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function jobIsSkippedWhenUrlIsAlreadyIndexed()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['rejectMessage', '__destruct', 'listen'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('rejectMessage')
            ->with($this->isInstanceOf('PhpAmqpLib\Message\AMQPMessage'));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexed', 'setMetadata'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexed')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(true));

        $indexer
            ->expects($this->once())
            ->method('setMetadata')
            ->with($this->equalTo(['core' => 'corename']));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getCurrentUri', 'getEventDispatcher'])
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getEventDispatcher')
            ->will($this->returnValue($eventDispatcher));

        $userAgent = 'I am some agent';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queue, $indexer, $spider, $userAgent, $logger])
            ->setMethods(null)
            ->getMock();

        $input = new StringInput('');
        $output = new NullOutput();
        $command->run($input, $output);

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'uri' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => ['core' => 'corename']
            ]
        );

        $this->assertNull($command->crawlUrl($message));
    }

    /**
     * @test
     */
    public function exceptionResultsInEmergency()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['__destruct', 'listen', 'rejectMessage'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('rejectMessage')
            ->with($this->isInstanceOf('PhpAmqpLib\Message\AMQPMessage'));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexed', 'setMetadata'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexed')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(false));

        $indexer
            ->expects($this->once())
            ->method('setMetadata')
            ->with($this->equalTo(['core' => 'corename']));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(['setUserAgent'])
            ->getMock();

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->once())
            ->method('getClient')
            ->will($this->returnValue($client));

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getCurrentUri', 'getEventDispatcher', 'getRequestHandler', 'crawlUrl'])
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

        $userAgent = 'I am some agent';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('emergency');

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queue, $indexer, $spider, $userAgent, $logger])
            ->setMethods(null)
            ->getMock();

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'uri' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => ['core' => 'corename']
            ]
        );

        $command->crawlUrl($message);
    }

    /**
     * @test
     */
    public function rejectionForMalformedUrl()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['__destruct', 'listen', 'rejectMessage'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('rejectMessage')
            ->with($this->isInstanceOf('PhpAmqpLib\Message\AMQPMessage'));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexed', 'setMetadata'])
            ->getMock();

        $indexer
            ->expects($this->never())
            ->method('isUrlIndexed');

        $indexer
            ->expects($this->once())
            ->method('setMetadata')
            ->with($this->equalTo(['core' => 'corename']));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->never())
            ->method('getClient');

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getCurrentUri', 'getEventDispatcher', 'getRequestHandler', 'crawlUrl'])
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getEventDispatcher')
            ->will($this->returnValue($eventDispatcher));

        $spider
            ->expects($this->never())
            ->method('getRequestHandler');

        $spider
            ->expects($this->never())
            ->method('crawlUrl');

        $userAgent = 'I am some agent';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $logger
            ->expects($this->never())
            ->method('emergency');

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queue, $indexer, $spider, $userAgent, $logger])
            ->setMethods(null)
            ->getMock();

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'uri' => 'gibberish',
                'base_url' => 'gibberish',
                'blacklist' => [],
                'metadata' => ['core' => 'corename']
            ]
        );

        $command->crawlUrl($message);
    }

    /**
     * @test
     */
    public function serviceNotAvailableCausesRequeue()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['__destruct', 'listen', 'rejectMessageAndRequeue'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('rejectMessageAndRequeue')
            ->with($this->isInstanceOf('PhpAmqpLib\Message\AMQPMessage'));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexed', 'setMetadata'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexed')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(false));

        $indexer
            ->expects($this->once())
            ->method('setMetadata')
            ->with($this->equalTo(['core' => 'corename']));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(['setUserAgent'])
            ->getMock();

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->once())
            ->method('getClient')
            ->will($this->returnValue($client));

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getCurrentUri', 'getEventDispatcher', 'getRequestHandler', 'crawlUrl'])
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
            ->setMethods(['getResponse'])
            ->getMock();

        $response = $this
            ->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->setMethods(['getStatusCode'])
            ->getMock();

        $exception
            ->expects($this->exactly(2))
            ->method('getResponse')
            ->will($this->returnValue($response));

        $spider
            ->expects($this->once())
            ->method('crawlUrl')
            ->will($this->throwException($exception));

        $userAgent = 'I am some agent';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('emergency');

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queue, $indexer, $spider, $userAgent, $logger])
            ->setMethods(null)
            ->getMock();

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'uri' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => ['core' => 'corename']
            ]
        );

        $command->crawlUrl($message);
    }

    /**
     * @test
     */
    public function serviceNotAvailableCausesRejection()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['__destruct', 'listen', 'rejectMessage'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('rejectMessage')
            ->with($this->isInstanceOf('PhpAmqpLib\Message\AMQPMessage'));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexed', 'setMetadata'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexed')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(false));

        $indexer
            ->expects($this->once())
            ->method('setMetadata')
            ->with($this->equalTo(['core' => 'corename']));


        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(['setUserAgent'])
            ->getMock();

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->once())
            ->method('getClient')
            ->will($this->returnValue($client));

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getCurrentUri', 'getEventDispatcher', 'getRequestHandler', 'crawlUrl'])
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
            ->setMethods(['getResponse'])
            ->getMock();

        $response = $this
            ->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->setMethods(['getStatusCode'])
            ->getMock();

        $response
            ->expects($this->any())
            ->method('getStatusCode')
            ->will($this->returnValue(404));

        $exception
            ->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue($response));

        $spider
            ->expects($this->once())
            ->method('crawlUrl')
            ->will($this->throwException($exception));

        $userAgent = 'I am some agent';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('warning');

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queue, $indexer, $spider, $userAgent, $logger])
            ->setMethods(null)
            ->getMock();

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'uri' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => ['core' => 'corename'],
            ]
        );

        $command->crawlUrl($message);
    }

    /**
     * @test
     */
    public function uriSyntaxExceptionCausesRequeue()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['__destruct', 'listen', 'rejectMessageAndRequeue'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('rejectMessageAndRequeue')
            ->with($this->isInstanceOf('PhpAmqpLib\Message\AMQPMessage'));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexed', 'setMetadata'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexed')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(false));

        $indexer
            ->expects($this->once())
            ->method('setMetadata')
            ->with($this->equalTo(['core' => 'corename']));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(['setUserAgent'])
            ->getMock();

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->once())
            ->method('getClient')
            ->will($this->returnValue($client));

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getCurrentUri', 'getEventDispatcher', 'getRequestHandler', 'crawlUrl'])
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
            ->will($this->throwException(new UriSyntaxException()));

        $userAgent = 'I am some agent';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('warning');

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queue, $indexer, $spider, $userAgent, $logger])
            ->setMethods(null)
            ->getMock();

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'uri' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => ['core' => 'corename']
            ]
        );

        $command->crawlUrl($message);
    }

    /**
     * @test
     */
    public function invalidContentExceptionCausesRejection()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['__destruct', 'listen', 'rejectMessage'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('rejectMessage')
            ->with($this->isInstanceOf('PhpAmqpLib\Message\AMQPMessage'));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexed', 'setMetadata'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexed')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(false));

        $indexer
            ->expects($this->once())
            ->method('setMetadata')
            ->with($this->equalTo(['core' => 'corename']));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(['setUserAgent'])
            ->getMock();

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->once())
            ->method('getClient')
            ->will($this->returnValue($client));

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getCurrentUri', 'getEventDispatcher', 'getRequestHandler', 'crawlUrl'])
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
            ->will($this->throwException(new InvalidContentException()));

        $userAgent = 'I am some agent';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('emergency');

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queue, $indexer, $spider, $userAgent, $logger])
            ->setMethods(null)
            ->getMock();

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'uri' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => ['core' => 'corename']
            ]
        );

        $command->crawlUrl($message);
    }

    /**
     * @test
     */
    public function queueAcknowledge()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['__destruct', 'listen', 'acknowledge'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('acknowledge')
            ->with($this->isInstanceOf('PhpAmqpLib\Message\AMQPMessage'));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexed', 'setMetadata'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexed')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(false));

        $indexer
            ->expects($this->once())
            ->method('setMetadata')
            ->with($this->equalTo([]));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(['setUserAgent'])
            ->getMock();

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->once())
            ->method('getClient')
            ->will($this->returnValue($client));

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getCurrentUri', 'getEventDispatcher', 'getRequestHandler', 'crawlUrl'])
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

        $userAgent = 'I am some agent';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('info');

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queue, $indexer, $spider, $userAgent, $logger])
            ->setMethods(null)
            ->getMock();

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'uri' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => []
            ]
        );

        $command->crawlUrl($message);
    }

    /**
     * @test
     */
    public function canLogError()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getCurrentUri', 'getEventDispatcher'])
            ->disableOriginalConstructor()
            ->getMock();

        $userAgent = 'I am some agent';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['error'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('error')
            ->with($this->equalTo('Test'));

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queue, $indexer, $spider, $userAgent, $logger])
            ->setMethods(null)
            ->getMock();

        $command->logMessage('error', 'Test', 'https://github.com');

    }

    /**
     * @test
     */
    public function eventDispatched()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $argument = $this
            ->getMockBuilder('\Symfony\Component\EventDispatcher\Event')
            ->disableOriginalConstructor()
            ->setMethods(['toString'])
            ->getMock();

        $argument
            ->expects($this->once())
            ->method('toString')
            ->will($this->returnValue(''));

        $event = $this
            ->getMockBuilder('\Symfony\Component\EventDispatcher\Event')
            ->disableOriginalConstructor()
            ->setMethods(['getArgument'])
            ->getMock();

        $event
            ->expects($this->once())
            ->method('getArgument')
            ->will($this->returnValue($argument));

        $eventDispatcher = new MockEventDispatcher();
        $eventDispatcher->setMockEvent($event);

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getCurrentUri', 'getEventDispatcher'])
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getEventDispatcher')
            ->will($this->returnValue($eventDispatcher));

        $userAgent = 'I am some agent';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info'])
            ->getMock();

        $logger
            ->expects($this->exactly(2))
            ->method('info');

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queue, $indexer, $spider, $userAgent, $logger])
            ->setMethods(null)
            ->getMock();

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'uri' => 'gibberish',
                'base_url' => 'gibberish',
                'blacklist' => [],
                'metadata' => []
            ]
        );

        $command->crawlUrl($message);
    }
}

class MockEventDispatcher extends EventDispatcher
{
    public function setMockEvent($event)
    {
        $this->event = $event;
    }

    /**
     * @see EventDispatcherInterface::addListener()
     *
     * @api
     */
    public function addListener($eventName, $listener, $priority = 0)
    {
        $listener($this->event);
    }
}
