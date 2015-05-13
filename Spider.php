<?php

namespace Simgroep\ConcurrentSpiderBundle;

use VDB\Uri\Uri;
use VDB\Uri\Exception\UriSyntaxException;
use VDB\Spider\RequestHandler\GuzzleRequestHandler;
use VDB\Spider\PersistenceHandler\PersistenceHandler;
use VDB\Spider\Event\SpiderEvents;
use VDB\Spider\Resource;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Spider
{
    private $eventDispatcher;
    private $requestHandler;
    private $persistenceHandler;
    private $currentUri;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        GuzzleRequestHandler $requestHandler,
        PersistenceHandler $persistenceHandler
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->requestHandler = $requestHandler;
        $this->persistenceHandler = $persistenceHandler;
    }

    public function getCurrentUri()
    {
        return $this->currentUri;
    }

    public function crawlUrl($uri)
    {
        $this->currentUri = new Uri($uri);
        $resource = $this->requestHandler->request($this->currentUri);

        $crawler = $resource->getCrawler()->filterXPath('//a');
        $uris = array();

        $this->persistenceHandler->persist($resource);
        $this->eventDispatcher->dispatch(SpiderEvents::SPIDER_CRAWL_PRE_DISCOVER);

        foreach ($crawler as $node) {
            try {
                $uri = new Uri($node->getAttribute('href'), $resource->getUri()->toString() . '/');
                $uris[] = $uri;
            } catch (UriSyntaxException $e) {
                //too bad
            }
        }

        $uris = array_unique($uris);

        $this->eventDispatcher->dispatch(
            SpiderEvents::SPIDER_CRAWL_POST_DISCOVER,
            new GenericEvent($this, array('uris' => $uris))
        );
    }
}
