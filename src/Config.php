<?php

namespace Overtrue\Socialite;

use ArrayAccess;
use InvalidArgumentException;

class Config implements ArrayAccess, \JsonSerializable
{
    /**
     * @var array
     */
    protected array $config;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $config = $this->config;

        if (is_null($key)) {
            return $config;
        }

        if (isset($config[$key])) {
            return $config[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($config) || !array_key_exists($segment, $config)) {
                return $default;
            }
            $config = $config[$segment];
        }

        return $config;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return array
     */
    public function set(string $key, $value)
    {
        if (is_null($key)) {
            throw new InvalidArgumentException('Invalid config key.');
        }

        $keys = explode('.', $key);
        $config = &$this->config;

        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($config[$key]) || !is_array($config[$key])) {
                $config[$key] = [];
            }
            $config = &$config[$key];
        }

        $config[array_shift($keys)] = $value;

        return $config;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->config);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->set($offset, null);
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->config;
    }

    public function __toString()
    {
        return \json_encode($this, \JSON_UNESCAPED_UNICODE);
    }
}
