<?php

namespace Tests\Providers;

use Mockery as m;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Providers\QQ;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class QQTest extends TestCase
{
    public function testQQProviderHasCorrectRedirectResponse()
    {
        $provider = new QQ([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $response = $provider->redirect();

        $this->assertStringStartsWith('https://graph.qq.com/oauth2.0/authorize', $response);
        $this->assertStringContains('redirect_uri=http%3A%2F%2Flocalhost%2Fcallback', $response);
        $this->assertStringContains('client_id=client_id', $response);
        $this->assertStringContains('response_type=code', $response);
        $this->assertStringContains('scope=get_user_info', $response);
    }

    public function testQQProviderTokenUrlAndRequestFields()
    {
        $provider = new QQ([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $getTokenUrl = new \ReflectionMethod(QQ::class, 'getTokenUrl');
        $getTokenUrl->setAccessible(true);

        $getTokenFields = new \ReflectionMethod(QQ::class, 'getTokenFields');
        $getTokenFields->setAccessible(true);

        $getCodeFields = new \ReflectionMethod(QQ::class, 'getCodeFields');
        $getCodeFields->setAccessible(true);

        $this->assertSame('https://graph.qq.com/oauth2.0/token', $getTokenUrl->invoke($provider));
        
        $this->assertSame([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'code' => 'test_code',
            'redirect_uri' => 'http://localhost/callback',
            'grant_type' => 'authorization_code',
        ], $getTokenFields->invoke($provider, 'test_code'));

        $this->assertSame([
            'client_id' => 'client_id',
            'redirect_uri' => 'http://localhost/callback',
            'scope' => 'get_user_info',
            'response_type' => 'code',
            'state' => 'qq-state',
        ], $getCodeFields->invoke($provider->withState('qq-state')));
    }

    public function testWithUnionIdMethod()
    {
        $provider = new QQ([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $result = $provider->withUnionId();
        $this->assertInstanceOf(QQ::class, $result);
        $this->assertSame($provider, $result);
    }

    public function testTokenFromCodeMethod()
    {
        $mockProvider = $this->getMockBuilder(QQ::class)
            ->setConstructorArgs([[
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_url' => 'http://localhost/callback',
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
            ->andReturn('access_token=test_token&refresh_token=refresh_token&expires_in=7200');

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);

        $token = $mockProvider->tokenFromCode('test_code');
        
        $this->assertArrayHasKey('access_token', $token);
        $this->assertSame('test_token', $token['access_token']);
    }

    public function testGetUserByTokenSuccess()
    {
        $mockProvider = $this->getMockBuilder(QQ::class)
            ->setConstructorArgs([[
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_url' => 'http://localhost/callback',
            ]])
            ->onlyMethods(['getHttpClient'])
            ->getMock();

        $mockHttpClient = m::mock();
        $mockResponse1 = m::mock();
        $mockResponse2 = m::mock();
        
        // First call to get openid
        $mockHttpClient->shouldReceive('get')
            ->once()
            ->with('https://graph.qq.com/oauth2.0/me', m::any())
            ->andReturn($mockResponse1);

        $mockResponse1->shouldReceive('getBody')
            ->once()
            ->andReturn('{"openid": "test_openid", "unionid": "test_unionid"}');

        // Second call to get user info
        $mockHttpClient->shouldReceive('get')
            ->once()
            ->with('https://graph.qq.com/user/get_user_info', m::any())
            ->andReturn($mockResponse2);

        $mockResponse2->shouldReceive('getBody')
            ->once()
            ->andReturn('{"ret": 0, "nickname": "Test User", "figureurl_qq_2": "http://avatar.url"}');

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);

        // Use reflection to test protected method
        $getUserByToken = new ReflectionMethod(QQ::class, 'getUserByToken');
        $getUserByToken->setAccessible(true);
        $result = $getUserByToken->invoke($mockProvider, 'test_token');

        $this->assertArrayHasKey('openid', $result);
        $this->assertSame('test_openid', $result['openid']);
        $this->assertArrayHasKey('unionid', $result);
        $this->assertSame('test_unionid', $result['unionid']);
    }

    public function testThrowsExceptionWhenOpenidMissing()
    {
        $mockProvider = $this->getMockBuilder(QQ::class)
            ->setConstructorArgs([[
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_url' => 'http://localhost/callback',
            ]])
            ->onlyMethods(['getHttpClient'])
            ->getMock();

        $mockHttpClient = m::mock();
        $mockResponse = m::mock();
        
        // First call to get openid - return response missing openid
        $mockHttpClient->shouldReceive('get')
            ->once()
            ->with('https://graph.qq.com/oauth2.0/me', m::any())
            ->andReturn($mockResponse);

        $mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn('{"error": "invalid_request"}');

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);

        $this->expectException(AuthorizeFailedException::class);
        $this->expectExceptionMessage('Authorization failed: missing openid in token response');

        // Use reflection to test protected method
        $getUserByToken = new ReflectionMethod(QQ::class, 'getUserByToken');
        $getUserByToken->setAccessible(true);
        $getUserByToken->invoke($mockProvider, 'test_token');
    }

    public function testThrowsExceptionWhenUserInfoReturnsFails()
    {
        $mockProvider = $this->getMockBuilder(QQ::class)
            ->setConstructorArgs([[
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_url' => 'http://localhost/callback',
            ]])
            ->onlyMethods(['getHttpClient'])
            ->getMock();

        $mockHttpClient = m::mock();
        $mockResponse1 = m::mock();
        $mockResponse2 = m::mock();
        
        // First call to get openid - success
        $mockHttpClient->shouldReceive('get')
            ->once()
            ->with('https://graph.qq.com/oauth2.0/me', m::any())
            ->andReturn($mockResponse1);

        $mockResponse1->shouldReceive('getBody')
            ->once()
            ->andReturn('{"openid": "test_openid"}');

        // Second call to get user info - failure
        $mockHttpClient->shouldReceive('get')
            ->once()
            ->with('https://graph.qq.com/user/get_user_info', m::any())
            ->andReturn($mockResponse2);

        $mockResponse2->shouldReceive('getBody')
            ->once()
            ->andReturn('{"ret": 1, "msg": "parameter error"}');

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);

        $this->expectException(AuthorizeFailedException::class);

        // Use reflection to test protected method
        $getUserByToken = new ReflectionMethod(QQ::class, 'getUserByToken');
        $getUserByToken->setAccessible(true);
        $getUserByToken->invoke($mockProvider, 'test_token');
    }

    public function testMapUserToObject()
    {
        $provider = new QQ([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $mapUserToObject = new ReflectionMethod(QQ::class, 'mapUserToObject');
        $mapUserToObject->setAccessible(true);

        $user = [
            'openid' => 'test_openid',
            'nickname' => 'Test User',
            'email' => 'test@example.com',
            'figureurl_qq_2' => 'http://avatar.url',
        ];

        $result = $mapUserToObject->invoke($provider, $user);

        $this->assertSame('test_openid', $result->getId());
        $this->assertSame('Test User', $result->getName());
        $this->assertSame('Test User', $result->getNickname());
        $this->assertSame('test@example.com', $result->getEmail());
        $this->assertSame('http://avatar.url', $result->getAvatar());
    }

    protected function tearDown(): void
    {
        m::close();
    }
}