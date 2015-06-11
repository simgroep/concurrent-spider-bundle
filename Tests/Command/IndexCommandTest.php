<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\Command;

use PHPUnit_Framework_TestCase;
use PhpAmqpLib\Message\AMQPMessage;

class IndexCommandTest extends PHPUnit_Framework_TestCase
{
    /**
     * @testdox Tests if every 10 documents the index saves them.
     */
    public function testIfEveryTenDocumentsAreSaved()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(array('acknowledge', '__destruct'))
            ->getMock();

        $queue
            ->expects($this->exactly(10))
            ->method('acknowledge');

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array())
            ->getMock();

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\IndexCommand')
            ->setConstructorArgs(array($queue, $indexer))
            ->setMethods(array('saveDocuments'))
            ->getMock();

        $command
            ->expects($this->once())
            ->method('saveDocuments');

        for ($i=0; $i<=9; $i++) {
            $body = json_encode(
                array(
                    'document' => array(
                        'id' => rand(0,10),
                        'title' => sha1(rand(0,10)),
                        'tstamp' => date('Y-m-d\TH:i:s\Z'),
                        'date' => date('Y-m-d\TH:i:s\Z'),
                        'publishedDate' => date('Y-m-d\TH:i:s\Z'),
                        'content' => str_repeat(sha1(rand(0,10)), 5),
                        'url' => 'https://www.github.com',
                    )
                )
            );

            $message = new AMQPMessage($body);
            $command->prepareDocument($message);
        }
    }
}
