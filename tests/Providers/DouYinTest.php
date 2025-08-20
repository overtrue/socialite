<?php

namespace Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Exceptions\InvalidArgumentException;
use Overtrue\Socialite\Providers\DouYin;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DouYinTest extends TestCase
{
    public function testDouYinProviderHasCorrectRedirectResponse()
    {
        $provider = new DouYin([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $response = $provider->redirect();

        $this->assertStringStartsWith('https://open.douyin.com/platform/oauth/connect/', $response);
        $this->assertStringContainsString('redirect_uri=http%3A%2F%2Flocalhost%2Fcallback', $response);
        $this->assertStringContainsString('client_key=client_id', $response);
        $this->assertStringContainsString('response_type=code', $response);
        $this->assertStringContainsString('scope=user_info', $response);
    }

    public function testDouYinProviderUrlsAndFields()
    {
        $provider = new DouYin([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $getTokenUrl = new ReflectionMethod(DouYin::class, 'getTokenUrl');
        $getTokenUrl->setAccessible(true);

        $getTokenFields = new ReflectionMethod(DouYin::class, 'getTokenFields');
        $getTokenFields->setAccessible(true);

        $this->assertSame('https://open.douyin.com/oauth/access_token/', $getTokenUrl->invoke($provider));
        
        $this->assertSame([
            'client_key' => 'client_id',
            'client_secret' => 'client_secret',
            'code' => 'test_code',
            'grant_type' => 'authorization_code',
        ], $getTokenFields->invoke($provider, 'test_code'));

        $this->assertSame([
            'client_key' => 'client_id',
            'redirect_uri' => 'http://localhost/callback',
            'scope' => 'user_info',
            'response_type' => 'code',
        ], $provider->getCodeFields());
    }

    public function testWithOpenIdMethod()
    {
        $provider = new DouYin([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $result = $provider->withOpenId('test_openid');
        $this->assertInstanceOf(DouYin::class, $result);
        $this->assertSame($provider, $result);
    }

    public function testTokenFromCodeSuccess()
    {
        $mockProvider = $this->getMockBuilder(DouYin::class)
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
            ->andReturn('{"data": {"error_code": 0, "access_token": "token123", "open_id": "openid123"}}');

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);

        $token = $mockProvider->tokenFromCode('test_code');
        
        $this->assertArrayHasKey('access_token', $token);
        $this->assertSame('token123', $token['access_token']);
    }

    public function testThrowsExceptionWhenOpenIdMissing()
    {
        $mockProvider = $this->getMockBuilder(DouYin::class)
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
            ->andReturn('{"data": {"error_code": 0, "access_token": "token123"}}'); // Missing open_id

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);

        $this->expectException(AuthorizeFailedException::class);
        $this->expectExceptionMessage('Authorization failed: missing open_id in token response');

        $mockProvider->tokenFromCode('test_code');
    }

    public function testThrowsExceptionWhenDataMissing()
    {
        $mockProvider = $this->getMockBuilder(DouYin::class)
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
            ->andReturn('{"error": "invalid_request"}'); // Missing data

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);

        $this->expectException(AuthorizeFailedException::class);
        $this->expectExceptionMessage('Invalid token response');

        $mockProvider->tokenFromCode('test_code');
    }

    public function testThrowsExceptionWhenErrorCodeNonZero()
    {
        $mockProvider = $this->getMockBuilder(DouYin::class)
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
            ->andReturn('{"data": {"error_code": 1, "description": "Error message"}}');

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);

        $this->expectException(AuthorizeFailedException::class);
        $this->expectExceptionMessage('Invalid token response');

        $mockProvider->tokenFromCode('test_code');
    }

    public function testGetUserByTokenSuccess()
    {
        $mockProvider = $this->getMockBuilder(DouYin::class)
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
            ->andReturn('{"data": {"open_id": "test_openid", "nickname": "Test User", "avatar": "http://avatar.url"}}');

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);

        // Set openId first
        $mockProvider->withOpenId('test_openid');

        $getUserByToken = new ReflectionMethod(DouYin::class, 'getUserByToken');
        $getUserByToken->setAccessible(true);
        $result = $getUserByToken->invoke($mockProvider, 'test_token');

        $this->assertArrayHasKey('open_id', $result);
        $this->assertSame('test_openid', $result['open_id']);
        $this->assertArrayHasKey('nickname', $result);
        $this->assertSame('Test User', $result['nickname']);
    }

    public function testGetUserByTokenThrowsExceptionWhenOpenIdEmpty()
    {
        $provider = new DouYin([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('please set the `open_id` before issue the API request.');

        $getUserByToken = new ReflectionMethod(DouYin::class, 'getUserByToken');
        $getUserByToken->setAccessible(true);
        $getUserByToken->invoke($provider, 'test_token');
    }

    public function testMapUserToObject()
    {
        $provider = new DouYin([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $mapUserToObject = new ReflectionMethod(DouYin::class, 'mapUserToObject');
        $mapUserToObject->setAccessible(true);

        $user = [
            'open_id' => 'test_openid',
            'nickname' => 'Test User',
            'avatar' => 'http://avatar.url',
            'email' => 'test@example.com',
        ];

        $result = $mapUserToObject->invoke($provider, $user);

        $this->assertSame('test_openid', $result->getId());
        $this->assertSame('Test User', $result->getName());
        $this->assertSame('Test User', $result->getNickname());
        $this->assertSame('http://avatar.url', $result->getAvatar());
        $this->assertSame('test@example.com', $result->getEmail());
    }
}