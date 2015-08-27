<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\DocumentResolver;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentResolver;

class DocumentResolverTest extends PHPUnit_Framework_TestCase
{

    /**
     * @test
     * @dataProvider documentsProvider
     */
    public function ifDataIsReturnedFromDocuments($mimeType, $resolverType)
    {

        $response = $this
                ->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getContentType'])
                ->getMock();
        $response
                ->expects($this->once())
                ->method('getContentType')
                ->will($this->returnValue($mimeType));

        $uri = $this->getMockBuilder('VDB\Uri\Uri')
                ->disableOriginalConstructor()
                ->setMethods(['toString'])
                ->getMock();

        $resource = $this
                ->getMockBuilder('\VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getResponse', 'getUri'])
                ->getMock();
        $resource
                ->expects($this->once())
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource
                ->expects($this->any())
                ->method('getUri')
                ->will($this->returnValue($uri));

        $data = ['dummyKey' => 'dummyValue'];

        $htmlType = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Html')
                ->disableOriginalConstructor()
                ->setMethods(['getData'])
                ->getMock();
        if ($resolverType === 'html') {
            $htmlType->expects($this->once())
                    ->method('getData')
                    ->with($resource)
                    ->will($this->returnValue($data));
        }

        $pdfType = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Pdf')
                ->disableOriginalConstructor()
                ->getMock();
        if ($resolverType === 'pdf') {
            $pdfType->expects($this->once())
                    ->method('getData')
                    ->with($resource)
                    ->will($this->returnValue($data));
        }

        $msdocType = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\MsDoc')
                ->disableOriginalConstructor()
                ->getMock();
        if ($resolverType === 'msdoc') {
            $msdocType->expects($this->once())
                    ->method('getData')
                    ->with($resource)
                    ->will($this->returnValue($data));
        }

        $word2007Type = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Word2007')
                ->disableOriginalConstructor()
                ->getMock();

        $rtfType = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Rtf')
                ->disableOriginalConstructor()
                ->getMock();
        if ($resolverType === 'rtf') {
            $rtfType->expects($this->once())
                    ->method('getData')
                    ->with($resource)
                    ->will($this->returnValue($data));
        }

        $odtType = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Odt')
                ->disableOriginalConstructor()
                ->getMock();
        if ($resolverType === 'odt') {
            $odtType->expects($this->once())
                    ->method('getData')
                    ->with($resource)
                    ->will($this->returnValue($data));
        }

        $documentResolver = new DocumentResolver($htmlType, $pdfType, $msdocType, $word2007Type, $rtfType, $odtType);

        $this->assertNull($documentResolver->resolveTypeFromResource($resource));
        $this->assertEquals($data, $documentResolver->getData());
    }

    /**
     * @test
     */
    public function ifDataIsReturnedFromWord2007Document()
    {
        $response = $this
                ->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getContentType'])
                ->getMock();
        $response
                ->expects($this->once())
                ->method('getContentType')
                ->will($this->returnValue('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));

        $uri = $this->getMockBuilder('VDB\Uri\Uri')
                ->disableOriginalConstructor()
                ->setMethods(['toString'])
                ->getMock();
        $uri
                ->expects($this->any())
                ->method('toString')
                ->will($this->returnValue('dummyUri/documment.docx'));

        $resource = $this
                ->getMockBuilder('\VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getResponse', 'getUri'])
                ->getMock();
        $resource
                ->expects($this->once())
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource
                ->expects($this->any())
                ->method('getUri')
                ->will($this->returnValue($uri));

        $data = ['dummyKey' => 'dummyValue'];

        $htmlType = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Html')
                ->disableOriginalConstructor()
                ->setMethods(['getData'])
                ->getMock();

        $pdfType = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Pdf')
                ->disableOriginalConstructor()
                ->getMock();

        $msdocType = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\MsDoc')
                ->disableOriginalConstructor()
                ->getMock();

        $word2007Type = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Word2007')
                ->disableOriginalConstructor()
                ->getMock();
        $word2007Type->expects($this->once())
                ->method('getData')
                ->with($resource)
                ->will($this->returnValue($data));

        $rtfType = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Rtf')
                ->disableOriginalConstructor()
                ->getMock();

        $odtType = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Odt')
                ->disableOriginalConstructor()
                ->getMock();

        $documentResolver = new DocumentResolver($htmlType, $pdfType, $msdocType, $word2007Type, $rtfType, $odtType);

        $this->assertNull($documentResolver->resolveTypeFromResource($resource));
        $this->assertEquals($data, $documentResolver->getData());
    }

    /**
     * Returns document mimetype with corresponding
     * document resolverType
     *
     * Except for word2007 that need another logic
     *
     * @return array
     */
    public function documentsProvider()
    {
        return [
            ['text/html', 'html'],
            ['application/pdf', 'pdf'],
            ['application/octet-stream', 'pdf'],
            ['application/vnd.openxmlformats-officedocument.wordprocessingml.template', 'msdoc'],
            ['application/msword', 'msdoc'],
            ['application/rtf', 'rtf'],
            ['application/vnd.oasis.opendocument.text', 'odt'],
            ['unknown', 'html']
        ];
    }

}
