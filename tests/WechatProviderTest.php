<?php

use Overtrue\Socialite\Providers\WeChatOpenPlatformProvider as RealWeChatOpenPlatformProvider;
use Overtrue\Socialite\Providers\WeChatProvider as RealWeChatProvider;
use Symfony\Component\HttpFoundation\Request;

class WechatProviderTest extends PHPUnit_Framework_TestCase
{
    /** @test */
    public function has_correctly_redirect_response()
    {
        $wechatRedirectResponse = (new WeChatProvider(Request::create('foo'), 'client_id', 'client_secret', 'http://localhost/socialite/callback.php'))
                    ->redirect();
        $wechatOpenPlatformRedirectResponse = (new WeChatOpenPlatformProvider(Request::create('foo'), 'client_id', ['component-app-id', 'component-access-token'], 'redirect-url'))->redirect();

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $wechatRedirectResponse);
        $this->assertStringStartsWith('https://open.weixin.qq.com/connect/qrconnect', $wechatRedirectResponse->getTargetUrl());
        $this->assertRegExp('|redirect_uri='.urlencode('http://localhost/socialite/callback.php').'|', $wechatRedirectResponse->getTargetUrl());

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $wechatOpenPlatformRedirectResponse);
        $this->assertStringStartsWith('https://open.weixin.qq.com/connect/oauth2/authorize', $wechatOpenPlatformRedirectResponse->getTargetUrl());
        $this->assertRegExp('|redirect_uri='.urlencode('redirect-url').'|', $wechatOpenPlatformRedirectResponse->getTargetUrl());
    }

    /** @test */
    public function providers_has_correct_token_url_and_token_fields()
    {
        $wechatProvider = new WeChatProvider(Request::create('foo'), 'client_id', 'client_secret', 'http://localhost/socialite/callback.php');
        $wechatOpenPlatformProvider = new WeChatOpenPlatformProvider(Request::create('foo'), 'client_id', ['component-app-id', 'component-access-token'], 'redirect-url');

        $this->assertEquals('https://api.weixin.qq.com/sns/oauth2/access_token', $wechatProvider->tokenUrl());
        $this->assertSame([
            'appid' => 'client_id',
            'secret' => 'client_secret',
            'code' => 'iloveyou',
            'grant_type' => 'authorization_code',
        ], $wechatProvider->tokenFields('iloveyou'));

        $this->assertEquals('https://api.weixin.qq.com/sns/oauth2/component/access_token', $wechatOpenPlatformProvider->tokenUrl());
        $this->assertSame([
            'appid' => 'client_id',
            'component_appid' => 'component-app-id',
            'component_access_token' => 'component-access-token',
            'code' => 'code',
            'grant_type' => 'authorization_code',
        ], $wechatOpenPlatformProvider->tokenFields('code'));
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
}

class WeChatProvider extends RealWeChatProvider
{
    use ProviderTrait;
}

class WeChatOpenPlatformProvider extends RealWeChatOpenPlatformProvider
{
    use ProviderTrait;
}
