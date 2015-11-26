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

        $solrQuery
            ->expects($this->once())
            ->method('setQuery');

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

        $indexer = new Indexer($solrClient, [], 50);
        $actual = $indexer->isUrlIndexedAndNotExpired($url, ['core' => 'coreName']);

        $this->assertTrue($actual);
    }

    /**
     * @test
     * @testdox Tests if every 10 documents the index saves them.
     */
    public function ifEveryFiftyDocumentsAreSaved()
    {
        $solrQuery = $this
            ->getMockBuilder('Solarium\QueryType\Update\Query\Query')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $solrClient = $this
            ->getMockBuilder('Solarium\Client')
            ->setMethods(['createUpdate', 'update'])
            ->getMock();

        $solrClient
            ->expects($this->any())
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

        $indexer = new Indexer($solrClient, $mapping, 50);

        for ($i = 0; $i <= 49; $i++) {
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

        $indexer = new Indexer($solrClient, $mapping, 50);

        $bodyCrawlJob = json_encode(
            [
                'url' => $url,
                'metadata' => ['core' => 'core2']
            ]
        );

        $message = new AMQPMessage($bodyCrawlJob);
        $indexer->deleteDocument($message);
    }

    /**
     * @test
     * @testdox Tests the structure of the query phrase to see if it's looking for a date range.
     */
    public function isQueryPhraseADateRangeWhenSearchingForExpiredUrls()
    {
        $selectQuery = $this
            ->getMockBuilder('Solarium\QueryType\Select\Query\Query')
            ->setMethods(['setQuery'])
            ->getMock();

        $selectQuery
            ->expects($this->once())
            ->method('setQuery')
            ->with($this->callback(function($subject) {
                return preg_match('/^revisit_expiration:\[\* TO .*\]$/', $subject);
            }))
            ->will($this->returnValue($selectQuery));

        $solrClient = $this
            ->getMockBuilder('Solarium\Client')
            ->setMethods(['createSelect', 'select'])
            ->getMock();

        $solrClient
            ->expects($this->once())
            ->method('createSelect')
            ->will($this->returnValue($selectQuery));

        $indexer = new Indexer($solrClient, [], 50);
        $indexer->findExpiredUrls('test');
    }

    /**
     * @test
     */
    public function isNullReturnedWhenNoDocumentFoundByUrl()
    {
        $url = 'https://github.com';

        $selectQuery = $this
            ->getMockBuilder('Solarium\QueryType\Select\Query\Query')
            ->setMethods(['setQuery'])
            ->getMock();

        $selectQuery
            ->expects($this->once())
            ->method('setQuery')
            ->with($this->callback(function($subject) use ($url) {
                return preg_match(sprintf('/^id:%s$/', sha1($url)), $subject);
            }))
            ->will($this->returnValue($selectQuery));

        $result = $this
            ->getMockBuilder('Solarium\QueryType\Select\Result\Result')
            ->disableOriginalConstructor()
            ->getMock();

        $solrClient = $this
            ->getMockBuilder('Solarium\Client')
            ->setMethods(['createSelect', 'select'])
            ->getMock();

        $solrClient
            ->expects($this->once())
            ->method('createSelect')
            ->will($this->returnValue($selectQuery));

        $solrClient
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($result));

        $indexer = new Indexer($solrClient, [], 50);
        $this->assertNull($indexer->findDocumentByUrl($url));
    }

    /**
     * @test
     */
    public function isCorrectQueryPhraseUsedForAmountDocuments()
    {
        $selectQuery = $this
            ->getMockBuilder('Solarium\QueryType\Select\Query\Query')
            ->setMethods(['setQuery'])
            ->getMock();

        $selectQuery
            ->expects($this->once())
            ->method('setQuery')
            ->with($this->equalTo('*:*'))
            ->will($this->returnValue($selectQuery));

        $result = $this
            ->getMockBuilder('Solarium\QueryType\Select\Result\Result')
            ->disableOriginalConstructor()
            ->getMock();

        $solrClient = $this
            ->getMockBuilder('Solarium\Client')
            ->setMethods(['createSelect', 'select'])
            ->getMock();

        $solrClient
            ->expects($this->once())
            ->method('createSelect')
            ->will($this->returnValue($selectQuery));

        $solrClient
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($result));

        $indexer = new Indexer($solrClient, [], 50);
        $indexer->getAmountDocumentsInCore('core');

    }
}
