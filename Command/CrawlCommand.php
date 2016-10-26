<?php

namespace Simgroep\ConcurrentSpiderBundle\Command;

use Monolog\Logger;
use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\Indexer;
use Simgroep\ConcurrentSpiderBundle\Spider;
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
        $data = json_decode($message->body, true);

        $crawlJob = new CrawlJob(
            $data['url'],
            $data['base_url'],
            $data['blacklist'],
            $data['metadata'],
            $data['whitelist']
        );

        if (false === $crawlJob->isAllowedToCrawl()) {
            $this->indexer->deleteDocument($message);

            $this->queue->rejectMessage($message);
            $this->markAsSkipped($crawlJob, 'info', 'Not allowed to crawl');

            return;
        }

        if ($this->indexer->isUrlIndexedAndNotExpired($crawlJob->getUrl(), $crawlJob->getMetadata())) {
            $this->queue->rejectMessage($message);
            $this->markAsSkipped($crawlJob, 'info', 'Not expired yet');

            return;
        }

        try {
            $this->spider->getRequestHandler()->getClient()->setUserAgent($this->userAgent);
            $this->spider->getRequestHandler()->getClient()->getConfig()->set('request.params', [
                'redirect.disable' => true,
            ]);
            $this->spider->crawl($crawlJob);

            $this->logMessage('info', sprintf("Crawling %s", $crawlJob->getUrl()), $crawlJob->getUrl(), $data['metadata']['core']);
            $this->queue->acknowledge($message);
        } catch (ClientErrorResponseException $e) {
            switch ($e->getResponse()->getStatusCode()) {
                case 301:
                    $this->indexer->deleteDocument($message);
                    $this->queue->rejectMessage($message);

                    $this->markAsSkipped($crawlJob, 'warning', $e->getMessage());
                    $newCrawlJob = new CrawlJob(
                        $e->getResponse()->getInfo('redirect_url'),
                        $crawlJob->getBaseUrl(),
                        $crawlJob->getBlacklist(),
                        $crawlJob->getMetadata(),
                        $crawlJob->getWhitelist()
                    );
                    $this->queue->publishJob($newCrawlJob);
                    break;
                case 403:
                case 401:
                case 500:
                    $this->queue->rejectMessage($message);
                    $this->markAsSkipped($crawlJob, 'warning', 'status: ' . $e->getResponse()->getStatusCode());
                    break;
                case 404:
                case 418:
                    $this->indexer->deleteDocument($message);
                    $this->logMessage('warning', sprintf("Deleted %s", $crawlJob->getUrl()), $crawlJob->getUrl(), $data['metadata']['core']);
                    $this->queue->rejectMessage($message);
                    break;
                default:
                    $this->queue->rejectMessageAndRequeue($message);
                    $this->markAsFailed($crawlJob, $e->getResponse()->getStatusCode());
                    break;

            }
        } catch (Exception $e) {
            $this->queue->rejectMessage($message);
            $this->markAsFailed($crawlJob, $e->getMessage());
        }

        unset($crawlJob, $message, $data);
        gc_collect_cycles();
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
    public function logMessage($level, $message, $url, $core = 'unknown')
    {
        $this->logger->{$level}($message, ['tags' => [parse_url($url, PHP_URL_HOST), $core]]);
    }

    /**
     * Logs a message that will tell the job is skipped.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\CrawlJob $crawlJob
     * @param string                                    $level
     */
    public function markAsSkipped(CrawlJob $crawlJob, $level = 'info', $reason = 'unknown')
    {
        $meta = $crawlJob->getMetadata();
        $this->logMessage(
            $level,
            sprintf(
                "Skipped %s, reason: %s",
                $crawlJob->getUrl(),
                $reason
            ),
            $crawlJob->getUrl(),
            $meta['core']
        );
    }

    /**
     * Logs a message that will tell the job is failed.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\CrawlJob $crawlJob
     * @param string                                    $level
     */
    public function markAsFailed(CrawlJob $crawlJob, $errorMessage)
    {
        $meta = $crawlJob->getMetadata();
        $this->logMessage('emergency', sprintf("Failed (%s) %s", $errorMessage, $crawlJob->getUrl()), $crawlJob->getUrl(), $meta['core']);
    }

}
