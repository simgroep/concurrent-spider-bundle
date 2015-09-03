<?php

namespace Simgroep\ConcurrentSpiderBundle\EventListener;

use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\Indexer;
use Symfony\Component\EventDispatcher\GenericEvent as Event;
use Guzzle\Http\Exception\ClientErrorResponseException;

class IndexedUrlListener
{
    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Queue
     */
    private $queue;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Indexer
     */
    private $indexer;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Spider
     */
    private $spider;

    /**
     * @var boolean
     */
    private $urlRemoved;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue   $queue
     * @param \Simgroep\ConcurrentSpiderBundle\Indexer $indexer
     * @param \Simgroep\ConcurrentSpiderBundle\Spider  $spider
     */
    public function __construct(Queue $queue, Indexer $indexer, $spider)
    {
        $this->queue = $queue;
        $this->indexer = $indexer;
        $this->spider = $spider;
    }

    /**
     * Check status of indexed page identified by url and delete it from solr when its not existing
     * with remowing it from queue
     *
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public function onUrl(Event $event)
    {
        $this->urlRemoved = false;

        $crawlCommand = $event->getSubject();
        $message = $event->getArgument('message');
        $url = $event->getArgument('url');

        try {
            $this->spider->requestResourceFromUrl($url);
        } catch (ClientErrorResponseException $e) {
            //catch all responses with status from 400 to 418 and remove document from solr
            if (in_array($e->getResponse()->getStatusCode(), range(400, 418))) {

                $this->indexer->deleteDocument($message);
                $crawlCommand->logMessage('warning', sprintf("Deleted %s", $url), $url);
//                $this->queue->rejectMessage($message);//cause AMPQmessage fails
                $this->urlRemoved = true;
            }
        }
    }

    /**
     * Check if urlIsDeleted
     *
     * @return boolean
     */
    public function isUrlRemoved()
    {
        return $this->urlRemoved;
    }

}
