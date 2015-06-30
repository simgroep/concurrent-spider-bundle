<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\Command;

use PHPUnit_Framework_TestCase;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class IndexCommandTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function execute()
    {
        $message = $this
            ->getMockBuilder('PhpAmqpLib\Message\AMQPMessage')
            ->setMethods(null)
            ->getMock();
        $message->body = '{"metadata":{"core":"dummyCoreName"}}';

        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['rejectMessage', '__destruct', 'listen', 'acknowledge'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('listen')
            ->will($this->returnCallback(function($callback) use($message) {
                $callback($message);
            }));

        $queue
            ->expects($this->once())
            ->method('acknowledge');

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexed', 'prepareDocument', 'setMetadata'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('prepareDocument');

        $indexer
            ->expects($this->once())
            ->method('setMetadata')
            ->with(['core' => 'dummyCoreName']);

        /** @var \Simgroep\ConcurrentSpiderBundle\Command\IndexCommand $command */
        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\IndexCommand')
            ->setConstructorArgs(array($queue, $indexer, []))
            ->setMethods(null)
            ->getMock();

        $input = new StringInput('');
        $output = new NullOutput();
        $command->run($input, $output);
    }

}
