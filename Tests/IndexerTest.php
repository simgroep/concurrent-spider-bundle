<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\Indexer;

class IndexerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @testdox Tests if the given url is checked upon a checksum in Solr.
     */
    public function testIfUrlIsSha1Checksum()
    {
        $url = 'https://github.com';

        $solrQuery = $this->getMockBuilder('Solarium_Query_Select')
            ->disableOriginalConstructor()
            ->setMethods(['setQuery'])
            ->getMock();

        $solrQuery->expects($this->once())
            ->method('setQuery')
            ->with($this->equalTo(sprintf("id:%s", sha1($url))));

        $solrResult = $this->getMockBuilder('Solarium_Result')
            ->disableOriginalConstructor()
            ->setMethods(['getNumFound'])
            ->getMock();

        $solrResult->expects($this->once())
            ->method('getNumFound')
            ->will($this->returnValue(1));

        $solrClient = $this->getMockBuilder('Solarium_Client')
            ->disableOriginalConstructor()
            ->setMethods(['createSelect', 'select'])
            ->getMock();

        $solrClient->expects($this->once())
            ->method('createSelect')
            ->will($this->returnValue($solrQuery));

        $solrClient->expects($this->once())
            ->method('select')
            ->will($this->returnValue($solrResult));

        $indexer = new Indexer($solrClient);
        $actual = $indexer->isUrlIndexed($url);

        $this->assertTrue($actual);
    }

    public function testAddDocuments()
    {
        $documents = ['doc1', 'doc2', 'doc3'];

        $solrQuery = $this->getMockBuilder('Solarium_Query_Select')
            ->disableOriginalConstructor()
            ->setMethods(['addDocuments', 'addCommit'])
            ->getMock();
        $solrQuery->expects($this->once())
            ->method('addDocuments')
            ->with($this->equalTo($documents));

        $solrClient = $this->getMockBuilder('Solarium_Client')
            ->disableOriginalConstructor()
            ->setMethods(['createUpdate', 'update'])
            ->getMock();
        $solrClient->expects($this->once())
            ->method('createUpdate')
            ->will($this->returnValue($solrQuery));

        $indexer = new Indexer($solrClient);
        $this->assertNull($indexer->addDocuments($documents));
    }


}
