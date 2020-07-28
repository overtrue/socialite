<?php

namespace Overtrue\Socialite\Traits;

trait HasAttributes
{
    protected array $attributes = [];

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param string $name
     * @param string $default
     *
     * @return mixed
     */
    public function getAttribute($name, $default = null)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : $default;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return $this
     */
    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * @param array $attributes
     *
     * @return $this
     */
    public function merge(array $attributes)
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->attributes);
    }

    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->setAttribute($offset, $value);
    }

    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }

    public function __get($property)
    {
        return $this->getAttribute($property);
    }

    public function toArray(): array
    {
        return $this->getAttributes();
    }

    public function toJSON(): string
    {
        return \json_encode($this->getAttributes(), JSON_UNESCAPED_UNICODE);
    }
}
