<?php

namespace Overtrue\Socialite;

use Closure;
use InvalidArgumentException;
use Overtrue\Socialite\Contracts\FactoryInterface;
use Overtrue\Socialite\Contracts\ProviderInterface;
use Overtrue\Socialite\Providers\AlipayProvider;

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
    protected $drivers = [
        'alipay' =>Providers\AlipayProvider::class,
        'qq' => Providers\QQProvider::class,
        'qcloud' => Providers\QCloudProvider::class,
        'github' => Providers\GitHubProvider::class,
        'google' => Providers\GoogleProvider::class,
        'weibo' => Providers\WeiboProvider::class,
        'wechat' => Providers\WeChatProvider::class,
        'douban' => Providers\DoubanProvider::class,
        'wework' => Providers\WeWorkProvider::class,
        'douyin' => Providers\DouYinProvider::class,
        'taobao' => Providers\TaobaoProvider::class,
        'feishu' => Providers\FeiShuProvider::class,
        'outlook' => Providers\OutlookProvider::class,
        'linkedin' => Providers\LinkedinProvider::class,
        'facebook' => Providers\FacebookProvider::class,
        'dingtalk' => Providers\DingTalkProvider::class,
    ];

    /**
     * @var array
     */
    protected $resolved = [];

    /**
     * @param array $config
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
     * @throws \InvalidArgumentException
     *
     * @return ProviderInterface
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
     * Call a custom driver creator.
     *
     * @param string $driver
     *
     * @return ProviderInterface
     */
    protected function callCustomCreator(string $driver): ProviderInterface
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
    public function extend(string $driver, Closure $callback): self
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
    public function getResolvedDrivers(): array
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
    public function buildProvider(string $provider, array $config): ProviderInterface
    {
        return new $provider($config);
    }
}
