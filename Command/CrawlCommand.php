<?php

namespace Simgroep\ConcurrentSpiderBundle\Command;

use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\Indexer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\ContainerInterface;
use VDB\Uri\Exception\UriSyntaxException;
use VDB\Spider\Discoverer\XPathExpressionDiscoverer;
use VDB\Spider\Filter\Prefetch\AllowedHostsFilter;
use VDB\Spider\Filter\Prefetch\RestrictToBaseUriFilter;
use VDB\Spider\PersistenceHandler\PersistenceHandler;

class CrawlCommand extends Command
{
    private $container;
    private $queue;
    private $indexer;
    private $persistenceHandler;
    private $output;

    public function __construct(
        ContainerInterface $container,
        Queue $queue,
        Indexer $indexer,
        PersistenceHandler $persistenceHandler
    ) {
        $this->container = $container;
        $this->queue = $queue;
        $this->indexer = $indexer;
        $this->persistenceHandler = $persistenceHandler;

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
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->queue->listen(array($this, 'crawlUrl'));

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
        $urlToCrawl = $data['uri'];
        $baseUrl = $data['base_url'];
        $allowSubDomains = true;

        if (!$this->areHostsEqual($urlToCrawl, $baseUrl)) {
            $this->queue->rejectMessage($message);

            $this->output->writeLn(sprintf("[x] Skipped %s", $urlToCrawl));
            return;
        }

        if ($this->indexer->isUrlIndexed($urlToCrawl)) {
            $this->queue->rejectMessage($message);

            $this->output->writeLn(sprintf("[x] Skipped %s", $urlToCrawl));
            return;
        }

        $this->output->writeLn(sprintf("[x] Crawling: %s", $urlToCrawl));

        try {
            $spider = $this->container->get('simgroep_concurrent_spider.spider');
            $spider->setSeed($urlToCrawl);
            $spider->addDiscoverer(new XPathExpressionDiscoverer("//a"));
            $spider->setMaxDepth(10);
            $spider->setMaxQueueSize(1);
            $spider->addPreFetchFilter(new AllowedHostsFilter(array($baseUrl), $allowSubDomains));
            $spider->addPreFetchFilter(new RestrictToBaseUriFilter($baseUrl));
            $spider->setPersistenceHandler($this->persistenceHandler);
            $spider->crawl();

        } catch (UriSyntaxException $e) {
            $this->output->writeLn(sprintf('<error>[x] URL %s failed</error>', $urlToCrawl));

            $this->queue->rejectMessageAndRequeue($message);
        } catch (\InvalidArgumentException $e) {
            $this->queue->rejectMessage($message);
        }

        $this->queue->acknowledge($message);

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
        $firstUrl = parse_url($firstUrl);
        $secondUrl = parse_url($secondUrl);

        return ($firstUrl['host'] === $secondUrl['host']);
    }
}
