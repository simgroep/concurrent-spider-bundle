<?php

namespace Simgroep\ConcurrentSpiderBundle\Command;

use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Simgroep\ConcurrentSpiderBundle\Queue;

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
        $data = array(
            'uri' => $input->getArgument('url'),
            'base_url' => $input->getArgument('url'),
        );
        $message = new AMQPMessage(json_encode($data), array('delivery_mode' => 1));

        $this->queue->publish($message);

        $output->writeLn('<info>Job is published, start a worker with app/console simgroep:crawl</info>');

        return 0;
    }
}
