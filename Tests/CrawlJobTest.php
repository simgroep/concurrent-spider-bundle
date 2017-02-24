<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;
use PhpAmqpLib\Message\AMQPMessage;

class CrawlJobTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \Simgroep\ConcurrentSpiderBundle\CrawlJob
     */
    protected $crawlJob;

    public function setUp()
    {
        $this->crawlJob = new CrawlJob(
            'http://dummy.nl/dummy.html',
            'http://dummy.nl/',
            ['http:/black.site/'],
            ['core' => 'core1'],
            ['http://white.power/'],
            'dummyqueue'
        );
    }

    /**
     * @test
     * @testdox Tests if factory return correct values.
     */
    public function ifFactoryReturnCorrectValues()
    {
        $data = [
            'url' => 'http://dummy.nl/dummy.html',
            'base_url' => 'http://dummy.nl/',
            'blacklist' => [],
            'metadata' => ['core' => 'core2'],
            'whitelist' => [],
            'queueName' => null
        ];
        $bodyCrawlJob = json_encode($data);

        $message = new AMQPMessage($bodyCrawlJob);
        $crawlJob = CrawlJob::create($message);
        $this->assertSame($data, $crawlJob->toArray());
    }

    /**
     * @test
     * @testdox Tests if get base url returns correct value.
     */
    public function ifGetBaseUrlReturnCorrectValue()
    {
        $this->assertSame('http://dummy.nl/', $this->crawlJob->getBaseUrl());
    }

    /**
     * @test
     * @testdox Tests if get url returns correct value.
     */
    public function ifGetUrlReturnCorrectValue()
    {
        $this->assertSame('http://dummy.nl/dummy.html', $this->crawlJob->getUrl());
    }

    /**
     * @test
     * @testdox Tests if get blacklist returns correct value.
     */
    public function ifGetBlacklistReturnCorrectValue()
    {
        $this->assertSame(['http:/black.site/'], $this->crawlJob->getBlacklist());
    }

    /**
     * @test
     * @testdox Tests if get metadata returns correct value.
     */
    public function ifGetMetadataReturnCorrectValue()
    {
        $this->assertSame(['core' => 'core1'], $this->crawlJob->getMetadata());
    }

    /**
     * @test
     * @testdox Tests if get whitelist returns correct value.
     */
    public function ifGetWhitelistReturnCorrectValue()
    {
        $this->assertSame(['http://white.power/'], $this->crawlJob->getWhitelist());
    }

    /**
     * @test
     * @testdox Tests if get url returns correct value.
     */
    public function ifGetQueueNameReturnCorrectValue()
    {
        $this->assertSame('dummyqueue', $this->crawlJob->getQueueName());
    }
}
