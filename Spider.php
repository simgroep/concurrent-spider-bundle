<?php

namespace Simgroep\ConcurrentSpiderBundle;

use Guzzle\Http\Exception\ClientErrorResponseException;
use PhpAmqpLib\Message\AMQPMessage;
use VDB\Uri\Uri;
use VDB\Uri\Exception\UriSyntaxException;
use VDB\Spider\RequestHandler\GuzzleRequestHandler;
use VDB\Spider\Event\SpiderEvents;
use Simgroep\ConcurrentSpiderBundle\PersistenceHandler\PersistenceHandler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Simgroep\ConcurrentSpiderBundle\QueueFactory;
use VDB\Spider\Resource;

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
    )
    {
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
     * @param \Simgroep\ConcurrentSpiderBundle\CrawlJob     $crawlJob
     * @param \Simgroep\ConcurrentSpiderBundle\QueueFactory $queueFactory
     * @param string                                        $currentQueueType
     */
    public function crawl(CrawlJob $crawlJob, QueueFactory $queueFactory, $currentQueueType)
    {
        $this->currentCrawlJob = $crawlJob;
        $resource = $this->requestHandler->request(new Uri($crawlJob->getUrl()));

        if ($resource->getResponse()->getStatusCode() == 301) {
            $exception = new ClientErrorResponseException(sprintf(
                "Page moved to %s",
                $resource->getResponse()->getInfo('redirect_url')
            ), 301);
            $exception->setResponse($resource->getResponse());
            throw $exception;
        }

        if ($this->isDocument($resource) && $currentQueueType != QueueFactory::QUEUE_DOCUMENTS) {

            $message = new AMQPMessage(
                json_encode($crawlJob->toArray()),
                ['delivery_mode' => 1]
            );

            $queueFactory->getQueue(QueueFactory::QUEUE_DOCUMENTS)->publish($message);

        } else {

            $uris = [];

            $this->eventDispatcher->dispatch(SpiderEvents::SPIDER_CRAWL_PRE_DISCOVER);

            $baseUrl = $resource->getUri()->toString();

            $crawler = $resource->getCrawler()->filterXPath('//a');
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

            $crawler = $resource->getCrawler()->filterXPath('//loc');
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
     * @param Resource $resource
     *
     * @return bool
     */
    public function isDocument(Resource $resource)
    {
        switch ($resource->getResponse()->getContentType()) {
            case 'application/pdf':
            case 'application/octet-stream' :
            case 'application/msword' :
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' :
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.template' :
            case 'application/rtf' :
            case 'application/vnd.oasis.opendocument.text' :
                return true;
        }

        return false;
    }
}
