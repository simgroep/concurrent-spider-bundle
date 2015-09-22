<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\PersistableDocument;

class PersistableDocumentTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \Simgroep\ConcurrentSpiderBundle\PersistableDocument
     */
    private $persistableDocument;

    /**
     * set up
     */
    public function setUp()
    {
        parent::setUp();

        $container = ['key1' => 'value1', 'key2' => 'value2'];

        $this->persistableDocument = new PersistableDocument($container);
    }

    /**
     * @test
     */
    public function ifSetCorrectValueOnEmptyKey()
    {
        $this->persistableDocument->offsetSet(null, 'dummyvalue1');
        $this->assertEquals('dummyvalue1', $this->persistableDocument->offsetGet(0));
        $this->assertEquals(3, count($this->persistableDocument->toArray()));
    }

    /**
     * @test
     */
    public function ifSetCorrectValueOnExistingKey()
    {
        $this->persistableDocument->offsetSet('key1', 'dummyvalue2');
        $this->assertEquals('dummyvalue2', $this->persistableDocument->offsetGet('key1'));
        $this->assertEquals(2, count($this->persistableDocument->toArray()));
    }

    /**
     * @test
     */
    public function ifOffsetExistReturnTrueOnExistingKey()
    {
        $this->assertTrue($this->persistableDocument->offsetExists('key1'));
    }

    /**
     * @test
     */
    public function ifOffsetExistReturnFalseOnNotExistingKey()
    {
        $this->assertFalse($this->persistableDocument->offsetExists('dummyKey'));
    }

    /**
     * @test
     */
    public function ifOffsetUnsetSuccessfullyDeleteKey()
    {
        $this->persistableDocument->offsetUnset('key1');
        $this->assertEquals('value2', $this->persistableDocument->offsetGet('key2'));
        $this->assertEquals(1, count($this->persistableDocument->toArray()));
        $this->assertArrayNotHasKey('key1', $this->persistableDocument->toArray());
    }
    
    /**
     * @test
     */
    public function ifOffsetGetNotExistingValueReturnNull()
    {
        $this->assertNull($this->persistableDocument->offsetGet('dummyKeyX'));
    }

}
