<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;
use PhpAmqpLib\Message\AMQPMessage;

class CrawlJobTest extends PHPUnit_Framework_TestCase
{
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
        $crawlJob = new CrawlJob('', 'http://dummy.nl/', [], [], []);
        $this->assertSame('http://dummy.nl/', $crawlJob->getBaseUrl());
    }

    /**
     * @test
     * @testdox Tests if get url returns correct value.
     */
    public function ifGetUrlReturnCorrectValue()
    {
        $crawlJob = new CrawlJob('http://dummy.nl/', '', [], [], []);
        $this->assertSame('http://dummy.nl/', $crawlJob->getUrl());
    }
}
