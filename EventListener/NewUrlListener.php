<?php

namespace Simgroep\ConcurrentSpiderBundle\EventListener;

use Simgroep\ConcurrentSpiderBundle\Queue;
use Symfony\Component\EventDispatcher\GenericEvent as Event;
use VDB\Uri\Exception\UriSyntaxException;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Exception;

class NewUrlListener
{
    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Queue
     */
    private $queue;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Spider
     */
    private $spider;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue   $queue
     * @param \Simgroep\ConcurrentSpiderBundle\Spider  $spider
     */
    public function __construct(Queue $queue, $spider)
    {
        $this->queue = $queue;
        $this->spider = $spider;
    }

    /**
     * Crawl a not indexed page(document) identified by url from queue
     * and save its content to solr when no error occure
     * then remove that url from queue
     *
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public function onNotIndexedUrl(Event $event)
    {
        $crawlCommand = $event->getSubject();
        $crawlJob = $event->getArgument('crawlJob');
        $message = $event->getArgument('message');

        try {
            $this->spider->crawl($crawlJob);

            $crawlCommand->logMessage('info', sprintf("Crawling %s", $crawlJob->getUrl()), $crawlJob->getUrl());
            $this->queue->acknowledge($message);
        } catch (UriSyntaxException $e) {
            $crawlCommand->markAsFailed($crawlJob, 'Invalid URI syntax');
            $this->queue->rejectMessageAndRequeue($message);
        } catch (ClientErrorResponseException $e) {
            if (in_array($e->getResponse()->getStatusCode(), [404, 403, 401, 500])) {
                $this->queue->rejectMessage($message);
                $crawlCommand->markAsSkipped($crawlJob, 'warning');
            } else {
                $this->queue->rejectMessageAndRequeue($message);
                $crawlCommand->markAsFailed($crawlJob, $e->getResponse()->getStatusCode());
            }
        } catch (Exception $e) {
            $this->queue->rejectMessage($message);
            $crawlCommand->markAsFailed($crawlJob, $e->getMessage());
        }
    }

}
