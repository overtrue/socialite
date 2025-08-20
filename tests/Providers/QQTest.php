<?php

namespace Tests\Providers;

use Mockery as m;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Providers\QQ;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class QQTest extends TestCase
{
    public function testThrowsExceptionWhenOpenidMissing()
    {
        $provider = new QQ([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
        ]);

        // Mock the getHttpClient method to return response without openid
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

    protected function tearDown(): void
    {
        m::close();
    }
}