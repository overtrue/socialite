<?php

/*
 * This file is part of the overtrue/socialite.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\Socialite;

use Closure;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Class SocialiteManager.
 */
class SocialiteManager implements FactoryInterface
{
    /**
     * The configuration.
     *
     * @var \Overtrue\Socialite\Config
     */
    protected $config;

    /**
     * The request instance.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * The initial drivers.
     *
     * @var array
     */
    protected $initialDrivers = [
            'facebook' => 'Facebook',
            'github' => 'GitHub',
            'google' => 'Google',
            'linkedin' => 'Linkedin',
            'weibo' => 'Weibo',
            'qq' => 'QQ',
            'wechat' => 'WeChat',
            'douban' => 'Douban',
    ];

    /**
     * The array of created "drivers".
     *
     * @var array
     */
    protected $drivers = [];

    /**
     * SocialiteManager constructor.
     *
     * @param array                                          $config
     * @param \Symfony\Component\HttpFoundation\Request|null $request
     */
    public function __construct(array $config, Request $request = null)
    {
        $this->config = new Config($config);
        $this->request = $request ?: $this->createDefaultRequest();
    }

    /**
     * Set config instance.
     *
     * @param \Overtrue\Socialite\Config $config
     *
     * @return $this
     */
    public function config(Config $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        throw new InvalidArgumentException('No Socialite driver was specified.');
    }

    /**
     * Get a driver instance.
     *
     * @param string $driver
     *
     * @return mixed
     */
    public function driver($driver = null)
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create a new driver instance.
     *
     * @param string $driver
     *
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    protected function createDriver($driver)
    {
        if (isset($this->initialDrivers[$driver])) {
            $provider = $this->initialDrivers[$driver];
            $provider = __NAMESPACE__.'\\Providers\\'.$provider.'Provider';

            return $this->buildProvider($provider, $this->formatConfig($this->config->get($driver)));
        }

        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param string $driver
     *
     * @return mixed
     */
    protected function callCustomCreator($driver)
    {
        return $this->customCreators[$driver]($this->config);
    }

    /**
     * Create default request instance.
     *
     * @return \Symfony\Component\HttpFoundation\Request
     */
    protected function createDefaultRequest()
    {
        $request = Request::createFromGlobals();
        $session = new Session();

        $request->setSession($session);

        return $request;
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param string   $driver
     * @param \Closure $callback
     *
     * @return $this
     */
    public function extend($driver, Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Get all of the created "drivers".
     *
     * @return array
     */
    public function getDrivers()
    {
        return $this->drivers;
    }

    /**
     * Get a driver instance.
     *
     * @param string $driver
     *
     * @return mixed
     */
    public function with($driver)
    {
        return $this->driver($driver);
    }

    /**
     * Build an OAuth 2 provider instance.
     *
     * @param string $provider
     * @param array  $config
     *
     * @return \Overtrue\Socialite\AbstractProvider
     */
    public function buildProvider($provider, $config)
    {
        return new $provider(
            $this->request, $this->config, $config['client_id'],
            $config['client_secret'], $config['redirect']
        );
    }

    /**
     * Format the server configuration.
     *
     * @param array $config
     *
     * @return array
     */
    public function formatConfig(array $config)
    {
        return array_merge([
            'identifier' => $config['client_id'],
            'secret' => $config['client_secret'],
            'callback_uri' => $config['redirect'],
        ], $config);
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->driver(), $method], $parameters);
    }
}
