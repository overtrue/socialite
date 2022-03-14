<?php

namespace Overtrue\Socialite;

use Closure;
use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use Overtrue\Socialite\Contracts\FactoryInterface;
use Overtrue\Socialite\Contracts\ProviderInterface;

class SocialiteManager implements FactoryInterface
{
    protected Config $config;
    protected array $resolved = [];
    protected array $customCreators = [];
    protected array $providers = [
        Providers\QQ::NAME => Providers\QQ::class,
        Providers\Tapd::NAME => Providers\Tapd::class,
        Providers\Weibo::NAME => Providers\Weibo::class,
        Providers\Alipay::NAME => Providers\Alipay::class,
        Providers\QCloud::NAME => Providers\QCloud::class,
        Providers\GitHub::NAME => Providers\GitHub::class,
        Providers\Google::NAME => Providers\Google::class,
        Providers\Figma::NAME => Providers\Figma::class,
        Providers\WeChat::NAME => Providers\WeChat::class,
        Providers\Douban::NAME => Providers\Douban::class,
        Providers\WeWork::NAME => Providers\WeWork::class,
        Providers\DouYin::NAME => Providers\DouYin::class,
        Providers\Taobao::NAME => Providers\Taobao::class,
        Providers\FeiShu::NAME => Providers\FeiShu::class,
        Providers\Outlook::NAME => Providers\Outlook::class,
        Providers\Azure::NAME => Providers\Azure::class,
        Providers\Linkedin::NAME => Providers\Linkedin::class,
        Providers\Facebook::NAME => Providers\Facebook::class,
        Providers\DingTalk::NAME => Providers\DingTalk::class,
        Providers\OpenWeWork::NAME => Providers\OpenWeWork::class,
        Providers\Line::NAME => Providers\Line::class,
        Providers\Gitee::NAME => Providers\Gitee::class,
    ];

    #[Pure]
    public function __construct(array $config)
    {
        $this->config = new Config($config);
    }

    public function config(Config $config): static
    {
        $this->config = $config;

        return $this;
    }

    public function create(string $name): ProviderInterface
    {
        $name = strtolower($name);

        if (!isset($this->resolved[$name])) {
            $this->resolved[$name] = $this->createProvider($name);
        }

        return $this->resolved[$name];
    }

    public function extend(string $name, Closure $callback): self
    {
        $this->customCreators[strtolower($name)] = $callback;

        return $this;
    }

    public function getResolvedProviders(): array
    {
        return $this->resolved;
    }

    public function buildProvider(string $provider, array $config): ProviderInterface
    {
        return new $provider($config);
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function createProvider(string $name): ProviderInterface
    {
        $config = $this->config->get($name, []);
        $provider = $config['provider'] ?? $name;

        if (isset($this->customCreators[$provider])) {
            return $this->callCustomCreator($provider, $config);
        }

        if (!$this->isValidProvider($provider)) {
            throw new InvalidArgumentException("Provider [$provider] not supported.");
        }

        return $this->buildProvider($this->providers[$provider] ?? $provider, $config);
    }

    protected function callCustomCreator(string $name, array $config): ProviderInterface
    {
        return $this->customCreators[$name]($config);
    }

    protected function isValidProvider(string $provider): bool
    {
        return isset($this->providers[$provider]) || is_subclass_of($provider, ProviderInterface::class);
    }
}
