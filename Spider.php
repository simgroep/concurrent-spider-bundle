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
     * @var Uri
     */
    private $currentUri;

    /**
     * @var array
     */
    private $blacklist;

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
     * @param Uri $currentUri
     */
    private function setCurrentUri(Uri $currentUri)
    {
        $this->currentUri = $currentUri;
    }

    /**
     * Returns the URI that is currently crawled.
     *
     * @return Uri $uri
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
     * Sets the blacklist.
     *
     * @param array $blacklist
     */
    public function setBlacklist(array $blacklist)
    {
        $this->blacklist = $blacklist;
    }

    /**
     * Returns the blacklisted url's.
     *
     * @return array
     */
    public function getBlacklist()
    {
        return $this->blacklist;
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
     * Function that crawls one webpage based on the give url.
     *
     * @param string $uri
     */
    public function crawlUrl($uri)
    {
        $this->setCurrentUri(new Uri($uri));
        $resource = $this->requestHandler->request($this->currentUri);

        $crawler = $resource->getCrawler()->filterXPath('//a');
        $uris = array();

        $this->persistenceHandler->persist($resource);
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

        $spider = $this;

        $this->eventDispatcher->dispatch(
            SpiderEvents::SPIDER_CRAWL_POST_DISCOVER,
            new GenericEvent($this, array('uris' => $uris))
        );
    }
}
