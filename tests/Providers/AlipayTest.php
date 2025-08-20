<?php

namespace Tests\Providers;

use Mockery as m;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Providers\Alipay;
use PHPUnit\Framework\TestCase;

class AlipayTest extends TestCase
{
    public function testThrowsExceptionWhenTokenResponseMissing()
    {
        $provider = new Alipay([
            'client_id' => 'client_id',
            'private_key' => 'private_key',
            'redirect_url' => 'http://localhost/callback',
        ]);

        // Mock the getHttpClient method to return response without token response
        $mockProvider = $this->getMockBuilder(Alipay::class)
            ->setConstructorArgs([[
                'client_id' => 'client_id',
                'private_key' => 'private_key',
                'redirect_url' => 'http://localhost/callback',
            ]])
            ->onlyMethods(['getHttpClient'])
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

        $this->expectException(AuthorizeFailedException::class);
        $this->expectExceptionMessage('Authorization failed: missing alipay_system_oauth_token_response in response');

        $mockProvider->tokenFromCode('test_code');
    }

    protected function tearDown(): void
    {
        m::close();
    }
}