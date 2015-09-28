<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\DocumentResolver\TypeMsDoc;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\MsDoc;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Mock version of json_encode
 *
 * Used for testing an error situation.
 *
 * @param mixed $value
 * @return bool|string
 */
function json_encode($value)
{
    if ($value === 'return-false') {
        return false;
    }
    return \json_encode($value);
}

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

class MsDocTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     * @expectedException \Simgroep\ConcurrentSpiderBundle\InvalidContentException
     * @expectedExceptionMessage MsDoc didn't contain enough content (minimal chars is 3)
     */
    public function throwExceptionOnLessThenMinimalContentLength()
    {
        $resource = $this
                ->getMockBuilder('VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->getMock();

        $type = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\MsDoc')
                ->setMethods(['extractContentFromResource'])
                ->getMock();
        $type->expects($this->once())
                ->method('extractContentFromResource')
                ->with($resource)
                ->will($this->returnValue(''));

        $type->getData($resource);
    }

    /**
     * @test
     */
    public function retrieveValidDataFromPdfFile()
    {
        $response = $this->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getBody', 'getLastModified'])
                ->getMock();
        $response->expects($this->once())
                ->method('getLastModified')
                ->will($this->returnValue('2015-06-18T23:49:41Z'));
        $response->expects($this->once())
                ->method('getBody')
                ->will($this->returnValue(file_get_contents(__DIR__ . '/../../Mock/Documents/sample.doc')));

        $uri = $this->getMockBuilder('VDB\Uri\Uri')
                ->disableOriginalConstructor()
                ->setMethods(['toString'])
                ->getMock();
        $uri->expects($this->exactly(2))
                ->method('toString')
                ->will($this->returnValue('http://blabdummy.de/dummydir/sample.doc'));

        $crawler = new Crawler('', 'http://blabdummy.de/dummydir/sample.doc');

        $resource = $this
                ->getMockBuilder('VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getResponse', 'getUri', 'getBody'])
                ->getMock();
        $resource->expects($this->exactly(2))
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource->expects($this->exactly(2))
                ->method('getUri')
                ->will($this->returnValue($uri));

        $type = new MsDoc();
        $data = $type->getData($resource);

        $this->assertEquals(10, count($data));
        $expectedKeys = ['id', 'url', 'content', 'title', 'tstamp', 'contentLength', 'lastModified', 'date', 'publishedDate', 'updatedDate'];
        foreach ($expectedKeys as $expectedKey) {
            $this->assertArrayHasKey($expectedKey, $data);
        }

        $this->assertEquals('sample.doc', $data['title']);
        $this->assertNotEmpty($data, $data['content']);
    }

}
