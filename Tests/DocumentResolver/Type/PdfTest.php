<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\DocumentResolver\TypePdf;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Pdf;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Date function mock for returning the same date string
 * Fixing problem with generating not the same datetime each call
 *
 * @return string
 */
function date()
{
    return '2015-06-18T23:49:41Z';
}

class PdfTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function retrieveValidDataFromPdfFile()
    {
        $response = $this->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->setMethods(['getLastModified'])
            ->getMock();
        $response->addHeader('Content-Disposition', 'filename="dummyfileFromContent.pdf"');
        $response->expects($this->once())
            ->method('getLastModified')
            ->will($this->returnValue('2015-06-18T23:49:41Z'));

        $uri = $this->getMockBuilder('VDB\Uri\Uri')
            ->disableOriginalConstructor()
            ->setMethods(['toString'])
            ->getMock();
        $uri->expects($this->exactly(2))
            ->method('toString')
            ->will($this->returnValue('http://blabdummy.de/dummydir/dummyfile.pdf'));

        $resource = $this->getMockBuilder('VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(['getUri', 'getResponse'])
            ->getMock();
        $resource->expects($this->exactly(2))
            ->method('getResponse')
            ->will($this->returnValue($response));
        $resource->expects($this->exactly(2))
            ->method('getUri')
            ->will($this->returnValue($uri));

        $type = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Pdf')
            ->setConstructorArgs(['/usr/local/sbin/pdftotext'])
            ->setMethods(['extractContentFromResource'])
            ->getMock();
        $type->expects($this->once())
            ->method('extractContentFromResource')
            ->with($resource)
            ->will($this->returnValue('TEST TEXT Content from PDF 1'));

        $data = $type->getData($resource);

        $this->assertEquals(12, count($data));
        $expectedKeys = [
            'id', 'url', 'content', 'title',
            'tstamp', 'contentLength', 'lastModified',
            'date', 'publishedDate', 'type', 'strippedContent', 'updatedDate',
        ];

        foreach ($expectedKeys as $expectedKey) {
            $this->assertArrayHasKey($expectedKey, $data);
        }

        $this->assertEquals('dummyfileFromContent.pdf', $data['title']);
        $this->assertEquals(28, strlen($data['content']));

        //not equals filename taken from url
        $this->assertNotEquals('dummyfile.pdf', $data['title']);
        $this->assertNotEmpty($data, $data['content']);
    }

    /**
     * @test
     */
    public function retrieveValidDataFromPdfFileWithTitleFromUrl()
    {
        $response = $this->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->setMethods(['getLastModified'])
            ->getMock();
        $response->expects($this->once())
            ->method('getLastModified')
            ->will($this->returnValue('2015-06-18T23:49:41Z'));

        $uri = $this->getMockBuilder('VDB\Uri\Uri')
            ->disableOriginalConstructor()
            ->setMethods(['toString'])
            ->getMock();
        $uri->expects($this->exactly(2))
            ->method('toString')
            ->will($this->returnValue('http://blabdummy.de/dummydir/dummyfile.pdf'));

        $resource = $this->getMockBuilder('VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(['getResponse', 'getUri', 'getBody'])
            ->getMock();
        $resource->expects($this->exactly(2))
            ->method('getResponse')
            ->will($this->returnValue($response));
        $resource->expects($this->exactly(2))
            ->method('getUri')
            ->will($this->returnValue($uri));

        $type = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Pdf')
            ->setConstructorArgs(['/usr/local/sbin/pdftotext'])
            ->setMethods(['extractContentFromResource'])
            ->getMock();
        $type->expects($this->once())
            ->method('extractContentFromResource')
            ->with($resource)
            ->will($this->returnValue('TEST TEXT Content from PDF 22'));

        $data = $type->getData($resource);

        $this->assertEquals(12, count($data));
        $expectedKeys = [
            'id', 'url', 'content', 'title',
            'tstamp', 'contentLength', 'lastModified',
            'date', 'publishedDate', 'type', 'strippedContent',
        ];

        foreach ($expectedKeys as $expectedKey) {
            $this->assertArrayHasKey($expectedKey, $data);
        }

        $this->assertEquals('dummyfile.pdf', $data['title']);
        $this->assertEquals(29, strlen($data['content']));

        //filename taken from url, because no filename in content
        $this->assertNotEquals('dummyfileFromContent.pdf', $data['title']);
        $this->assertNotEmpty($data, $data['content']);
    }

    /**
     * @test
     * @expectedException \Simgroep\ConcurrentSpiderBundle\InvalidContentException
     * @expectedExceptionMessage PDF didn't contain enough content (minimal chars is 3)
     */
    public function throwExceptionOnLessThenMinimalContentLength()
    {
        $resource = $this->getMockBuilder('VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(['getResponse'])
            ->getMock();

        $type = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Pdf')
            ->setConstructorArgs(['/usr/local/sbin/pdftotext'])
            ->setMethods(['extractContentFromResource'])
            ->getMock();
        $type->expects($this->once())
            ->method('extractContentFromResource')
            ->with($resource)
            ->will($this->returnValue('te'));

        //exception is thrown there !
        $type->getData($resource);

    }

}
