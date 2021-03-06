<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\Command;

use Exception;
use Guzzle\Common\Collection;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Guzzle\Http\Message\Response;
use PHPUnit_Framework_TestCase;
use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\CurlClient;
use Simgroep\ConcurrentSpiderBundle\PageBlacklistedException;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;

class CrawlCommandTest extends PHPUnit_Framework_TestCase
{

    /**
     * @test
     * @dataProvider rejectResponseCodeDataProvider
     */
    public function skipDocumentOnServerError($statusCode)
    {
        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'url' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => [
                    'core' => 'core1'
                ],
                'whitelist' => [],
                'queueName' => null,
            ]
        );

        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['__destruct', 'listen', 'rejectMessage'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('rejectMessage')
            ->with($message);

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(['getQueue'])
            ->getMock();

        $queueFactory
            ->expects($this->any())
            ->method('getQueue')
            ->will($this->returnValue($queue));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com/'))
            ->will($this->returnValue(false));

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(['setUserAgent', 'setSslVerification'])
            ->getMock();

        $client->setConfig(new Collection());

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->exactly(3))
            ->method('getClient')
            ->will($this->returnValue($client));

        $response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->setMethods(['getStatusCode'])
            ->getMock();

        $response
            ->expects($this->exactly(2))
            ->method('getStatusCode')
            ->will($this->returnValue($statusCode));


        $exception = new ClientErrorResponseException("Internal server error", $statusCode);
        $exception->setResponse($response);

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getRequestHandler', 'crawl'])
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->exactly(3))
            ->method('getRequestHandler')
            ->will($this->returnValue($requestHandler));

        $spider->expects($this->once())
            ->method("crawl")
            ->willThrowException($exception);

        $userAgent = 'I am some agent';
        $curlCertCADirectory = '/usr/local/share/certs/';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queueFactory, $indexer, $spider, $userAgent, $curlCertCADirectory, $logger])
            ->setMethods(null)
            ->getMock();

        $command
            ->setQueue($queue)
            ->crawlUrl($message);
    }

    /**
     * @test
     * @dataProvider redirectResponseCodeDataProvider
     */
    public function deleteDocumentAndCreateNewMessageWhenDocumentIsMoved($statusCode)
    {
        $redirect_url = "http://redirect.example.com";

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'url' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => [
                    'core' => 'core1'
                ],
                'whitelist' => [],
                'queueName' => null,
            ]
        );

        $crawlJob = new CrawlJob(
            $redirect_url,
            'https://github.com/',
            [],
            [
                'core' => 'core1'
            ],
            [],
            null
        );

        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['__destruct', 'listen', 'publishJob', 'rejectMessage'])
            ->getMock();
        $queue->expects($this->once())
            ->method('rejectMessage')
            ->with($this->equalTo($message));
        $queue->expects($this->once())
            ->method('publishJob')
            ->with($this->equalTo($crawlJob));

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(['getQueue'])
            ->getMock();

        $queueFactory
            ->expects($this->any())
            ->method('getQueue')
            ->will($this->returnValue($queue));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexedAndNotExpired', 'deleteDocument'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com/'))
            ->will($this->returnValue(false));

        $indexer
            ->expects($this->once())
            ->method('deleteDocument')
            ->with($this->equalTo($message))
            ->will($this->returnValue(false));

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(['setUserAgent', 'setSslVerification'])
            ->getMock();

        $client->setConfig(new Collection());

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->exactly(3))
            ->method('getClient')
            ->will($this->returnValue($client));

        $response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->setMethods(['getInfo', 'getStatusCode'])
            ->getMock();

        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->will($this->returnValue($statusCode));

        $response
            ->expects($this->once())
            ->method('getInfo')
            ->with($this->equalTo('redirect_url'))
            ->will($this->returnValue($redirect_url));

        $exception = new ClientErrorResponseException(sprintf(
            "Page moved to %s",
            $redirect_url
        ), $statusCode);
        $exception->setResponse($response);

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getRequestHandler', 'crawl'])
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->exactly(3))
            ->method('getRequestHandler')
            ->will($this->returnValue($requestHandler));

        $spider->expects($this->once())
            ->method("crawl")
            ->willThrowException($exception);

        $userAgent = 'I am some agent';
        $curlCertCADirectory = '/usr/local/share/certs/';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queueFactory, $indexer, $spider, $userAgent, $curlCertCADirectory, $logger])
            ->setMethods(null)
            ->getMock();

        $command
            ->setQueue($queue)
            ->crawlUrl($message);
    }

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

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(['getQueue'])
            ->getMock();

        $queueFactory
            ->expects($this->any())
            ->method('getQueue')
            ->will($this->returnValue($queue));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexedAndNotExpired', 'deleteDocument'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com/'), ['core' => 'corename'])
            ->will($this->returnValue(false));

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
            ->setMethods(['setUserAgent', 'setSslVerification'])
            ->getMock();

        $client->setConfig(new Collection());

        $guzzleResponse = $this->getMockBuilder('Guzzle\Http\Message\Response')
            ->setMethods(['getStatusCode'])
            ->disableOriginalConstructor()
            ->getMock();

        $guzzleResponse
            ->expects($this->once())
            ->method('getStatusCode')
            ->will($this->returnValue(404));

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
            ->expects($this->exactly(3))
            ->method('getClient')
            ->will($this->returnValue($client));

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $curlClient = $this->getMockBuilder(CurlClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cookiePlugin = $this
            ->getMockBuilder('Guzzle\Plugin\Cookie\CookiePlugin')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['crawl'])
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler, $curlClient, $cookiePlugin])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('crawl')
            ->will($this->throwException($exception));

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $userAgent = 'I am some agent';
        $curlCertCADirectory = '/usr/local/share/certs/';

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queueFactory, $indexer, $spider, $userAgent, $curlCertCADirectory, $logger])
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
                'queueName' => null,
            ]
        );

        $command->setQueue($queue);

        $this->assertNull($command->crawlUrl($message));
    }

    /**
     * @test
     */
    public function ifJobIsSkippedWhenUrlIsIndexedAndNotExpired()
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

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(['getQueue'])
            ->getMock();

        $queueFactory
            ->expects($this->any())
            ->method('getQueue')
            ->will($this->returnValue($queue));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com/'), ['core' => 'corename'])
            ->will($this->returnValue(true));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient', 'request'])
            ->getMock();

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $curlClient = $this->getMockBuilder(CurlClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cookiePlugin = $this
            ->getMockBuilder('Guzzle\Plugin\Cookie\CookiePlugin')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['crawl'])
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler, $curlClient, $cookiePlugin])
            ->getMock();

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $userAgent = 'I am some agent';
        $curlCertCADirectory = '/usr/local/share/certs/';

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queueFactory, $indexer, $spider, $userAgent, $curlCertCADirectory, $logger])
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
                'queueName' => null,
            ]
        );
        $command->setQueue($queue);

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

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(['getQueue'])
            ->getMock();

        $queueFactory
            ->expects($this->any())
            ->method('getQueue')
            ->will($this->returnValue($queue));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com/'))
            ->will($this->returnValue(false));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(['setUserAgent', 'setSslVerification'])
            ->getMock();

        $client->setConfig(new Collection());

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->exactly(3))
            ->method('getClient')
            ->will($this->returnValue($client));

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $curlClient = $this->getMockBuilder(CurlClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cookiePlugin = $this
            ->getMockBuilder('Guzzle\Plugin\Cookie\CookiePlugin')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['crawl'])
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler, $curlClient, $cookiePlugin])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('crawl')
            ->will($this->throwException(new Exception()));

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('emergency');

        $userAgent = 'I am some agent';
        $curlCertCADirectory = '/usr/local/share/certs/';

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queueFactory, $indexer, $spider, $userAgent, $curlCertCADirectory, $logger])
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
                'queueName' => null,
            ]
        );

        $command
            ->setQueue($queue)
            ->crawlUrl($message);
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

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(['getQueue'])
            ->getMock();

        $queueFactory
            ->expects($this->any())
            ->method('getQueue')
            ->will($this->returnValue($queue));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com/'))
            ->will($this->returnValue(false));

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(['setUserAgent', 'setSslVerification'])
            ->getMock();

        $client->setConfig(new Collection());

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->exactly(3))
            ->method('getClient')
            ->will($this->returnValue($client));

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getRequestHandler', 'crawl'])
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->exactly(3))
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
        $curlCertCADirectory = '/usr/local/share/certs/';

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
            ->setConstructorArgs([$queueFactory, $indexer, $spider, $userAgent, $curlCertCADirectory, $logger])
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
                'queueName' => null,
            ]
        );

        $command
            ->setQueue($queue)
            ->crawlUrl($message);
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

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(['getQueue'])
            ->getMock();

        $queueFactory
            ->expects($this->any())
            ->method('getQueue')
            ->will($this->returnValue($queue));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com/'))
            ->will($this->returnValue(false));

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(['setUserAgent', 'setSslVerification'])
            ->getMock();

        $client->setConfig(new Collection());

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->exactly(3))
            ->method('getClient')
            ->will($this->returnValue($client));

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getRequestHandler', 'crawl'])
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->exactly(3))
            ->method('getRequestHandler')
            ->will($this->returnValue($requestHandler));

        $spider
            ->expects($this->once())
            ->method('crawl')
            ->will($this->throwException(new InvalidContentException()));

        $userAgent = 'I am some agent';
        $curlCertCADirectory = '/usr/local/share/certs/';

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
            ->setConstructorArgs([$queueFactory, $indexer, $spider, $userAgent, $curlCertCADirectory, $logger])
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
                'queueName' => null,
            ]
        );

        $command
            ->setQueue($queue)
            ->crawlUrl($message);
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

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(['getQueue'])
            ->getMock();

        $queueFactory
            ->expects($this->any())
            ->method('getQueue')
            ->will($this->returnValue($queue));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('http://ggg.nl/'))
            ->will($this->returnValue(false));

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(['setUserAgent', 'setSslVerification'])
            ->getMock();

        $client->setConfig(new Collection());

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->exactly(3))
            ->method('getClient')
            ->will($this->returnValue($client));

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getRequestHandler', 'crawl'])
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->exactly(3))
            ->method('getRequestHandler')
            ->will($this->returnValue($requestHandler));

        $userAgent = 'I am some agent';
        $curlCertCADirectory = '/usr/local/share/certs/';

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
            ->setConstructorArgs([$queueFactory, $indexer, $spider, $userAgent, $curlCertCADirectory, $logger])
            ->setMethods(null)
            ->getMock();

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'url' => 'http://ggg.nl',
                'base_url' => 'http://ggg.nl',
                'blacklist' => [],
                'metadata' => [
                    'core' => 'core1'
                ],
                'whitelist' => ['http://ggg.nl'],
                'queueName' => null,
            ]
        );

        $command
            ->setQueue($queue)
            ->crawlUrl($message);
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

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(['getQueue'])
            ->getMock();

        $queueFactory
            ->expects($this->any())
            ->method('getQueue')
            ->will($this->returnValue($queue));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com/'))
            ->will($this->returnValue(false));

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(['setUserAgent', 'setSslVerification'])
            ->getMock();

        $client->setConfig(new Collection());

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->exactly(3))
            ->method('getClient')
            ->will($this->returnValue($client));

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['getRequestHandler', 'crawl'])
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->exactly(3))
            ->method('getRequestHandler')
            ->will($this->returnValue($requestHandler));

        $userAgent = 'I am some agent';
        $curlCertCADirectory = '/usr/local/share/certs/';

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
            ->setConstructorArgs([$queueFactory, $indexer, $spider, $userAgent, $curlCertCADirectory, $logger])
            ->setMethods(null)
            ->getMock();

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'url' => 'https://github.com',
                'base_url' => 'https://github.com',
                'blacklist' => [],
                'metadata' => [
                    'core' => 'core1'
                ],
                'whitelist' => [],
                'queueName' => null,
            ]
        );

        $command
            ->setQueue($queue)
            ->crawlUrl($message);
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

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(['getQueue'])
            ->getMock();

        $queueFactory
            ->expects($this->any())
            ->method('getQueue')
            ->will($this->returnValue($queue));

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
        $curlCertCADirectory = '/usr/local/share/certs/';

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
            ->setConstructorArgs([$queueFactory, $indexer, $spider, $userAgent, $curlCertCADirectory, $logger])
            ->setMethods(null)
            ->getMock();

        $command->logMessage('error', 'Test', 'https://github.com', 'core');
    }

    /**
     * @test
     */
    public function isDocumentDeletedWhenUrlIsNotAllowedToCrawl()
    {
        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'url' => 'https://github.com',
                'base_url' => 'https://blaat.com',
                'blacklist' => [],
                'metadata' => ['core' => 'corename'],
                'whitelist' => [],
                'queueName' => null,
            ]
        );

        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['rejectMessage', '__destruct', 'listen'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('rejectMessage')
            ->with($message);

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(['getQueue'])
            ->getMock();

        $queueFactory
            ->expects($this->any())
            ->method('getQueue')
            ->will($this->returnValue($queue));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['deleteDocument'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('deleteDocument');

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock();

        $userAgent = 'I am some agent';
        $curlCertCADirectory = '/usr/local/share/certs/';

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['error'])
            ->getMock();

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queueFactory, $indexer, $spider, $userAgent, $curlCertCADirectory, $logger])
            ->setMethods(['markAsSkipped'])
            ->getMock();

        $command
            ->expects($this->once())
            ->method('markAsSkipped');

        $command
            ->setQueue($queue)
            ->crawlUrl($message);
    }

    /**
     * @test
     */
    public function collectionDoesNotExist()
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

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(['getQueue'])
            ->getMock();

        $queueFactory
            ->expects($this->any())
            ->method('getQueue')
            ->will($this->returnValue($queue));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexedAndNotExpired')
            ->with($this->equalTo('https://github.com/'))
            ->will($this->returnValue(false));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $client = $this
            ->getMockBuilder('Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods(['setUserAgent', 'setSslVerification'])
            ->getMock();

        $client->setConfig(new Collection());

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();

        $requestHandler
            ->expects($this->exactly(3))
            ->method('getClient')
            ->will($this->returnValue($client));

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $curlClient = $this->getMockBuilder(CurlClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cookiePlugin = $this
            ->getMockBuilder('Guzzle\Plugin\Cookie\CookiePlugin')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(['crawl'])
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler, $curlClient, $cookiePlugin])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('crawl')
            ->will($this->throwException(new PageBlacklistedException()));

        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('info');

        $userAgent = 'I am some agent';
        $curlCertCADirectory = '/usr/local/share/certs/';

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\CrawlCommand')
            ->setConstructorArgs([$queueFactory, $indexer, $spider, $userAgent, $curlCertCADirectory, $logger])
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
                'queueName' => null,
            ]
        );

        $command
            ->setQueue($queue)
            ->crawlUrl($message);
    }

    /**
     * @return array
     */
    public function redirectResponseCodeDataProvider()
    {
        return [
            [301], [302]
        ];
    }

    /**
     * @return array
     */
    public function rejectResponseCodeDataProvider()
    {
        return [
            [400], [401], [403], [500]
        ];
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
