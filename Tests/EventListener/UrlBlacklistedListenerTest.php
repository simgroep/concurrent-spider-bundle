<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\EventListener;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\EventListener\UrlBlacklistedListener;

class UrlBlacklistedListenerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function isMessageLoggedWhenUrlIsBlacklisted()
    {
        $logger = $this
            ->getMockBuilder('Monolog\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['info'])
            ->getMock();

        $logger
            ->expects($this->once())
            ->method('info')
            ->with($this->equalTo('blacklisted https://github.com'), $this->equalTo(['tags' => ['github.com']]));

        $argument = $this
            ->getMockBuilder('VDB\Uri\Uri')
            ->disableOriginalConstructor()
            ->setMethods(['toString'])
            ->getMock();

        $argument
            ->expects($this->once())
            ->method('toString')
            ->will($this->returnValue('https://github.com'));

        $event = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\GenericEvent')
            ->disableOriginalConstructor()
            ->setMethods(['getArgument'])
            ->getMock();

        $event
            ->expects($this->once())
            ->method('getArgument')
            ->will($this->returnValue($argument));

        $listener = new UrlBlacklistedListener($logger);
        $listener->onBlacklisted($event);
    }
}
