<?php

namespace Simgroep\ConcurrentSpiderBundle\Command;

use Monolog\Logger;
use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\QueueFactory;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\Indexer;
use Simgroep\ConcurrentSpiderBundle\Spider;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Exception;

class CrawlCommand extends Command
{
    /**
     * @var \Simgroep\ConcurrentSpiderBundle\QueueFactory
     */
    private $queueFactory;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Queue
     */
    private $queue;

    /**
     * @var string
     */
    private $currentQueueType;

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
     * @var string
     */
    private $curlCertCADirectory;

    /**
     * @var \Monolog\Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\QueueFactory $queueFactory
     * @param \Simgroep\ConcurrentSpiderBundle\Indexer      $indexer
     * @param \Simgroep\ConcurrentSpiderBundle\Spider       $spider
     * @param string                                        $userAgent
     * @param string                                        $curlCertCADirectory
     * @param \Monolog\Logger                               $logger
     */
    public function __construct(
        QueueFactory $queueFactory,
        Indexer $indexer,
        Spider $spider,
        $userAgent,
        $curlCertCADirectory,
        Logger $logger
    )
    {
        $this->queueFactory = $queueFactory;
        $this->indexer = $indexer;
        $this->spider = $spider;
        $this->userAgent = $userAgent;
        $this->curlCertCADirectory = $curlCertCADirectory;
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
            ->addOption(
                'queueName',
                null,
                InputOption::VALUE_REQUIRED,
                'Name of queue to be used:  "urls" , "documents" ?'
            )
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
        $this->currentQueueType = $input->getOption('queueName');

        $this->setQueue($this->queueFactory->getQueue($this->currentQueueType));

        $this->queue->listen([$this, 'crawlUrl']);

        return 1;
    }

    /**
     * @param Queue $queue
     *
     * @return CrawlCommand
     */
    public function setQueue(Queue $queue)
    {
        $this->queue = $queue;

        return $this;
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
            $data['whitelist'],
            $data['queueName']
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
            $this->spider->getRequestHandler()->getClient()->setSslVerification($this->curlCertCADirectory);
            $this->spider->getRequestHandler()->getClient()->getConfig()->set('request.params', [
                'redirect.disable' => true
            ]);
            $this->spider->crawl($crawlJob, $this->queueFactory, $this->currentQueueType);

            $this->logMessage(
                'info',
                sprintf(
                    "Crawling %s",
                    $crawlJob->getUrl()
                ),
                $crawlJob->getUrl(),
                $data['metadata']['core'],
                $crawlJob->getQueueName()
            );
            $this->queue->acknowledge($message);

        } catch (ClientErrorResponseException $e) {
            switch ($e->getResponse()->getStatusCode()) {
                case 301:
                case 302:
                    $this->indexer->deleteDocument($message);
                    $this->queue->rejectMessage($message);

                    $this->markAsSkipped($crawlJob, 'warning', $e->getMessage());
                    $newCrawlJob = new CrawlJob(
                        $e->getResponse()->getInfo('redirect_url'),
                        $crawlJob->getBaseUrl(),
                        $crawlJob->getBlacklist(),
                        $crawlJob->getMetadata(),
                        $crawlJob->getWhitelist(),
                        $crawlJob->getQueueName()
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
     * @param string $core
     * @param string $queueName
     */
    public function logMessage($level, $message, $url, $core = 'unknown', $queueName = '')
    {
        $this->logger->{$level}($message, ['tags' => [parse_url($url, PHP_URL_HOST), $core, $queueName]]);
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
            $meta['core'],
            $crawlJob->getQueueName()
        );
    }

    /**
     * Logs a message that will tell the job is failed.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\CrawlJob $crawlJob
     * @param string                                    $errorMessage
     */
    public function markAsFailed(CrawlJob $crawlJob, $errorMessage)
    {
        $meta = $crawlJob->getMetadata();
        $this->logMessage(
            'emergency',
            sprintf(
                "Failed (%s) %s",
                $errorMessage,
                $crawlJob->getUrl()
            ),
            $crawlJob->getUrl(),
            $meta['core'],
            $crawlJob->getQueueName()
        );
    }

}
