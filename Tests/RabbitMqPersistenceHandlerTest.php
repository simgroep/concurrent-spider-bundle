<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PHPUnit_Framework_TestCase;

class RabbitMqPersistenceHandlerTest extends PHPUnit_Framework_TestCase
{
    public function testIfCanHandleApplicationPdfContentType()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(array('publish', '__destruct'))
            ->getMock();

        $pdfParser = $this
            ->getMockBuilder('Smalot\PdfParser\Parser')
            ->disableOriginalConstructor()
            ->setMethods(array())
            ->getMock();

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\RabbitMqPersistenceHandler')
            ->setConstructorArgs(array($queue, $pdfParser))
            ->setMethods(array('getDataFromPdfFile'))
            ->getMock();

        $persistenceHandler
            ->expects($this->once())
            ->method('getDataFromPdfFile')
            ->will($this->returnValue(array()));

        $response = $this
            ->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->setMethods(array('getContentType'))
            ->getMock();

        $response
            ->expects($this->once())
            ->method('getContentType')
            ->will($this->returnValue('application/pdf'));

        $resource = $this
            ->getMockBuilder('\VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(array('getResponse'))
            ->getMock();

        $resource
            ->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue($response));

        $persistenceHandler->persist($resource);
    }

    public function testIfCanHandleTextHtmlContentType()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(array('publish', '__destruct'))
            ->getMock();

        $pdfParser = $this
            ->getMockBuilder('Smalot\PdfParser\Parser')
            ->disableOriginalConstructor()
            ->setMethods(array())
            ->getMock();

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\RabbitMqPersistenceHandler')
            ->setConstructorArgs(array($queue, $pdfParser))
            ->setMethods(array('getDataFromWebPage'))
            ->getMock();

        $persistenceHandler
            ->expects($this->once())
            ->method('getDataFromWebPage')
            ->will($this->returnValue(array()));

        $response = $this
            ->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->setMethods(array('getContentType'))
            ->getMock();

        $response
            ->expects($this->once())
            ->method('getContentType')
            ->will($this->returnValue('text/html'));

        $resource = $this
            ->getMockBuilder('\VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(array('getResponse'))
            ->getMock();

        $resource
            ->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue($response));

        $persistenceHandler->persist($resource);
    }
}
