<?php

namespace Simgroep\ConcurrentSpiderBundle;

use ArrayAccess;

class PersistableDocument implements ArrayAccess
{
    /**
     * @var array
     */
    private $container;

    /**
     * Constructor.
     *
     * @param array $container
     */
    public function __construct(array $container = array())
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    /**
     * Outputs the data belonging to this document into an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->container;
    }
}

