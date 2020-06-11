<?php

namespace Overtrue\Socialite;

use Closure;
use InvalidArgumentException;
use Overtrue\Socialite\Contracts\FactoryInterface;
use Overtrue\Socialite\Contracts\ProviderInterface;

class SocialiteManager implements FactoryInterface
{
    protected Config $config;
    protected array $customCreators = [];
    protected array $drivers = [
        Providers\QQProvider::NAME => Providers\QQProvider::class,
        Providers\AlipayProvider::NAME => Providers\AlipayProvider::class,
        Providers\QCloudProvider::NAME => Providers\QCloudProvider::class,
        Providers\GitHubProvider::NAME => Providers\GitHubProvider::class,
        Providers\GoogleProvider::NAME => Providers\GoogleProvider::class,
        Providers\WeiboProvider::NAME => Providers\WeiboProvider::class,
        Providers\WeChatProvider::NAME => Providers\WeChatProvider::class,
        Providers\DoubanProvider::NAME => Providers\DoubanProvider::class,
        Providers\WeWorkProvider::NAME => Providers\WeWorkProvider::class,
        Providers\DouYinProvider::NAME => Providers\DouYinProvider::class,
        Providers\TaobaoProvider::NAME => Providers\TaobaoProvider::class,
        Providers\FeiShuProvider::NAME => Providers\FeiShuProvider::class,
        Providers\OutlookProvider::NAME => Providers\OutlookProvider::class,
        Providers\LinkedinProvider::NAME => Providers\LinkedinProvider::class,
        Providers\FacebookProvider::NAME => Providers\FacebookProvider::class,
        Providers\DingTalkProvider::NAME => Providers\DingTalkProvider::class,
    ];
    protected array $resolved = [];

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
    public function driver(string $driver): ProviderInterface
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
    protected function createDriver(string $driver)
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        if (isset($this->drivers[$driver])) {
            $provider = $this->drivers[$driver];

            return $this->buildProvider($provider, $this->config->get($driver, []));
        }

        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }

    /**
     * @param string $driver
     *
     * @return ProviderInterface
     */
    protected function callCustomCreator(string $driver): ProviderInterface
    {
        return $this->customCreators[$driver]($this->config);
    }

    /**
     * @param string   $driver
     * @param \Closure $callback
     *
     * @return $this
     */
    public function extend(string $driver, Closure $callback): self
    {
        $driver = strtolower($driver);

        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * @return \Overtrue\Socialite\Contracts\ProviderInterface[]
     */
    public function getResolvedDrivers(): array
    {
        return $this->resolved;
    }

    /**
     * @param string $provider
     * @param array  $config
     *
     * @return \Overtrue\Socialite\Contracts\ProviderInterface
     */
    public function buildProvider(string $provider, array $config): ProviderInterface
    {
        return new $provider($config);
    }
}
