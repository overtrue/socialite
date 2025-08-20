<?php

use Mockery as m;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Exceptions\InvalidArgumentException;
use Overtrue\Socialite\Exceptions\MethodDoesNotSupportException;
use Overtrue\Socialite\Providers\WeWork;
use PHPUnit\Framework\TestCase;

class WeWorkTest extends TestCase
{
    public function testWeWorkProviderOAuthUrl()
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

    public function testWeWorkProviderQrcodeUrl()
    {
        $provider = new WeWork([
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
            'agent_id' => 1000,
        ]);

        $response = $provider->withAgentId(1000)->asQrcode()->redirect();

        $this->assertStringStartsWith('https://open.work.weixin.qq.com/wwopen/sso/qrConnect', $response);
        $this->assertStringContains('appid=CORPID', $response);
        $this->assertStringContains('agentid=1000', $response);
    }

    public function testWeWorkProviderConfiguration()
    {
        $provider = new WeWork([
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
            'base_url' => 'https://custom.base.url',
            'agent_id' => 1000,
        ]);

        $this->assertSame('https://custom.base.url', $provider->getBaseUrl());
    }

    public function testWithAgentIdMethod()
    {
        $provider = new WeWork([
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
        ]);

        $result = $provider->withAgentId(1000);
        $this->assertInstanceOf(WeWork::class, $result);
        $this->assertSame($provider, $result);
    }

    public function testDetailedMethod()
    {
        $provider = new WeWork([
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
        ]);

        $result = $provider->detailed();
        $this->assertInstanceOf(WeWork::class, $result);
        $this->assertSame($provider, $result);
    }

    public function testAsQrcodeMethod()
    {
        $provider = new WeWork([
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
        ]);

        $result = $provider->asQrcode();
        $this->assertInstanceOf(WeWork::class, $result);
        $this->assertSame($provider, $result);
    }

    public function testWithApiAccessTokenMethod()
    {
        $provider = new WeWork([
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
        ]);

        $result = $provider->withApiAccessToken('test_token');
        $this->assertInstanceOf(WeWork::class, $result);
        $this->assertSame($provider, $result);
    }

    public function testThrowsExceptionWhenAgentIdRequiredForQrcode()
    {
        $provider = new WeWork([
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("agent_id is require when qrcode mode or scopes is 'snsapi_privateinfo'");

        $provider->asQrcode()->getAuthUrl();
    }

    public function testThrowsExceptionWhenAgentIdRequiredForPrivateInfo()
    {
        $provider = new WeWork([
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("agent_id is require when qrcode mode or scopes is 'snsapi_privateinfo'");

        $provider->scopes(['snsapi_privateinfo'])->getAuthUrl();
    }

    public function testUserFromCodeSuccess()
    {
        $mockProvider = $this->getMockBuilder(WeWork::class)
            ->setConstructorArgs([[
                'client_id' => 'CORPID',
                'client_secret' => 'client_secret',
                'redirect' => 'REDIRECT_URI',
            ]])
            ->onlyMethods(['getApiAccessToken', 'getUser'])
            ->getMock();

        $mockProvider->method('getApiAccessToken')->willReturn('api_token');
        $mockProvider->method('getUser')->willReturn([
            'UserId' => 'user123',
            'OpenId' => 'openid123',
        ]);

        $user = $mockProvider->userFromCode('test_code');

        $this->assertSame('user123', $user->getId());
    }

    public function testUserFromCodeWithDetailedSuccess()
    {
        $mockProvider = $this->getMockBuilder(WeWork::class)
            ->setConstructorArgs([[
                'client_id' => 'CORPID',
                'client_secret' => 'client_secret',
                'redirect' => 'REDIRECT_URI',
            ]])
            ->onlyMethods(['getApiAccessToken', 'getUser', 'getUserById'])
            ->getMock();

        $mockProvider->method('getApiAccessToken')->willReturn('api_token');
        $mockProvider->method('getUser')->willReturn([
            'UserId' => 'user123',
        ]);
        $mockProvider->method('getUserById')->willReturn([
            'userid' => 'user123',
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user = $mockProvider->detailed()->userFromCode('test_code');

        $this->assertSame('user123', $user->getId());
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
        $detailedProperty = new \ReflectionProperty(WeWork::class, 'detailed');
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

    public function testGetUserByTokenThrowsException()
    {
        $provider = new WeWork([
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
        ]);

        $this->expectException(MethodDoesNotSupportException::class);
        $this->expectExceptionMessage('WeWork doesn\'t support access_token mode');

        $getUserByToken = new \ReflectionMethod(WeWork::class, 'getUserByToken');
        $getUserByToken->setAccessible(true);
        $getUserByToken->invoke($provider, 'test_token');
    }

    public function testMapUserToObjectDetailed()
    {
        $provider = new WeWork([
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
        ]);

        // Set detailed to true
        $detailedProperty = new \ReflectionProperty(WeWork::class, 'detailed');
        $detailedProperty->setAccessible(true);
        $detailedProperty->setValue($provider, true);

        $mapUserToObject = new \ReflectionMethod(WeWork::class, 'mapUserToObject');
        $mapUserToObject->setAccessible(true);

        $user = [
            'userid' => 'user123',
            'name' => 'Test User',
            'avatar' => 'http://avatar.url',
            'email' => 'test@example.com',
        ];

        $result = $mapUserToObject->invoke($provider, $user);

        $this->assertSame('user123', $result->getId());
        $this->assertSame('Test User', $result->getName());
        $this->assertSame('http://avatar.url', $result->getAvatar());
        $this->assertSame('test@example.com', $result->getEmail());
    }

    public function testMapUserToObjectSimple()
    {
        $provider = new WeWork([
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
        ]);

        $mapUserToObject = new \ReflectionMethod(WeWork::class, 'mapUserToObject');
        $mapUserToObject->setAccessible(true);

        $user = [
            'UserId' => 'user123',
            'OpenId' => 'openid123',
        ];

        $result = $mapUserToObject->invoke($provider, $user);

        $this->assertSame('user123', $result->getId());
    }

    protected function tearDown(): void
    {
        m::close();
    }
}
