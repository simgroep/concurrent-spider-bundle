<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use Simgroep\ConcurrentSpiderBundle\QueueFactory;

class QueueFactoryTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $queueUrls;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $queueDocuments;

    public function setUp()
    {
        $this->queueUrls = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['urls', '__destruct', 'listen', 'rejectMessage'])
            ->getMock();

        $this->queueDocuments = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['documents', '__destruct', 'listen', 'rejectMessage'])
            ->getMock();
    }

    public function testGetQueueUrls()
    {
        $queueFactory = new QueueFactory($this->queueUrls, $this->queueDocuments);

        $this->assertEquals(
            $this->queueUrls,
            $queueFactory->getQueue(QueueFactory::QUEUE_URLS)
        );
    }

    public function testGetQueueDocuments()
    {
        $queueFactory = new QueueFactory($this->queueUrls, $this->queueDocuments);

        $this->assertEquals(
            $this->queueDocuments,
            $queueFactory->getQueue(QueueFactory::QUEUE_DOCUMENTS)
        );
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Unknown queue!
     */
    public function testGetInvalidQueue()
    {
        $queueFactory = new QueueFactory($this->queueUrls, $this->queueDocuments);

        $queueFactory->getQueue('some unknown queue');
    }
}
