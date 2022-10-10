<?php

use Overtrue\Socialite\Providers\WeChat;
use PHPUnit\Framework\TestCase;

// here we need loaded the symbols first.
\class_exists(\Overtrue\Socialite\Contracts\FactoryInterface::class);

class WechatTest extends TestCase
{
    public function testWeChatProviderHasCorrectlyRedirectResponse()
    {
        $response = (new WeChat([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/socialite/callback.php',
        ]))->redirect();

        $this->assertStringStartsWith('https://open.weixin.qq.com/connect/qrconnect', $response);
        $this->assertMatchesRegularExpression('/redirect_uri=http%3A%2F%2Flocalhost%2Fsocialite%2Fcallback.php/', $response);
    }

    public function testWeChatProviderTokenUrlAndRequestFields()
    {
        $provider = new WeChat([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/socialite/callback.php',
        ]);

        $getTokenUrl = new ReflectionMethod(WeChat::class, 'getTokenUrl');
        $getTokenUrl->setAccessible(true);

        $getTokenFields = new ReflectionMethod(WeChat::class, 'getTokenFields');
        $getTokenFields->setAccessible(true);

        $getCodeFields = new ReflectionMethod(WeChat::class, 'getCodeFields');
        $getCodeFields->setAccessible(true);

        $this->assertSame('https://api.weixin.qq.com/sns/oauth2/access_token', $getTokenUrl->invoke($provider));
        $this->assertSame([
            'appid' => 'client_id',
            'secret' => 'client_secret',
            'code' => 'iloveyou',
            'grant_type' => 'authorization_code',
        ], $getTokenFields->invoke($provider, 'iloveyou'));

        $this->assertSame([
            'appid' => 'client_id',
            'redirect_uri' => 'http://localhost/socialite/callback.php',
            'response_type' => 'code',
            'scope' => 'snsapi_login',
            'state' => 'wechat-state',
            'connect_redirect' => 1,
        ], $getCodeFields->invoke($provider->withState('wechat-state')));
    }

    public function testOpenPlatformComponent()
    {
        $provider = new WeChat([
            'client_id' => 'client_id',
            'client_secret' => null,
            'redirect' => 'redirect-url',
            'component' => [
                'id' => 'component-app-id',
                'token' => 'token',
            ],
        ]);
        $getTokenUrl = new ReflectionMethod(WeChat::class, 'getTokenUrl');
        $getTokenUrl->setAccessible(true);

        $getTokenFields = new ReflectionMethod(WeChat::class, 'getTokenFields');
        $getTokenFields->setAccessible(true);

        $getCodeFields = new ReflectionMethod(WeChat::class, 'getCodeFields');
        $getCodeFields->setAccessible(true);

        $this->assertSame([
            'appid' => 'client_id',
            'redirect_uri' => 'redirect-url',
            'response_type' => 'code',
            'scope' => 'snsapi_base',
            'state' => 'state',
            'connect_redirect' => 1,
            'component_appid' => 'component-app-id',
        ], $getCodeFields->invoke($provider->withState('state')));

        $this->assertSame([
            'appid' => 'client_id',
            'component_appid' => 'component-app-id',
            'component_access_token' => 'token',
            'code' => 'simcode',
            'grant_type' => 'authorization_code',
        ], $getTokenFields->invoke($provider, 'simcode'));

        $this->assertSame('https://api.weixin.qq.com/sns/oauth2/component/access_token', $getTokenUrl->invoke($provider));
    }

    public function testOpenPlatformComponentWithCustomParameters()
    {
        $provider = new WeChat([
            'client_id' => 'client_id',
            'client_secret' => null,
            'redirect' => 'redirect-url',
            'component' => [
                'id' => 'component-app-id',
                'token' => 'token',
            ],
        ]);

        $getCodeFields = new ReflectionMethod(WeChat::class, 'getCodeFields');
        $getCodeFields->setAccessible(true);

        $provider->with(['foo' => 'bar']);

        $fields = $getCodeFields->invoke($provider->withState('wechat-state'));
        $this->assertArrayHasKey('foo', $fields);
        $this->assertSame('bar', $fields['foo']);
    }
}
