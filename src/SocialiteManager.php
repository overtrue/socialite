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
        Providers\QQ::NAME => Providers\QQ::class,
        Providers\Alipay::NAME => Providers\Alipay::class,
        Providers\QCloud::NAME => Providers\QCloud::class,
        Providers\GitHub::NAME => Providers\GitHub::class,
        Providers\Google::NAME => Providers\Google::class,
        Providers\Weibo::NAME => Providers\Weibo::class,
        Providers\WeChat::NAME => Providers\WeChat::class,
        Providers\Douban::NAME => Providers\Douban::class,
        Providers\WeWork::NAME => Providers\WeWork::class,
        Providers\DouYin::NAME => Providers\DouYin::class,
        Providers\Taobao::NAME => Providers\Taobao::class,
        Providers\FeiShu::NAME => Providers\FeiShu::class,
        Providers\Outlook::NAME => Providers\Outlook::class,
        Providers\Linkedin::NAME => Providers\Linkedin::class,
        Providers\Facebook::NAME => Providers\Facebook::class,
        Providers\DingTalk::NAME => Providers\DingTalk::class,
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
