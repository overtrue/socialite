<?php

use Overtrue\Socialite\Providers\WeWork;
use PHPUnit\Framework\TestCase;

class WeWorkTest extends TestCase
{
    public function testQrConnect()
    {
        $response = (new WeWork([
            'client_id' => 'ww100000a5f2191',
            'client_secret' => 'client_secret',
            'redirect' => 'http://www.oa.com',
        ]))
            ->setAgentId('1000000')
            ->redirect();

        $this->assertSame('https://open.work.weixin.qq.com/wwopen/sso/qrConnect?appid=ww100000a5f2191&agentid=1000000&redirect_uri=http%3A%2F%2Fwww.oa.com#wechat_redirect', $response);
    }

    public function testOAuthWithAgentId()
    {
        $response = (new WeWork([
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
        ]))
            ->scopes(['snsapi_base'])
            ->redirect();

        $this->assertSame('https://open.weixin.qq.com/connect/oauth2/authorize?appid=CORPID&redirect_uri=REDIRECT_URI&response_type=code&scope=snsapi_base#wechat_redirect', $response);
    }

    public function testOAuthWithoutAgentId()
    {
        $response = (new WeWork([
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
        ]))
            ->scopes(['snsapi_base'])
            ->redirect();

        $this->assertSame('https://open.weixin.qq.com/connect/oauth2/authorize?appid=CORPID&redirect_uri=REDIRECT_URI&response_type=code&scope=snsapi_base#wechat_redirect', $response);
    }
}
