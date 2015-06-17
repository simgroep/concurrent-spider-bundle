<?php

namespace Simgroep\ConcurrentSpiderBundle\Command;

use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\Indexer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Solarium_Document_ReadWrite;

class IndexCommand extends Command
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
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue   $queue
     * @param \Simgroep\ConcurrentSpiderBundle\Indexer $indexer
     */
    public function __construct(
        Queue $queue,
        Indexer $indexer
    ) {
        $this->queue = $queue;
        $this->indexer = $indexer;

        parent::__construct();
    }

    /**
     * Configure the command options.
     */
    public function configure()
    {
        $this
            ->setName('simgroep:index')
            ->setDescription("This command starts listening to the index queue and will add documents to SOLR.");
    }

    /**
     * Start a consumer that retrieved documents that have to be saved to the index.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->queue->listen(function ($message) {
            $this->indexer->prepareDocument($message);
            $this->queue->acknowledge($message);
        });

        return 1;
    }
}
