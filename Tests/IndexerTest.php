<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit_Framework_TestCase;
use DateTime;
use Solarium\Core\Query\Result\Result;
use VDB\Uri\Uri;
use Simgroep\ConcurrentSpiderBundle\Indexer;
use Simgroep\ConcurrentSpiderBundle\UrlCheck;

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
            'id' => 'id',
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
            ->with(sha1(strtolower($url)));

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
            ->with($this->callback(function ($subject) {
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
            ->with($this->callback(function ($subject) use ($url) {
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

    /**
     * @test
     */
    public function isCorrectQueryPhraseUsedForDeletingAllDocuments()
    {
        $updateQuery = $this
            ->getMockBuilder('Solarium\QueryType\Update\Query\Query')
            ->setMethods(['addDeleteQuery', 'addCommit'])
            ->getMock();

        $updateQuery
            ->expects($this->once())
            ->method('addDeleteQuery')
            ->with($this->equalTo('*:*'))
            ->will($this->returnValue($updateQuery));

        $result = $this
            ->getMockBuilder('Solarium\QueryType\Select\Result\Result')
            ->disableOriginalConstructor()
            ->getMock();

        $solrClient = $this
            ->getMockBuilder('Solarium\Client')
            ->setMethods(['createUpdate', 'update'])
            ->getMock();

        $solrClient
            ->expects($this->once())
            ->method('createUpdate')
            ->will($this->returnValue($updateQuery));

        $solrClient
            ->expects($this->once())
            ->method('update')
            ->will($this->returnValue($result));

        $indexer = new Indexer($solrClient, [], 50);
        $indexer->emptyCore('core');
    }

    /**
     * @test
     */
    public function isCorrectQueryPhraseUsedForGetDocumentsUrlsInCore()
    {
        $selectQuery = $this
            ->getMockBuilder('Solarium\QueryType\Select\Query\Query')
            ->setMethods(['setQuery', 'setFields'])
            ->getMock();

        $selectQuery
            ->expects($this->once())
            ->method('setQuery')
            ->with($this->equalTo('*:*'))
            ->will($this->returnValue($selectQuery));

        $selectQuery
            ->expects($this->once())
            ->method('setFields')
            ->with($this->equalTo(['url', 'id']))
            ->will($this->returnValue($selectQuery));

        $prefetch = $this
            ->getMockBuilder('Solarium\Plugin\PrefetchIterator')
            ->getMock();

        $solrClient = $this
            ->getMockBuilder('Solarium\Client')
            ->setMethods(['createSelect', 'getPlugin'])
            ->getMock();

        $solrClient
            ->expects($this->once())
            ->method('createSelect')
            ->will($this->returnValue($selectQuery));

        $solrClient
            ->expects($this->once())
            ->method('getPlugin')
            ->will($this->returnValue($prefetch));

        $indexer = new Indexer($solrClient, [], 50);
        $indexer->getDocumentUrlsInCore(['core' => 'core']);
    }

    /**
     * @test
     * @testdox Tests if the given url is not indexed in solr.
     */
    public function ifUrlIsNotIndexedReturnTrue()
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
            ->will($this->returnValue(0));

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
        $actual = $indexer->isUrlNotIndexedOrIndexedAndExpired($url, ['core' => 'coreName']);

        $this->assertTrue($actual);
    }

    public function testGetHashSolarId()
    {
        $url = 'https://github.com';
        $hashIdUrl = sha1(strtolower(UrlCheck::fixUrl($url)));

        $uriObj = new Uri('https://github.com');
        $hashIdUri = sha1(strtolower(UrlCheck::fixUrl($uriObj->toString())));

        $solrClient = $this->getMockBuilder('Solarium\Client')
            ->setConstructorArgs([])
            ->setMethods(['createSelect', 'select'])
            ->getMock();

        $indexer = new Indexer($solrClient, [], 50);

        $this->assertEquals($indexer->getHashSolarId($url), $hashIdUrl);
        $this->assertEquals($indexer->getHashSolarId($uriObj), $hashIdUri);
        $this->assertEquals($hashIdUrl, $hashIdUri);
    }

    public function storedAndNotExpiredDataProvider()
    {
        $url1 = 'http://www.simgroep.nl/internet/medewerkers_41499/';
        $url2 = 'http://www.simgroep.nl/internet/nieuws-uit-de-branche_41509/';
        $url3 = 'http://www.simgroep.nl/internet/portfolio_41515/search/';
        $url4 = 'http://www.simgroep.nl/internet/vacatures_41521';

        return [
            [
                [
                    new Uri($url1),
                    new Uri($url2),
                    new Uri($url3),
                    new Uri($url4)
                ],
                [
                    sha1(strtolower(UrlCheck::fixUrl($url1))),
                    sha1(strtolower(UrlCheck::fixUrl($url2))),
                    sha1(strtolower(UrlCheck::fixUrl($url3))),
                    sha1(strtolower(UrlCheck::fixUrl($url4)))
                ]
            ]
        ];
    }

    /**
     * @dataProvider storedAndNotExpiredDataProvider
     */
    public function testGetUnstoredOrExpiredUris($uris, $storedIds)
    {
        $urlUnstored = [new Uri('https://github.com')];

        $solrClient = $this->getMockBuilder('Solarium\Client')
            ->setConstructorArgs([])
            ->getMock();

        $indexer = new Indexer($solrClient, [], 50);

        $result = $indexer->getUnstoredOrExpiredUris($urlUnstored, $storedIds);
        $this->assertArrayHasKey(sha1(strtolower(UrlCheck::fixUrl($urlUnstored[0]))), $result);

        $result = $indexer->getUnstoredOrExpiredUris($uris, $storedIds);
        $this->assertEmpty($result);
    }

    public function urisNotUniqueDataProvider()
    {
        return [
            [
                [
                    new Uri('http://www.simgroep.nl/internet/medewerkers_41499/'),
                    new Uri('http://www.simgroep.nl/internet/medewerkers_41499'),
                    new Uri('http://www.simgroep.nl/internet/vacatures_41521/'),
                    new Uri('http://www.simgroep.nl/internet/vacatures_41521')
                ]
            ]
        ];
    }

    /**
     * @dataProvider urisNotUniqueDataProvider
     */
    public function testGetUniqueHashIds($uris)
    {
        $solrClient = $this->getMockBuilder('Solarium\Client')
            ->setConstructorArgs([])
            ->getMock();

        $indexer = new Indexer($solrClient, [], 50);

        $result = $indexer->getUniqueHashIds($uris);

        $this->assertContains(sha1(strtolower(UrlCheck::fixUrl($uris[0]))), $result);
        $this->assertContains(sha1(strtolower(UrlCheck::fixUrl($uris[2]))), $result);
        $this->assertEquals(count($result), 2);
    }

    public function testFilterIndexedAndNotExpired()
    {
        $uris = [new Uri('https://github.com')];

        $solrQuery = $this->getMockBuilder('Solarium\QueryType\Select\Query\Query')
            ->disableOriginalConstructor()
            ->setMethods(['setQuery'])
            ->getMock();

        $solrQuery
            ->expects($this->once())
            ->method('setQuery');

        $solrResult = $this->getMockBuilder('Solarium\Core\Query\Result\Result')
            ->disableOriginalConstructor()
            ->setMethods(['getNumFound', 'getDocuments'])
            ->getMock();

        $solrResult->expects($this->once())
            ->method('getNumFound')
            ->will($this->returnValue(1));

        $solrResultDoc = new \stdClass();
        $solrResultDoc->id = sha1(strtolower(UrlCheck::fixUrl($uris[0])));

        $solrResult->expects($this->once())
            ->method('getDocuments')
            ->willReturn([$solrResultDoc]);

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
        $result = $indexer->filterIndexedAndNotExpired($uris, ['core' => 'coreName']);

        $this->assertEquals(count($result), 0);
    }

    public function testEmptyUrisFilterIndexedAndNotExpiredEmptyArray()
    {
        $uris = [];

        $solrClient = $this->getMockBuilder('Solarium\Client')
            ->setConstructorArgs([])
            ->setMethods(['createSelect', 'select'])
            ->getMock();

        $indexer = new Indexer($solrClient, [], 50);
        $result = $indexer->filterIndexedAndNotExpired($uris, ['core' => 'coreName']);

        $this->assertEquals(count($result), 0);
        $this->assertTrue(is_array($result));
    }

    /**
     * @test
     * @testdox Tests if the given url is expired in solr.
     */
    public function ifUrlIsIndexedAndExpiredReturnTrue()
    {
        $url = 'https://github.com';

        $solrQuery = $this->getMockBuilder('Solarium\QueryType\Select\Query\Query')
            ->disableOriginalConstructor()
            ->setMethods(['setQuery'])
            ->getMock();

        $solrQuery
            ->expects($this->exactly(2))
            ->method('setQuery');

        $solrResult = $this->getMockBuilder('Solarium\Core\Query\Result\Result')
            ->disableOriginalConstructor()
            ->setMethods(['getNumFound'])
            ->getMock();

        $solrResult
            ->expects($this->exactly(2))
            ->method('getNumFound')
            ->will($this->returnValue(1));

        $solrClient = $this->getMockBuilder('Solarium\Client')
            ->setConstructorArgs([])
            ->setMethods(['createSelect', 'select'])
            ->getMock();

        $solrClient->expects($this->once())
            ->method('createSelect')
            ->will($this->returnValue($solrQuery));

        $solrClient->expects($this->exactly(2))
            ->method('select')
            ->will($this->returnValue($solrResult));

        $indexer = new Indexer($solrClient, [], 50);
        $actual = $indexer->isUrlNotIndexedOrIndexedAndExpired($url, ['core' => 'coreName']);

        $this->assertTrue($actual);
    }

}
