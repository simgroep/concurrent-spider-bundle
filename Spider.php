<?php

namespace Simgroep\ConcurrentSpiderBundle;

use VDB\Uri\Uri;
use VDB\Uri\Exception\UriSyntaxException;
use VDB\Spider\RequestHandler\GuzzleRequestHandler;
use VDB\Spider\PersistenceHandler\PersistenceHandler;
use VDB\Spider\Event\SpiderEvents;
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
     * @var \VDB\Spider\PersistenceHandler\PersistenceHandler
     */
    private $persistenceHandler;

    /**
     * @var string
     */
    private $currentUri;

    /**
     * Constructor.
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
     * @param \VDB\Spider\RequestHandler\GuzzleRequestHandler             $requestHandler
     * @param \VDB\Spider\PersistenceHandler\PersistenceHandler           $persistenceHandler
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
     * Returns the URI that is currently cralwed.
     *
     * @return \VDB\Uri\Uri
     */
    public function getCurrentUri()
    {
        return $this->currentUri;
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
     * Function that crawls one webpage based on the give url.
     *
     * @param string $uri
     */
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
