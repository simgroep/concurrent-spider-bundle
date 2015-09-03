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
use VDB\Spider\Event\SpiderEvents;
use Symfony\Component\EventDispatcher\GenericEvent as Event;

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
     * @var \Monolog\Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue   $queue
     * @param \Simgroep\ConcurrentSpiderBundle\Indexer $indexer
     * @param \Simgroep\ConcurrentSpiderBundle\Spider  $spider
     * @param \Monolog\Logger                          $logger
     */
    public function __construct(
        Queue $queue,
        Indexer $indexer,
        Spider $spider,
        Logger $logger
    ) {
        $this->queue = $queue;
        $this->indexer = $indexer;
        $this->spider = $spider;
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

            $this->spider->getEventDispatcher()->dispatch(
                SpiderEvents::SPIDER_CRAWL_POST_REQUEST,
                new Event($this, ['url' => $crawlJob->getUrl(), 'message' => $message])
            );

            $this->queue->rejectMessage($message);
            $this->markAsSkipped($crawlJob);

            return;
        }

        $this->spider->getEventDispatcher()->dispatch(
            SpiderEvents::SPIDER_CRAWL_START,
            new Event($this, ['crawlJob' => $crawlJob, 'message' => $message])
        );

        $this->spider->getEventDispatcher()->dispatch(SpiderEvents::SPIDER_CRAWL_END);
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
