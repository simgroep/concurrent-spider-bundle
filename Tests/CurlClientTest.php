<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use Simgroep\ConcurrentSpiderBundle\CurlClient;
use VDB\Uri\Uri;

class CurlClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    public $userAgent = 'some user agent';

    /**
     * @var string
     */
    public $curlCertCADirectory = '/usr/bin/cert';

    public $url = 'https://github.com/test';

    public function testIfDocumentFound()
    {
        $curlClient = $this->getMockBuilder(CurlClient::class)
            ->setConstructorArgs([$this->userAgent, $this->curlCertCADirectory])
            ->setMethods(['getContentType'])
            ->getMock();

        $curlClient->initClient();

        $curlClient
            ->expects($this->once())
            ->method('getContentType')
            ->will($this->returnValue('application/vnd.oasis.opendocument.text;'));

        $uri = new Uri($this->url);

        $curlClient->isDocument($uri);
    }

    public function testIfLinkFound()
    {
        $curlClient = $this->getMockBuilder(CurlClient::class)
            ->setConstructorArgs([$this->userAgent, $this->curlCertCADirectory])
            ->setMethods(['getContentType'])
            ->getMock();

        $curlClient->initClient();

        $curlClient
            ->expects($this->once())
            ->method('getContentType')
            ->will($this->returnValue('text/html;'));

        $uri = new Uri($this->url);

        $curlClient->isDocument($uri);
    }

    public function test301redirect()
    {
        $this->setExpectedException('Exception', sprintf('Page moved to %', $this->url));

        $curlClient = $this->getMockBuilder(CurlClient::class)
            ->setConstructorArgs([$this->userAgent, $this->curlCertCADirectory])
            ->setMethods(['getStatusCode', 'getRedirectUrl'])
            ->getMock();

        $curlClient
            ->expects($this->once())
            ->method('getStatusCode')
            ->will($this->returnValue(301));

        $curlClient
            ->expects($this->once())
            ->method('getRedirectUrl')
            ->will($this->returnValue($this->url));

        $curlClient->initClient();

        $uri = new Uri($this->url);

        $curlClient->isDocument($uri);
    }
}
