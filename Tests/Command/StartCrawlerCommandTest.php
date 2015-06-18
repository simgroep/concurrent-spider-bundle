<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\Command;

use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class StartCrawlerCommandTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function execute()
    {
        $data = [
            'uri' => 'http://simgroep.nl',
            'base_url' => 'http://simgroep.nl',
            'blacklist' => [],
        ];
        $message = new AMQPMessage(json_encode($data), ['delivery_mode' => 1]);

        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['rejectMessage', '__destruct', 'publish'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('publish')
            ->with($message);

        /** @var \Simgroep\ConcurrentSpiderBundle\Command\StartCrawlerCommand $command */
        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\StartCrawlerCommand')
            ->setConstructorArgs([$queue])
            ->setMethods(null)
            ->getMock();

        $input = new StringInput('http://simgroep.nl');
        $output = new NullOutput();
        $command->run($input, $output);
    }
}
