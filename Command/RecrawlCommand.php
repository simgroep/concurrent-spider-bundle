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

class RecrawlCommand extends Command
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
     * @var \Monolog\Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue   $queue
     * @param \Simgroep\ConcurrentSpiderBundle\Indexer $indexer
     * @param \Monolog\Logger                          $logger
     */
    public function __construct(
        Queue $queue,
        Indexer $indexer,
        Logger $logger
    ) {
        $this->queue = $queue;
        $this->indexer = $indexer;
        $this->logger = $logger;

        parent::__construct();
    }

    /**
     * Configure the command options.
     */
    public function configure()
    {
        $this
            ->setName('simgroep:recrawl')
            ->setDescription("This command starts listening to the queue and will recrawl.");
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
        $this->queue->listen([$this, 'recrawl']);

        return 1;
    }

    /**
     * Consume a message, extracts the URL from it and crawls the webpage.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function recrawl(AMQPMessage $message)
    {
        try {
            $body = json_decode($message->body);
            // checking blacklist
            if (is_array($body->blacklist) && count($body->blacklist) > 0) {
                $this->dropBlacklistedDocuments($body->blacklist, $body->metadata);
            }
            $this->queue->acknowledge($message);
        } catch (Exception $e) {
            $this->queue->rejectMessage($message);
            $this->logMessage(
                "emergency",
                $e->getMessage(),
                $body->metadata->core
            );
        }

    }

    public function dropBlacklistedDocuments (array $blacklist, $metadata) {
        $result = $this->indexer->getDocumentUrlsInCore(['core' => $metadata->core]);
        $toDelete = [];
        foreach ($result as $document) {
            if ($this->isUrlBlacklisted($document->url, $blacklist)) {
                $toDelete[] = [
                    'core' => $metadata->core,
                    'id' => $document->id,
                    'url' => $document->url
                ];
            }
        }
        foreach ($toDelete as $document) {
            $this->indexer->deleteDocumentById(['core' => $document['core']], $document['id']);
            $this->logMessage(
                "info",
                sprintf(
                    "Delete document %s. URL: %s",
                    $document['id'],
                    $document['url']
                ),
                $document['url']
            );
        }
    }

    /**
     * Check if given url is blacklisted
     *
     * @param string $url
     * @param array $blacklist
     *
     * @return boolean
     */
    public function isUrlBlacklisted($url, array $blacklist)
    {
        $isBlacklisted = false;

        array_walk(
            $blacklist,
            function ($blacklistUrl) use ($url, &$isBlacklisted) {
                if (@preg_match('#' . $blacklistUrl . '#i', $url)) {
                    $isBlacklisted = true;
                }
            }
        );

        return $isBlacklisted;
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

}
