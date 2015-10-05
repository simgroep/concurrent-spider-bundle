<?php

namespace Simgroep\ConcurrentSpiderBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;

class StartCrawlerCommand extends Command
{
    protected $queue;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue $queue
     */
    public function __construct(Queue $queue)
    {
        $this->queue = $queue;

        parent::__construct();
    }

    /**
     * Configure the command options.
     */
    public function configure()
    {
        $this
            ->setName('simgroep:start-crawler')
            ->addArgument('url', InputArgument::REQUIRED, 'The URL of the website that should be crawled.')
            ->addOption('blacklist', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Which URLs do you want to blacklist?')
            ->addOption('whitelist', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Which URLs do you want to whitelist?')
            ->addOption('corename', null, InputOption::VALUE_OPTIONAL, 'To which core do you want to save data?')
            ->setDescription('This command saves a job to the queue that will cause crawling to start.');
    }

    /**
     * Declares the queue and persists one message.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return boolean
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $metadata = [];

        if (null !== $input->getOption('corename')) {
            $metadata = ['core' => $input->getOption('corename')];
        }

        $job = new CrawlJob(
            $input->getArgument('url'),
            $input->getArgument('url'),
            $input->getOption('blacklist'),
            $input->getOption('whitelist'),
            $metadata
        );

        $this->queue->publishJob($job);
        $output->writeLn('<info>Job is published, start a worker with app/console simgroep:crawl</info>');

        return 0;
    }
}
