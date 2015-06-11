<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\SimgroepConcurrentSpiderBundle;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

class SimgroepConcurrentSpiderBundleTest extends PHPUnit_Framework_TestCase
{
    /**
     * @testdox Tests if a customer compilerpass is registered.
     */
    public function testIfEventDispatcherIsRegistered()
    {
        $container = $this
            ->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')
            ->disableOriginalConstructor()
            ->setMethods(array('addCompilerPass'))
            ->getMock();

        $container
            ->expects($this->once())
            ->method('addCompilerPass')
            ->with(
                $this->isInstanceOf('Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass'),
                $this->equalTo(PassConfig::TYPE_BEFORE_REMOVING)
            );

        $bundle = new SimgroepConcurrentSpiderBundle();
        $bundle->build($container);
    }
}
