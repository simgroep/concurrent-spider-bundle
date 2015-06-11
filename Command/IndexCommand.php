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
use Solarium_Document_ReadWrite;

class IndexCommand extends Command
{
    private $queue;
    private $indexer;
    private $documents;
    private $output;

    public function __construct(
        Queue $queue,
        Indexer $indexer
    ) {
        $this->queue = $queue;
        $this->indexer = $indexer;
        $this->documents = array();

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
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->queue->listen(array($this, 'prepareDocument'));

        return 1;
    }

    /**
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function prepareDocument(AMQPMessage $message)
    {
        $data = json_decode($message->body, true);

        if (null === $data) {
            $this->queue->reject($message);

            return;
        }

        $document = new Solarium_Document_ReadWrite();
        $document->id = $data['document']['id'];
        $document->title = $data['document']['title'];
        $document->tstamp = $data['document']['tstamp'];
        $document->date = $data['document']['date'];
        $document->publishedDate = $data['document']['publishedDate'];
        $document->content = $data['document']['content'];
        $document->url = $data['document']['url'];

        $this->documents[] = $document;

        if (count($this->documents) >= 10) {
            $this->saveDocuments();
        }

        $this->queue->acknowledge($message);
    }

    private function saveDocuments()
    {
        $this->indexer->addDocuments($this->documents);

        $this->output->writeLn(sprintf('<info>%s documents added.</info>', count($this->documents)));
        $this->documents = array();

        return true;
    }
}
