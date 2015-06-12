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
    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Queue
     */
    private $queue;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Indexer
     */
    private $indexer;

    /**
     * @var array
     */
    private $documents;

    /**
     * @var Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * @var array
     */
    private $mapping;


    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue   $queue
     * @param \Simgroep\ConcurrentSpiderBundle\Indexer $indexer
     * @param array                                    $mapping
     */
    public function __construct(
        Queue $queue,
        Indexer $indexer,
        array $mapping
    ) {
        $this->queue = $queue;
        $this->indexer = $indexer;
        $this->mapping = $mapping;
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
     * Start a consumer that retrieved documents that have to be saved to the index.
     *
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
     * Make a document ready to be indexed.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function prepareDocument(AMQPMessage $message)
    {
        $data = json_decode($message->body, true);

        $document = new Solarium_Document_ReadWrite();

        foreach ($this->mapping as $field => $solrField) {
            $document->addField($solrField, $data['document'][$field]);
        }

        $this->documents[] = $document;

        if (count($this->documents) >= 10) {
            $this->saveDocuments();
        }

        $this->queue->acknowledge($message);
    }

    /**
     * Save a list of documents.
     */
    protected function saveDocuments()
    {
        $this->indexer->addDocuments($this->documents);

        $this->output->writeLn(sprintf('<info>%s documents added.</info>', count($this->documents)));
        $this->documents = array();
    }
}
