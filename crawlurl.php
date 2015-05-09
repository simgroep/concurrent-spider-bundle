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
use PhpAmqpLib\Message\AMQPMessage;

ini_set('display_errors', true);

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
        try {
            $title = $resource->getCrawler()->filterXpath('//title')->text();
            $content = $resource->getCrawler()->filterXpath('//body')->text();

            $doc = $this->updateService->createDocument();
            $doc->id = sha1($resource->getUri());
            $doc->title = $title;
            $doc->tstamp = date('Y-m-d\TH:i:s\Z');
            $doc->date = date('Y-m-d\TH:i:s\Z');
            $doc->publishedDate = date('Y-m-d\TH:i:s\Z');
            $doc->content = $content;

            $this->updateService->addDocument($doc);
            $this->updateService->addCommit();
            $this->client->update($this->updateService);
        } catch (\InvalidArgumentException $e) {
            echo sprintf("[x] URL %s failed \n", $resource->getUri());
        }
    }
}

function crawl_url(AMQPMessage $message)
{
    global $channel;

    $data = json_decode($message->body, true);
    $urlToCrawl = $data['uri'];
    $baseUrl = $data['base_url'];
    $allowSubDomains = true;

    $urlToCrawlParts = parse_url($urlToCrawl);
    $baseUrlParts = parse_url($baseUrl);

    if ($urlToCrawlParts['host'] !== $baseUrlParts['host']) {
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);

        echo sprintf("[x] Skipped %s\n", $urlToCrawl);
        return;
    }

    if ($data['force'] === false) {
        $config = array(
            'adapteroptions' => array(
                'host' => '127.0.0.1',
                'port' => 8080,
                'path' => '/solr/',
            )
        );

        $dayAgo = date('Y-m-d\TH:i:s\Z', mktime(date('H')-24));
        $client = new Solarium_Client($config);
        $query = $client->createSelect();
        $query->setQuery(sprintf("id:%s AND date:[%s TO NOW]", sha1($urlToCrawl), $dayAgo));
        $result = $client->select($query);

        if ($result->getNumFound() > 0) {
            $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);

            echo sprintf("[x] Skipped %s\n", $urlToCrawl);
            return;
        }
    }

    echo sprintf("[x] Crawling: %s \n", $urlToCrawl);

    try {
        $spider = new Spider($urlToCrawl);
        $spider->addDiscoverer(new XPathExpressionDiscoverer("//a"));
        $spider->setMaxDepth(10);
        $spider->setMaxQueueSize(1);
        $spider->addPreFetchFilter(new AllowedHostsFilter(array($baseUrl), $allowSubDomains));
        $spider->addPreFetchFilter(new RestrictToBaseUriFilter($baseUrl));
        $spider->setPersistenceHandler(new SolrPersistenceHandler());
        $spider->getDispatcher()->addListener(
            SpiderEvents::SPIDER_CRAWL_POST_DISCOVER,
            function (Event $event) use ($channel, $baseUrl) {

                foreach ($event['uris'] as $uri) {
                    $data = json_encode(array('uri' => $uri->toString(), 'base_url' => $baseUrl, 'force' => false));
                    $message = new AMQPMessage($data, array('delivery_mode' => 2));
                    $channel->basic_publish($message, '', 'discovered_urls');
                }
            }
        );

        $spider->crawl();
    } catch (VDB\Uri\Exception\UriSyntaxException $e) {
        echo sprintf("[x] URL %s failed \n", $urlToCrawl);
    }

    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
}

$connection = new AMQPConnection(
    'localhost',
    '5672',
    'guest',
    'guest'
);

$channel = $connection->channel();
$channel->queue_declare('discovered_urls', false, true, false, false);
$channel->basic_qos(null, 1, null);
$channel->basic_consume(
    'discovered_urls',
    '',
    false,
    false,
    false,
    false,
    'crawl_url'
);

while(count($channel->callbacks)) {
    $channel->wait();
}

$channel->close();
$connection->close();
