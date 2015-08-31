<?php

namespace Simgroep\ConcurrentSpiderBundle\Command;

use Monolog\Logger;
use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\Indexer;
use Simgroep\ConcurrentSpiderBundle\Spider;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VDB\Uri\Exception\UriSyntaxException;
use Guzzle\Http\Exception\ClientErrorResponseException;
use VDB\Uri\Uri;
use Exception;

class CrawlCommand extends Command
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
     * @var string
     */
    private $userAgent;

    /**
     * @var \Monolog\Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue   $queue
     * @param \Simgroep\ConcurrentSpiderBundle\Indexer $indexer
     * @param \Simgroep\ConcurrentSpiderBundle\Spider  $spider
     * @param string                                   $userAgent
     * @param \Monolog\Logger                          $logger
     */
    public function __construct(
        Queue $queue,
        Indexer $indexer,
        Spider $spider,
        $userAgent,
        Logger $logger
    ) {
        $this->queue = $queue;
        $this->indexer = $indexer;
        $this->spider = $spider;
        $this->userAgent = $userAgent;
        $this->logger = $logger;

        parent::__construct();
    }

    /**
     * Configure the command options.
     */
    public function configure()
    {
        $this
            ->setName('simgroep:crawl')
            ->setDescription("This command starts listening to the queue and will accept url's to index.");
    }

    /**
     * Starts to listen to the queue and grabs the messages from the queue to crawl url's.
     *
     * It should endless keep listening to the queue, if the listen function stops, something went wrong
     * so a non-zero integer is returned to indicate something went wrong.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->queue->listen([$this, 'crawlUrl']);

        return 1;
    }

    /**
     * Consume a message, extracts the URL from it and crawls the webpage.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function crawlUrl(AMQPMessage $message)
    {
        $crawlJob = CrawlJob::create($message);

        if (!$this->areHostsEqual($crawlJob->getUrl(), $crawlJob->getBaseUrl())) {
            $this->queue->rejectMessage($message);
            $this->markAsSkipped($crawlJob);

            return;
        }

        if ($this->indexer->isUrlIndexed($crawlJob->getUrl(), $crawlJob->getMetadata())) {

            try {
                $requestHandler = $this->spider->getRequestHandler();
                $requestHandler->getClient()->setUserAgent($this->userAgent);
                $requestHandler->request(new Uri($crawlJob->getUrl()));
            } catch (ClientErrorResponseException $e) {
                if (in_array($e->getResponse()->getStatusCode(), range(400, 418))) {
                    $this->indexer->deleteDocument($message);
                    $this->logMessage('warning', sprintf("Deleted %s", $crawlJob->getUrl()), $crawlJob->getUrl());
                    $this->queue->rejectMessage($message);

                    return;
                }
            }

            $this->queue->rejectMessage($message);
            $this->markAsSkipped($crawlJob);

            return;
        }

        try {
            $this->spider->getRequestHandler()->getClient()->setUserAgent($this->userAgent);
            $this->spider->crawl($crawlJob);

            $this->logMessage('info', sprintf("Crawling %s", $crawlJob->getUrl()), $crawlJob->getUrl());
            $this->queue->acknowledge($message);
        } catch (UriSyntaxException $e) {
            $this->markAsFailed($crawlJob, 'Invalid URI syntax');
            $this->queue->rejectMessageAndRequeue($message);
        } catch (ClientErrorResponseException $e) {
            if (in_array($e->getResponse()->getStatusCode(), [404, 403, 401, 500])) {
                $this->queue->rejectMessage($message);
                $this->markAsSkipped($crawlJob, 'warning');
            } else {
                $this->queue->rejectMessageAndRequeue($message);
                $this->markAsFailed($crawlJob, $e->getResponse()->getStatusCode());
            }
        } catch (Exception $e) {
            $this->queue->rejectMessage($message);
            $this->markAsFailed($crawlJob, $e->getMessage());
        }
    }

    /**
     * Log a message to the logger.
     *
     * The level is the function name according to the PSR-3 logging interface.
     *
     * @param string $level
     * @param string $message
     * @param string $url
     */
    public function logMessage($level, $message, $url)
    {
        $this->logger->{$level}($message, ['tags' => [parse_url($url, PHP_URL_HOST)]]);
    }

    /**
     * Logs a message that will tell the job is skipped.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\CrawlJob $crawlJob
     * @param string                                    $level
     */
    public function markAsSkipped(CrawlJob $crawlJob, $level = 'info')
    {
        $this->logMessage($level, sprintf("Skipped %s", $crawlJob->getUrl()), $crawlJob->getUrl());
    }

    /**
     * Logs a message that will tell the job is failed.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\CrawlJob $crawlJob
     * @param string                                    $level
     */
    public function markAsFailed(CrawlJob $crawlJob, $errorMessage)
    {
        $this->logMessage('emergency', sprintf("Failed (%s) %s", $errorMessage, $crawlJob->getUrl()), $crawlJob->getUrl());
    }

    /**
     * Indicates whether the hostname parts of two urls are equal.
     *
     * @param string $firstUrl
     * @param string $secondUrl
     *
     * @return boolean
     */
    private function areHostsEqual($firstUrl, $secondUrl)
    {
        $firstHost = parse_url($firstUrl, PHP_URL_HOST);
        $secondHost = parse_url($secondUrl, PHP_URL_HOST);

        if (is_null($firstHost) || is_null($secondHost)) {
            return false;
        }

        return ($firstHost === $secondHost);
    }
}
