<?php
include_once('vendor/autoload.php');

use Symfony\Component\EventDispatcher\Event;
use VDB\Spider\Spider;
use VDB\Spider\Discoverer\XPathExpressionDiscoverer;
use VDB\Spider\Filter\Prefetch\AllowedHostsFilter;
use VDB\Spider\Filter\Prefetch\RestrictToBaseUriFilter;
use VDB\Spider\Event\SpiderEvents;
use VDB\Spider\Resource;
use VDB\Spider\PersistenceHandler\PersistenceHandler;
use PhpAmqpLib\Connection\AMQPConnection;

class SolrPersistenceHandler implements PersistenceHandler
{
    private $client;
    private $updateService;

    public function __construct()
    {
        $config = array(
            'adapteroptions' => array(
                'host' => '127.0.0.1',
                'port' => 8080,
                'path' => '/solr/',
            )
        );

        $this->client = new Solarium_Client($config);
        $this->updateService = $this->client->createUpdate();
    }

    public function setSpiderId($spiderId)
    {
    }

    public function persist(Resource $resource)
    {
        $title = $resource->getCrawler()->filterXpath('//title')->text();

        $doc = $this->updateService->createDocument();
        $doc->id = sha1($resource->getUri());
        $doc->title = $title;

        $this->updateService->addDocument($doc);
        $this->updateService->addCommit();
        $this->client->update($this->updateService);
    }
}

$seed = 'https://polyestershoppen.nl';
$allowSubDomains = true;

$spider = new Spider($seed);
$spider->addDiscoverer(new XPathExpressionDiscoverer("//a"));
$spider->setMaxDepth(10);
$spider->setMaxQueueSize(1);
$spider->addPreFetchFilter(new AllowedHostsFilter(array($seed), $allowSubDomains));
$spider->addPreFetchFilter(new RestrictToBaseUriFilter($seed));
$spider->setPersistenceHandler(new SolrPersistenceHandler());
$spider->getDispatcher()->addListener(
    SpiderEvents::SPIDER_CRAWL_POST_DISCOVER,
    function (Event $event) {
        foreach ($event['uris'] as $uri) {
            echo $uri->toString() . "\n";
        }
    }
);

$spider->crawl();

foreach ($spider->getPersistenceHandler() as $resource) {
    echo "\n - " . $resource->getCrawler()->filterXpath('//title')->text();
}


