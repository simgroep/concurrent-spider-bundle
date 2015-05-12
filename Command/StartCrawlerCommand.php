<?php

namespace Simgroep\ConcurrentSpiderBundle\Command;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class StartCrawlerCommand extends Command
{
    public function __construct(AMQPConnection $connection)
    {
        $this->connection = $connection;

        parent::__construct();
    }

    public function configure()
    {
        $this
            ->setName('simgroep:start-crawler')
            ->addArgument('url', InputArgument::REQUIRED, 'The URL of the website that should be crawled.')
            ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'The name of the queue.', 'discoved_urls')
            ->setDescription('This command saves a job to the queue that will cause crawling to start.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $channel = $this->connection->channel();
        $channel->queue_declare($input->getOption('queue'), false, true, false, false, true);
        $channel->basic_qos(null, 1, null);

        $data = array(
            'uri' => $input->getArgument('url'),
            'base_url' => $input->getArgument('url'),
        );

        $message = new AMQPMessage(json_encode($data), array('delivery_mode' => 2));
        $channel->basic_publish($message, '', $input->getOption('queue'));

        $output->writeLn('<info>Job is published, start a worker with app/console simgroep:crawl</info>');

        $channel->close();
        $this->connection->close();
    }
}
