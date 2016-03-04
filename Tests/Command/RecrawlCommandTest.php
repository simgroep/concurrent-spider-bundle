<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\Command;

use Exception;
use MyProject\Proxies\__CG__\OtherProject\Proxies\__CG__\stdClass;
use PHPUnit_Framework_TestCase;
use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use VDB\Uri\Exception\UriSyntaxException;

class RecrawlCommandTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function recrawlWithEmptyBlacklist()
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
            ->setMethods(null)
            ->getMock();


        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\RecrawlCommand')
            ->setConstructorArgs([$queue, $indexer, $logger])
            ->setMethods(null)
            ->getMock();

        $input = new StringInput('');
        $output = new NullOutput();
        $command->run($input, $output);

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'url' => null,
                'base_url' => null,
                'blacklist' => [],
                'metadata' => ['core' => 'corename'],
                'whitelist' => [],
            ]
        );

       $this->assertNull($command->recrawl($message));
    }

    /**
     * @test
     */
    public function recrawlWithBlacklist()
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
            ->setMethods(['getDocumentUrlsInCore', 'deleteDocumentById'])
            ->getMock();

        $o1indexerResult = new \stdClass();
        $o1indexerResult->url = "http://onet.pl";
        $o1indexerResult->id = sha1($o1indexerResult->url);
        $o2indexerResult = new \stdClass();
        $o2indexerResult->url = "https://interia.pl";
        $o2indexerResult->id = sha1($o2indexerResult->url);
        $indexer_result = [$o1indexerResult, $o2indexerResult];

        $indexer
            ->expects($this->once())
            ->method('getDocumentUrlsInCore')
            ->with(['core' => 'corename'])
            ->will($this->returnValue($indexer_result));

        $indexer
            ->expects($this->at(1))
            ->method('deleteDocumentById');


        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\RecrawlCommand')
            ->setConstructorArgs([$queue, $indexer, $logger])
            ->setMethods(null)
            ->getMock();

        $input = new StringInput('');
        $output = new NullOutput();
        $command->run($input, $output);

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'url' => null,
                'base_url' => null,
                'blacklist' => ["^https\:"],
                'metadata' => ['core' => 'corename'],
                'whitelist' => [],
            ]
        );

        $this->assertNull($command->recrawl($message));
    }

    /**
     * @test
     */
    public function recrawlWithException()
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
            ->setMethods(['getDocumentUrlsInCore'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('getDocumentUrlsInCore')
            ->with(['core' => 'corename'])
            ->will($this->returnValue(null));


        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info', 'warning', 'emergency'])
            ->getMock();

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\RecrawlCommand')
            ->setConstructorArgs([$queue, $indexer, $logger])
            ->setMethods(null)
            ->getMock();

        $input = new StringInput('');
        $output = new NullOutput();
        $command->run($input, $output);

        $message = new AMQPMessage();
        $message->body = json_encode(
            [
                'url' => null,
                'base_url' => null,
                'blacklist' => ["^https\:"],
                'metadata' => ['core' => 'corename'],
                'whitelist' => [],
            ]
        );

        $this->assertNull($command->recrawl($message));
    }

}

