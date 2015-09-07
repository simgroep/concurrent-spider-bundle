<?php

namespace Simgroep\ConcurrentSpiderBundle\PersistenceHandler;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;

class RabbitMqPersistenceHandlerTest extends PHPUnit_Framework_TestCase
{

    /**
     * @test
     * @testdox Tests the method flow when resolving the response from a job
     */
    public function persist()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['publish', '__destruct'])
            ->getMock();

        $resource = $this
            ->getMockBuilder('\VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(array('getResponse'))
            ->getMock();

        $response = $this
            ->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->getMock();

        $resource
            ->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue($response));

        $documentResolver = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentResolver')
            ->disableOriginalConstructor()
            ->setMethods(['resolveTypeFromResource', 'getData'])
            ->getMock();

        $documentResolver
            ->expects($this->once())
            ->method('resolveTypeFromResource')
            ->with($resource);

        $documentResolver
            ->expects($this->once())
            ->method('getData')
            ->will($this->returnValue(array(1)));

        $persistenceHandler = new RabbitMqPersistenceHandler($queue, $documentResolver, '8MB');
        $crawlJob = new CrawlJob('https://github.com', 'https://github.com');
        $this->assertNull($persistenceHandler->persist($resource, $crawlJob));
    }

    /**
     * @test
     * @testdox Tests if an exception is thrown if response size exceeds limits
     * @expectedException \Simgroep\ConcurrentSpiderBundle\InvalidContentException
     * @expectedExceptionMessage Resource size exceeds limits (1024 bytes)
     */
    public function isExceptionThrownWhenSizeExeedsLimit()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['publish', '__destruct'])
            ->getMock();

        $response = $this
            ->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->setMethods(array('getBody'))
            ->getMock();

        $response
            ->expects($this->once())
            ->method('getBody')
            ->will($this->returnValue(str_repeat('A', '1025')));

        $resource = $this
            ->getMockBuilder('\VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(array('getResponse'))
            ->getMock();

        $resource
            ->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue($response));

        $documentResolver = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentResolver')
            ->disableOriginalConstructor()
            ->getMock();

        $persistenceHandler = new RabbitMqPersistenceHandler($queue, $documentResolver, '1KB');

        $crawlJob = new CrawlJob('https://github.com', 'https://github.com');
        $persistenceHandler->persist($resource, $crawlJob);
    }

    /**
     * @test
     * @testdox Tests if the amounts of bytes returned by RabbitMqPersistenceHandler::convertToBytes is correct
     * @dataProvider fileSizesProvider
     */
    public function isAmountOfBytesCalculatedCorrect($expectedInBytes, $humanReadableFormat)
    {
        $amountOfBytes = RabbitMqPersistenceHandler::convertToBytes($humanReadableFormat);

        $this->assertEquals($expectedInBytes, $amountOfBytes);
    }

    public function fileSizesProvider()
    {
        return array(
            array(1024, 1024),
            array(1024, '1kb'),
            array(1024 * 1024, '1mb'),
            array(1024 * 1024 * 1024, '1gb'),
            array(1024 * 1024 * 1024 * 1024, '1tb'),
            array(1024 * 1024 * 1024 * 1024 * 1024, '1pb'),
        );
    }
}
