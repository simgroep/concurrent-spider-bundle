<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PHPUnit_Framework_TestCase;

class IndexerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @testdox Tests if the given url is checked upon a checksum in Solr.
     */
    public function testIfUrlIsSha1Checksum()
    {
        $url = 'https://github.com';

        $solrQuery = $this
            ->getMockBuilder('Solarium_Query_Select')
            ->disableOriginalConstructor()
            ->setMethods(array('setQuery'))
            ->getMock();

        $solrQuery
            ->expects($this->once())
            ->method('setQuery')
            ->with($this->equalTo(sprintf("id:%s", sha1($url))));

        $solrResult = $this
            ->getMockBuilder('Solarium_Result')
            ->disableOriginalConstructor()
            ->setMethods(array('getNumFound'))
            ->getMock();

        $solrResult
            ->expects($this->once())
            ->method('getNumFound')
            ->will($this->returnValue(1));

        $solrClient = $this
            ->getMockBuilder('Solarium_Client')
            ->disableOriginalConstructor()
            ->setMethods(array('createSelect', 'select'))
            ->getMock();

        $solrClient
            ->expects($this->once())
            ->method('createSelect')
            ->will($this->returnValue($solrQuery));

        $solrClient
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($solrResult));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->setConstructorArgs(array($solrClient))
            ->setMethods(null)
            ->getMock();

        $actual = $indexer->isUrlIndexed($url);

        $this->assertTrue($actual);
    }
}
