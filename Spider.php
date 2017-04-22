<?php

namespace Simgroep\ConcurrentSpiderBundle;

use Guzzle\Http\Client;
use Guzzle\Plugin\Cookie\CookieJar\ArrayCookieJar;
use Guzzle\Plugin\Cookie\CookiePlugin;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\HttpFoundation\Request;
use VDB\Uri\Uri;
use VDB\Uri\Exception\UriSyntaxException;
use VDB\Spider\RequestHandler\GuzzleRequestHandler;
use VDB\Spider\Event\SpiderEvents;
use Simgroep\ConcurrentSpiderBundle\PersistenceHandler\PersistenceHandler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Spider
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var GuzzleRequestHandler
     */
    private $requestHandler;

    /**
     * @var PersistenceHandler
     */
    private $persistenceHandler;

    /**
     * @var CrawlJob
     */
    private $currentCrawlJob;

    /**
     * @var CookiePlugin
     */
    private $cookiePlugin;

    /**
     * @var resource
     */
    private $curlClient;

    /**
     * Constructor.
     *
     * @param EventDispatcherInterface $eventDispatcher
     * @param GuzzleRequestHandler $requestHandler
     * @param PersistenceHandler $persistenceHandler
     * @param CookiePlugin $cookiePlugin
     * @param resource $curlClient
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        GuzzleRequestHandler $requestHandler,
        PersistenceHandler $persistenceHandler,
        CookiePlugin $cookiePlugin,
        $curlClient
    )
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->requestHandler = $requestHandler;
        $this->persistenceHandler = $persistenceHandler;
        $this->cookiePlugin = $cookiePlugin;
        $this->curlClient = $curlClient;
    }

    /**
     * Returns the request handler.
     *
     * @return GuzzleRequestHandler
     */
    public function getRequestHandler()
    {
        return $this->requestHandler;
    }

    /**
     * Returns the event dispatcher.
     *
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * Returns the job that is currently being processed.
     *
     * @return CrawlJob
     */
    public function getCurrentCrawlJob()
    {
        return $this->currentCrawlJob;
    }

    /**
     * Returns the persistence handler.
     *
     * @return PersistenceHandler
     */
    public function getPersistenceHandler()
    {
        return $this->persistenceHandler;
    }

    /**
     * Function that crawls one webpage based on the give url.
     *
     * @param CrawlJob $crawlJob
     * @param QueueFactory $queueFactory
     * @param string $currentQueueType
     */
    public function crawl(CrawlJob $crawlJob, QueueFactory $queueFactory, $currentQueueType)
    {
        $this->currentCrawlJob = $crawlJob;

        $uri = new Uri($crawlJob->getUrl());

        $this->curlClient->initClient();

        if ($this->curlClient->isDocument($uri) && $currentQueueType != QueueFactory::QUEUE_DOCUMENTS) {
            $message = new AMQPMessage(
                json_encode($crawlJob->toArray()),
                ['delivery_mode' => 1]
            );

            $queueFactory->getQueue(QueueFactory::QUEUE_DOCUMENTS)->publish($message);

        } else {
            $resource = $this->requestHandler->request($uri);
            $crawler = $resource->getCrawler();
            $baseUrl = $resource->getUri()->toString();

            if (!trim($resource->getResponse()->getBody(true))) {
                $this->setClient($uri->toString());
                $resource = $this->requestHandler->request($uri);
                $crawler = $resource->getCrawler();
            }

            $uris = [];

            $this->eventDispatcher->dispatch(SpiderEvents::SPIDER_CRAWL_PRE_DISCOVER);

            $crawler = $crawler->filterXPath('//a');;
            foreach ($crawler as $node) {
                try {
                    if ($node->getAttribute("rel") === "nofollow") {
                        continue;
                    }
                    $href = $node->getAttribute('href');
                    $uri = new Uri($href, $baseUrl);
                    $uris[] = $uri;
                } catch (UriSyntaxException $e) {
                    //too bad
                }
            }

            $crawler = $crawler->filterXPath('//loc');;
            foreach ($crawler as $node) {
                try {
                    $href = $node->nodeValue;
                    $uri = new Uri($href, $baseUrl);
                    $uris[] = $uri;
                } catch (UriSyntaxException $e) {
                    //too bad
                }
            }

            $this->eventDispatcher->dispatch(
                SpiderEvents::SPIDER_CRAWL_POST_DISCOVER,
                new GenericEvent($this, ['uris' => $uris])
            );

            $this->persistenceHandler->persist($resource, $crawlJob);
        }
    }

    /**
     * Sets require cookie jar for request handler
     *
     * @param string $uri
     */
    public function setClient($uri)
    {
        $client = new Client($uri);
        $client->addSubscriber($this->cookiePlugin);
        $this->requestHandler->setClient($client);
    }

}
