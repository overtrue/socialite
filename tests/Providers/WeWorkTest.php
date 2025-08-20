<?php

use Mockery as m;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Providers\WeWork;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

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

    public function testThrowsExceptionWhenUserIdMissing()
    {
        $provider = new WeWork([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
            'corp_id' => 'corp_id',
            'corp_secret' => 'corp_secret',
        ]);

        // Set detailed to true to trigger the UserId validation
        $detailedProperty = new ReflectionProperty(WeWork::class, 'detailed');
        $detailedProperty->setAccessible(true);
        $detailedProperty->setValue($provider, true);

        // Mock the methods
        $mockProvider = $this->getMockBuilder(WeWork::class)
            ->setConstructorArgs([[
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_url' => 'http://localhost/callback',
                'corp_id' => 'corp_id',
                'corp_secret' => 'corp_secret',
            ]])
            ->onlyMethods(['getApiAccessToken', 'getUser'])
            ->getMock();

        // Set detailed to true
        $detailedProperty->setValue($mockProvider, true);

        $mockProvider->method('getApiAccessToken')->willReturn('api_token');
        $mockProvider->method('getUser')->willReturn([
            'Name' => 'Test User',
            // Missing UserId
        ]);

        $this->expectException(AuthorizeFailedException::class);
        $this->expectExceptionMessage('Authorization failed: missing UserId in user response');

        $mockProvider->userFromCode('test_code');
    }

    public function testThrowsExceptionWhenAccessTokenMissing()
    {
        $provider = new WeWork([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
            'corp_id' => 'corp_id',
            'corp_secret' => 'corp_secret',
        ]);

        // Mock the getHttpClient method to return response without access_token
        $mockProvider = $this->getMockBuilder(WeWork::class)
            ->setConstructorArgs([[
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_url' => 'http://localhost/callback',
                'corp_id' => 'corp_id',
                'corp_secret' => 'corp_secret',
            ]])
            ->onlyMethods(['getHttpClient'])
            ->getMock();

        $mockHttpClient = m::mock();
        $mockResponse = m::mock();
        
        $mockHttpClient->shouldReceive('get')
            ->once()
            ->andReturn($mockResponse);

        $mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn('{"errcode": 0}'); // Missing access_token

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);

        $this->expectException(AuthorizeFailedException::class);
        $this->expectExceptionMessage('Authorization failed: missing access_token in response');

        // Use reflection to test protected method
        $requestApiAccessToken = new \ReflectionMethod(WeWork::class, 'requestApiAccessToken');
        $requestApiAccessToken->setAccessible(true);
        $requestApiAccessToken->invoke($mockProvider);
    }

    protected function tearDown(): void
    {
        m::close();
    }
}
