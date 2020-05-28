<?php

namespace Overtrue\Socialite;

use Closure;
use InvalidArgumentException;
use Overtrue\Socialite\Contracts\FactoryInterface;
use Overtrue\Socialite\Contracts\ProviderInterface;

class SocialiteManager implements FactoryInterface
{
    /**
     * @var \Overtrue\Socialite\Config
     */
    protected $config;

    /**
     * @var array
     */
    protected $customCreators = [];

    /**
     * @var array
     */
    protected $initialDrivers = [
        'facebook' => Providers\FacebookProvider::class,
        'github' => Providers\GitHubProvider::class,
        'google' => Providers\GoogleProvider::class,
        'linkedin' => Providers\LinkedinProvider::class,
        'weibo' => Providers\WeiboProvider::class,
        'qq' => Providers\QQProvider::class,
        'wechat' => Providers\WeChatProvider::class,
        'douban' => Providers\DoubanProvider::class,
        'wework' => Providers\WeWorkProvider::class,
        'outlook' => Providers\OutlookProvider::class,
        'douyin' => Providers\DouYinProvider::class,
        'taobao' => Providers\TaobaoProvider::class,
        'feishu' => Providers\FeiShuProvider::class,
    ];

    /**
     * @var array
     */
    protected $resolved = [];

    /**
     * @param array        $config
     */
    public function __construct(array $config)
    {
        $this->config = new Config($config);
    }

    /**
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
     * @param string $driver
     *
     * @return \Overtrue\Socialite\Contracts\ProviderInterface
     */
    public function driver(string $driver = null): ProviderInterface
    {
        $driver = strtolower($driver);

        if (!isset($this->resolved[$driver])) {
            $this->resolved[$driver] = $this->createDriver($driver);
        }

        return $this->resolved[$driver];
    }

    /**
     * @param string $driver
     *
     * @return ProviderInterface
     * @throws \InvalidArgumentException
     *
     */
    protected function createDriver($driver)
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        if (isset($this->initialDrivers[$driver])) {
            $provider = $this->initialDrivers[$driver];

            return $this->buildProvider($provider, $this->config->get($driver));
        }

        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param string $driver
     *
     * @return ProviderInterface
     */
    protected function callCustomCreator($driver)
    {
        return $this->customCreators[$driver]($this->config);
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
        $driver = strtolower($driver);

        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Get all of the created "drivers".
     *
     * @return \Overtrue\Socialite\Contracts\ProviderInterface[]
     */
    public function getResolvedDrivers()
    {
        return $this->resolved;
    }

    /**
     * Build an OAuth 2 provider instance.
     *
     * @param string $provider
     * @param array  $config
     *
     * @return \Overtrue\Socialite\Contracts\ProviderInterface
     */
    public function buildProvider($provider, $config)
    {
        return new $provider($config);
    }
}
