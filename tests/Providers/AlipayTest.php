<?php

namespace Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Exceptions\BadRequestException;
use Overtrue\Socialite\Exceptions\InvalidArgumentException;
use Overtrue\Socialite\Providers\Alipay;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AlipayTest extends TestCase
{
    public function testAlipayProviderHasCorrectRedirectResponse()
    {
        $provider = new Alipay([
            'client_id' => 'client_id',
            'rsa_private_key' => 'private_key',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $response = $provider->redirect();

        $this->assertStringStartsWith('https://openauth.alipay.com/oauth2/publicAppAuthorize.htm', $response);
        $this->assertStringContainsString('redirect_uri=http%3A%2F%2Flocalhost%2Fcallback', $response);
        $this->assertStringContainsString('app_id=client_id', $response);
        $this->assertStringContainsString('scope=auth_user', $response);
    }

    public function testAlipayProviderSandboxMode()
    {
        $provider = new Alipay([
            'client_id' => 'client_id',
            'rsa_private_key' => 'private_key',
            'redirect_url' => 'http://localhost/callback',
            'sandbox' => true,
        ]);

        $response = $provider->redirect();

        $this->assertStringStartsWith('https://openauth.alipaydev.com/oauth2/publicAppAuthorize.htm', $response);
    }

    public function testAlipayProviderUrlsAndFields()
    {
        $provider = new Alipay([
            'client_id' => 'client_id',
            'rsa_private_key' => 'private_key',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $getTokenUrl = new ReflectionMethod(Alipay::class, 'getTokenUrl');
        $getTokenUrl->setAccessible(true);

        $this->assertSame('https://openapi.alipay.com/gateway.do', $getTokenUrl->invoke($provider));
    }

    public function testGetPublicFields()
    {
        $provider = new Alipay([
            'client_id' => 'test_app_id',
            'rsa_private_key' => 'private_key',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $fields = $provider->getPublicFields('test.method');

        $this->assertArrayHasKey('app_id', $fields);
        $this->assertSame('test_app_id', $fields['app_id']);
        $this->assertArrayHasKey('method', $fields);
        $this->assertSame('test.method', $fields['method']);
        $this->assertArrayHasKey('format', $fields);
        $this->assertSame('json', $fields['format']);
        $this->assertArrayHasKey('charset', $fields);
        $this->assertSame('UTF-8', $fields['charset']);
        $this->assertArrayHasKey('sign_type', $fields);
        $this->assertSame('RSA2', $fields['sign_type']);
        $this->assertArrayHasKey('timestamp', $fields);
        $this->assertArrayHasKey('version', $fields);
        $this->assertSame('1.0', $fields['version']);
    }

    public function testGetCodeFieldsThrowsExceptionWhenNoRedirectUrl()
    {
        $provider = new Alipay([
            'client_id' => 'client_id',
            'rsa_private_key' => 'private_key',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Please set the correct redirect URL refer which was on the Alipay Official Admin pannel.');

        $getCodeFields = new ReflectionMethod(Alipay::class, 'getCodeFields');
        $getCodeFields->setAccessible(true);
        $getCodeFields->invoke($provider);
    }

    public function testGetCodeFields()
    {
        $provider = new Alipay([
            'app_id' => 'test_app_id',
            'rsa_private_key' => 'private_key',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $getCodeFields = new ReflectionMethod(Alipay::class, 'getCodeFields');
        $getCodeFields->setAccessible(true);
        $fields = $getCodeFields->invoke($provider);

        $this->assertArrayHasKey('app_id', $fields);
        $this->assertSame('test_app_id', $fields['app_id']);
        $this->assertArrayHasKey('scope', $fields);
        $this->assertSame('auth_user', $fields['scope']);
        $this->assertArrayHasKey('redirect_uri', $fields);
        $this->assertSame('http://localhost/callback', $fields['redirect_uri']);
    }

    public function testSignWithSHA256RSAThrowsExceptionWhenNoPrivateKey()
    {
        $provider = new Alipay([
            'client_id' => 'client_id',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no RSA private key set.');

        $signWithSHA256RSA = new ReflectionMethod(Alipay::class, 'signWithSHA256RSA');
        $signWithSHA256RSA->setAccessible(true);
        $signWithSHA256RSA->invoke($provider, 'test_content', '');
    }

    public function testBuildParams()
    {
        $params = [
            'app_id' => 'test_app_id',
            'method' => 'test.method',
            'sign' => 'signature',
            'timestamp' => '2024-01-01 12:00:00',
        ];

        $result = Alipay::buildParams($params);
        $this->assertSame('app_id=test_app_id&method=test.method&timestamp=2024-01-01 12:00:00', $result);

        $resultWithUrlencode = Alipay::buildParams($params, true);
        $this->assertStringContainsString('timestamp=2024-01-01%2012%3A00%3A00', $resultWithUrlencode);
    }

    public function testTokenFromCodeSuccess()
    {
        $mockProvider = $this->getMockBuilder(Alipay::class)
            ->setConstructorArgs([[
                'client_id' => 'client_id',
                'rsa_private_key' => 'private_key',
                'redirect_url' => 'http://localhost/callback',
            ]])
            ->onlyMethods(['getHttpClient', 'generateSign'])
            ->getMock();

        $mockHttpClient = m::mock();
        $mockResponse = m::mock();
        
        $mockHttpClient->shouldReceive('post')
            ->once()
            ->andReturn($mockResponse);

        $mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn('{"alipay_system_oauth_token_response": {"access_token": "token123", "user_id": "user123"}}');

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);
        $mockProvider->method('generateSign')->willReturn('test_signature');

        $token = $mockProvider->tokenFromCode('test_code');
        
        $this->assertArrayHasKey('access_token', $token);
        $this->assertSame('token123', $token['access_token']);
    }

    public function testThrowsExceptionWhenTokenResponseMissing()
    {
        $mockProvider = $this->getMockBuilder(Alipay::class)
            ->setConstructorArgs([[
                'client_id' => 'client_id',
                'rsa_private_key' => 'private_key',
                'redirect_url' => 'http://localhost/callback',
            ]])
            ->onlyMethods(['getHttpClient', 'generateSign'])
            ->getMock();

        $mockHttpClient = m::mock();
        $mockResponse = m::mock();
        
        $mockHttpClient->shouldReceive('post')
            ->once()
            ->andReturn($mockResponse);

        $mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn('{"success": true}'); // Missing alipay_system_oauth_token_response

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);
        $mockProvider->method('generateSign')->willReturn('test_signature');

        $this->expectException(AuthorizeFailedException::class);
        $this->expectExceptionMessage('Authorization failed: missing alipay_system_oauth_token_response in response');

        $mockProvider->tokenFromCode('test_code');
    }

    public function testThrowsExceptionWhenErrorResponse()
    {
        $mockProvider = $this->getMockBuilder(Alipay::class)
            ->setConstructorArgs([[
                'client_id' => 'client_id',
                'rsa_private_key' => 'private_key',
                'redirect_url' => 'http://localhost/callback',
            ]])
            ->onlyMethods(['getHttpClient', 'generateSign'])
            ->getMock();

        $mockHttpClient = m::mock();
        $mockResponse = m::mock();
        
        $mockHttpClient->shouldReceive('post')
            ->once()
            ->andReturn($mockResponse);

        $mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn('{"error_response": {"code": "40002", "msg": "Invalid parameter"}}');

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);
        $mockProvider->method('generateSign')->willReturn('test_signature');

        $this->expectException(BadRequestException::class);

        $mockProvider->tokenFromCode('test_code');
    }

    public function testGetUserByTokenSuccess()
    {
        $mockProvider = $this->getMockBuilder(Alipay::class)
            ->setConstructorArgs([[
                'client_id' => 'client_id',
                'rsa_private_key' => 'private_key',
                'redirect_url' => 'http://localhost/callback',
            ]])
            ->onlyMethods(['getHttpClient', 'generateSign'])
            ->getMock();

        $mockHttpClient = m::mock();
        $mockResponse = m::mock();
        
        $mockHttpClient->shouldReceive('post')
            ->once()
            ->andReturn($mockResponse);

        $mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn('{"alipay_user_info_share_response": {"user_id": "user123", "nick_name": "Test User", "avatar": "http://avatar.url"}}');

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);
        $mockProvider->method('generateSign')->willReturn('test_signature');

        $getUserByToken = new ReflectionMethod(Alipay::class, 'getUserByToken');
        $getUserByToken->setAccessible(true);
        $result = $getUserByToken->invoke($mockProvider, 'test_token');

        $this->assertArrayHasKey('user_id', $result);
        $this->assertSame('user123', $result['user_id']);
        $this->assertArrayHasKey('nick_name', $result);
        $this->assertSame('Test User', $result['nick_name']);
    }

    public function testGetUserByTokenThrowsExceptionWhenErrorResponse()
    {
        $mockProvider = $this->getMockBuilder(Alipay::class)
            ->setConstructorArgs([[
                'client_id' => 'client_id',
                'rsa_private_key' => 'private_key',
                'redirect_url' => 'http://localhost/callback',
            ]])
            ->onlyMethods(['getHttpClient', 'generateSign'])
            ->getMock();

        $mockHttpClient = m::mock();
        $mockResponse = m::mock();
        
        $mockHttpClient->shouldReceive('post')
            ->once()
            ->andReturn($mockResponse);

        $mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn('{"error_response": {"code": "40002", "msg": "Invalid parameter"}}');

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);
        $mockProvider->method('generateSign')->willReturn('test_signature');

        $this->expectException(BadRequestException::class);

        $getUserByToken = new ReflectionMethod(Alipay::class, 'getUserByToken');
        $getUserByToken->setAccessible(true);
        $getUserByToken->invoke($mockProvider, 'test_token');
    }

    public function testMapUserToObject()
    {
        $provider = new Alipay([
            'client_id' => 'client_id',
            'rsa_private_key' => 'private_key',
            'redirect_url' => 'http://localhost/callback',
        ]);

        $mapUserToObject = new ReflectionMethod(Alipay::class, 'mapUserToObject');
        $mapUserToObject->setAccessible(true);

        $user = [
            'user_id' => 'user123',
            'nick_name' => 'Test User',
            'avatar' => 'http://avatar.url',
            'email' => 'test@example.com',
        ];

        $result = $mapUserToObject->invoke($provider, $user);

        $this->assertSame('user123', $result->getId());
        $this->assertSame('Test User', $result->getName());
        $this->assertSame('http://avatar.url', $result->getAvatar());
        $this->assertSame('test@example.com', $result->getEmail());
    }
}