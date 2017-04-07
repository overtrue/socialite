<?php

use Overtrue\Socialite\Providers\WeChatOpenPlatformProvider as RealWeChatOpenPlatformProvider;
use Overtrue\Socialite\Providers\WeChatProvider as RealWeChatProvider;
use Symfony\Component\HttpFoundation\Request;

class WechatProviderTest extends PHPUnit_Framework_TestCase
{
    public function testWeChatProviderHasCorrectlyRedirectResponse()
    {
        $response = (new WeChatProvider(Request::create('foo'), 'client_id', 'client_secret', 'http://localhost/socialite/callback.php'))->redirect();

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $response);
        $this->assertStringStartsWith('https://open.weixin.qq.com/connect/qrconnect', $response->getTargetUrl());
        $this->assertRegExp('/redirect_uri=http%3A%2F%2Flocalhost%2Fsocialite%2Fcallback.php/', $response->getTargetUrl());
    }

    public function testWeChatOpenPlatformProviderHasCorrectlyRedirectResponse()
    {
        $response = (new WeChatOpenPlatformProvider(Request::create('foo'), 'client_id', ['component-app-id', 'component-access-token'], 'http://localhost/callback.php'))->redirect();

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $response);
        $this->assertStringStartsWith('https://open.weixin.qq.com/connect/oauth2/authorize', $response->getTargetUrl());
        $this->assertRegExp('/redirect_uri=http%3A%2F%2Flocalhost%2Fcallback.php/', $response->getTargetUrl());
    }

    public function testWeChatProviderTokenUrlAndRequestFields()
    {
        $provider = new WeChatProvider(Request::create('foo'), 'client_id', 'client_secret', 'http://localhost/socialite/callback.php');

        $this->assertEquals('https://api.weixin.qq.com/sns/oauth2/access_token', $provider->tokenUrl());
        $this->assertSame([
            'appid' => 'client_id',
            'secret' => 'client_secret',
            'code' => 'iloveyou',
            'grant_type' => 'authorization_code',
        ], $provider->tokenFields('iloveyou'));

        $this->assertSame([
            'appid' => 'client_id',
            'redirect_uri' => 'http://localhost/socialite/callback.php',
            'response_type' => 'code',
            'scope' => 'snsapi_login',
            'state' => 'wechat-state',
        ], $provider->codeFields('wechat-state'));
    }

    public function testWeChatOpenPlatformProviderTokenUrlAndRequestFields()
    {
        $provider = new WeChatOpenPlatformProvider(Request::create('foo'), 'client_id', ['component-app-id', 'component-access-token'], 'redirect-url');

        $this->assertEquals('https://api.weixin.qq.com/sns/oauth2/component/access_token', $provider->tokenUrl());
        $this->assertSame([
            'appid' => 'client_id',
            'component_appid' => 'component-app-id',
            'component_access_token' => 'component-access-token',
            'code' => 'code',
            'grant_type' => 'authorization_code',
        ], $provider->tokenFields('code'));
        $this->assertSame([
            'appid' => 'client_id',
            'redirect_uri' => 'redirect-url',
            'response_type' => 'code',
            'scope' => 'snsapi_base',
            'state' => 'state',
            'component_appid' => 'component-app-id',
        ], $provider->codeFields('state'));
    }
}

trait ProviderTrait
{
    public function tokenUrl()
    {
        return $this->getTokenUrl();
    }

    public function tokenFields($code)
    {
        return $this->getTokenFields($code);
    }

    public function codeFields($state = null)
    {
        return $this->getCodeFields($state);
    }
}

class WeChatProvider extends RealWeChatProvider
{
    use ProviderTrait;
}

class WeChatOpenPlatformProvider extends RealWeChatOpenPlatformProvider
{
    use ProviderTrait;
}
