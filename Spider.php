<?php

namespace Simgroep\ConcurrentSpiderBundle;

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
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var \VDB\Spider\RequestHandler\GuzzleRequestHandler
     */
    private $requestHandler;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\PersistenceHandler\PersistenceHandler
     */
    private $persistenceHandler;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\CrawlJob
     */
    private $currentCrawlJob;

    /**
     * Constructor.
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface            $eventDispatcher
     * @param \VDB\Spider\RequestHandler\GuzzleRequestHandler                        $requestHandler
     * @param \Simgroep\ConcurrentSpiderBundle\PersistenceHandler\PersistenceHandler $persistenceHandler
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        GuzzleRequestHandler $requestHandler,
        PersistenceHandler $persistenceHandler
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->requestHandler = $requestHandler;
        $this->persistenceHandler = $persistenceHandler;
    }

    /**
     * Returns the request handler.
     *
     * @return \VDB\Spider\RequestHandler\GuzzleRequestHandler
     */
    public function getRequestHandler()
    {
        return $this->requestHandler;
    }

    /**
     * Returns the event dispatcher.
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * Returns the job that is currently being processed.
     *
     * @return \Simgroep\ConcurrentSpiderBundle\CrawlJob
     */
    public function getCurrentCrawlJob()
    {
        return $this->currentCrawlJob;
    }

    /**
     * Returns the persistence handler.
     *
     * @return \VDB\Spider\PersistenceHandler\PersistenceHandler
     */
    public function getPersistenceHandler()
    {
        return $this->persistenceHandler;
    }

    /**
     * Function that crawls one webpage based on the give url.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\CrawlJob $crawlJob
     */
    public function crawl(CrawlJob $crawlJob)
    {
        $this->currentCrawlJob = $crawlJob;
        $resource = $this->requestHandler->request(new Uri($crawlJob->getUrl()));
        $crawler = $resource->getCrawler()->filterXPath('//a');
        $uris = array();

        $this->persistenceHandler->persist($resource, $crawlJob);
        $this->eventDispatcher->dispatch(SpiderEvents::SPIDER_CRAWL_PRE_DISCOVER);

        foreach ($crawler as $node) {
            try {
                $href = $node->getAttribute('href');
                $baseUrl = $resource->getUri()->toString();

                $uri = new Uri($href, $baseUrl);
                $uris[] = $uri;
            } catch (UriSyntaxException $e) {
                //too bad
            }
        }

        $this->eventDispatcher->dispatch(
            SpiderEvents::SPIDER_CRAWL_POST_DISCOVER,
            new GenericEvent($this, array('uris' => $uris))
        );
    }
}
