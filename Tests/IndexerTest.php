<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\Indexer;
use DateTime;

class IndexerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     * @testdox Tests if the given url is checked upon a checksum in Solr.
     */
    public function ifUrlIsSha1Checksum()
    {
        $url = 'https://github.com';

        $solrQuery = $this->getMockBuilder('Solarium\QueryType\Select\Query\Query')
            ->disableOriginalConstructor()
            ->setMethods(['setQuery'])
            ->getMock();

        $expiresBeforeDate = new DateTime();
        $expiresBeforeDate->modify('-8 hour');

        $queryPhrase = sprintf(
            "id:%s AND updatedDate:[%s TO NOW]",
            sha1($url),
            $expiresBeforeDate->format('Y-m-d\TH:i:s\Z')
        );

        $solrQuery->expects($this->once())
            ->method('setQuery')
            ->with($this->equalTo($queryPhrase));

        $solrResult = $this->getMockBuilder('Solarium\Core\Query\Result\Result')
            ->disableOriginalConstructor()
            ->setMethods(['getNumFound'])
            ->getMock();

        $solrResult->expects($this->once())
            ->method('getNumFound')
            ->will($this->returnValue(1));

        $solrClient = $this->getMockBuilder('Solarium\Client')
            ->setConstructorArgs([])
            ->setMethods(['createSelect', 'select'])
            ->getMock();

        $solrClient->expects($this->once())
            ->method('createSelect')
            ->will($this->returnValue($solrQuery));

        $solrClient->expects($this->once())
            ->method('select')
            ->will($this->returnValue($solrResult));

        $indexer = new Indexer($solrClient, []);
        $actual = $indexer->isUrlIndexedAndNotExpired($url, ['core' => 'coreName']);

        $this->assertTrue($actual);
    }

    /**
     * @test
     * @testdox Tests if every 10 documents the index saves them.
     */
    public function ifEveryTenDocumentsAreSaved()
    {
        $solrQuery = $this->getMockBuilder('Solarium\QueryType\Update\Query\Query')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $solrClient = $this
            ->getMockBuilder('Solarium\Client')
            ->setMethods(['createUpdate', 'update'])
            ->getMock();
        $solrClient->expects($this->any())
            ->method('createUpdate')
            ->will($this->returnValue($solrQuery));

        $mapping = [
            'id' =>'id',
            'groups' =>
                [
                    'SIM' => ['dummyKey1' => 'dummyKeySolr1'],
                    'DCTERMS' => ['dummyKey2' => 'dummyKeySolr2']
                ]
        ];

        $indexer = new Indexer($solrClient, $mapping);

        for ($i = 0; $i <= 9; $i++) {
            $body = json_encode(
                [
                    'document' => [
                        'id' => rand(0, 10),
                        'title' => sha1(rand(0, 10)),
                        'tstamp' => date('Y-m-d\TH:i:s\Z'),
                        'date' => date('Y-m-d\TH:i:s\Z'),
                        'publishedDate' => date('Y-m-d\TH:i:s\Z'),
                        'content' => str_repeat(sha1(rand(0, 10)), 5),
                        'url' => 'https://www.github.com',
                        'SIM.dummyKey1' => 'dummyvalue1',
                        'DCTERMS.dummyKey2' => 'dummyvalue2'
                    ],
                    'metadata' => ['core' => 'core1']
                ]
            );

            $message = new AMQPMessage($body);
            $indexer->prepareDocument($message);
        }
    }

    /**
     * @test
     * @testdox Test if docuemnt are deleted from solr
     */
    public function ifDocumentAreDeleted()
    {
        $url = 'https://www.github.com';

        $solrQuery = $this->getMockBuilder('Solarium\QueryType\Update\Query\Query')
            ->disableOriginalConstructor()
            ->setMethods(['addDeleteById', 'addCommit'])
            ->getMock();
        $solrQuery->expects($this->once())
            ->method('addDeleteById')
            ->with(sha1($url));

        $solrClient = $this
            ->getMockBuilder('Solarium\Client')
            ->setMethods(['createUpdate', 'update'])
            ->getMock();
        $solrClient->expects($this->any())
            ->method('createUpdate')
            ->will($this->returnValue($solrQuery));

        $mapping = [];

        $indexer = new Indexer($solrClient, $mapping);

        $bodyCrawlJob = json_encode(
            [
                'url' => $url,
                'metadata' => ['core' => 'core2']
            ]
        );

        $message = new AMQPMessage($bodyCrawlJob);
        $indexer->deleteDocument($message);
    }
}
