<?php

use Overtrue\Socialite\Providers\WeWork;
use PHPUnit\Framework\TestCase;

class WeWorkTest extends TestCase
{
    public function testOAuthUrl()
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
