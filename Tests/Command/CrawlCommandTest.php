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
    public function deleteDocumentWhenItsIndexedAndNotExistAnymore()
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
            ->setMethods(['isUrlIndexedAndNotExpired', 'deleteDocument'])
            ->getMock();
        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com'), ['core' => 'corename'])
            ->will($this->returnValue(true));
        $indexer
            ->expects($this->once())
            ->method('deleteDocument');

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

        $guzzleResponse = $this->getMockBuilder('Guzzle\Http\Message\Response')
            ->setMethods(['getStatusCode'])
            ->disableOriginalConstructor()
            ->getMock();
        $guzzleResponse
            ->expects($this->once())
            ->method('getStatusCode')
            ->will($this->returnValue(400));

        $exception = $this
            ->getMockBuilder('Guzzle\Http\Exception\ClientErrorResponseException')
            ->disableOriginalConstructor()
            ->setMethods(['getResponse'])
            ->getMock();
        $exception
            ->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue($guzzleResponse));

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient', 'request'])
            ->getMock();
        $requestHandler
            ->expects($this->once())
            ->method('getClient')
            ->will($this->returnValue($client));
        $requestHandler
            ->expects($this->once())
            ->method('request')
            ->will($this->throwException($exception));

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['crawl'])
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler])
            ->getMock();

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
                'url' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => ['core' => 'corename'],
                'whitelist' => [],
            ]
        );

        $this->assertNull($command->crawlUrl($message));
    }

    /**
     * @test
     */
    public function skipJobWhenDocumentIsIndexedButStillExist()
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
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();
        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com'), ['core' => 'corename'])
            ->will($this->returnValue(true));

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
            ->setMethods(['getClient', 'request'])
            ->getMock();
        $requestHandler
            ->expects($this->once())
            ->method('getClient')
            ->will($this->returnValue($client));
        $requestHandler
            ->expects($this->once())
            ->method('request');

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['crawl'])
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler])
            ->getMock();

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
                'url' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => ['core' => 'corename'],
                'whitelist' => [],
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
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
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

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['crawl'])
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('crawl')
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
                'url' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => ['core' => 'corename'],
                'whitelist' => [],
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
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->never())
            ->method('isUrlIndexedAndNotExpired');

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
            ->setMethods(['getRequestHandler', 'crawl'])
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->never())
            ->method('getRequestHandler');

        $spider
            ->expects($this->never())
            ->method('crawl');

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
                'url' => 'gibberish',
                'base_url' => 'gibberish',
                'blacklist' => [],
                'metadata' => ['core' => 'corename'],
                'whitelist' => [],
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
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(false));

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
            ->setMethods(['getRequestHandler', 'crawl'])
            ->disableOriginalConstructor()
            ->getMock();

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
            ->method('crawl')
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
                'url' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => ['core' => 'corename'],
                'whitelist' => [],
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
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(false));

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
            ->setMethods(['getRequestHandler', 'crawl'])
            ->disableOriginalConstructor()
            ->getMock();

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
            ->method('crawl')
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
                'url' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => ['core' => 'corename'],
                'whitelist' => [],
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
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(false));

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
            ->setMethods(['getRequestHandler', 'crawl'])
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getRequestHandler')
            ->will($this->returnValue($requestHandler));

        $spider
            ->expects($this->once())
            ->method('crawl')
            ->will($this->throwException(new UriSyntaxException()));

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
                'url' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => ['core' => 'corename'],
                'whitelist' => [],
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
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(false));

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
            ->setMethods(['getRequestHandler', 'crawl'])
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getRequestHandler')
            ->will($this->returnValue($requestHandler));

        $spider
            ->expects($this->once())
            ->method('crawl')
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
                'url' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => ['core' => 'corename'],
                'whitelist' => [],
            ]
        );

        $command->crawlUrl($message);
    }

    /**
     * @test
     */
    public function queueAcknowledgeFromWhitelist()
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
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('http://ggg.nl'))
            ->will($this->returnValue(false));

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
            ->setMethods(['getRequestHandler', 'crawl'])
            ->disableOriginalConstructor()
            ->getMock();

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
                'url' => 'http://ggg.nl',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => [],
                'whitelist' => ['http://ggg.nl'],
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
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(false));

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
            ->setMethods(['getRequestHandler', 'crawl'])
            ->disableOriginalConstructor()
            ->getMock();

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
                'url' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => [],
                'whitelist' => [],
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
            ->setMethods(null)
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
