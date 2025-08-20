<?php

namespace Tests\Providers;

use Mockery as m;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Providers\DouYin;
use PHPUnit\Framework\TestCase;

class DouYinTest extends TestCase
{
    public function testThrowsExceptionWhenOpenIdMissing()
    {
        $provider = new DouYin([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
        ]);

        // Mock the getHttpClient method to return response without open_id
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

    protected function tearDown(): void
    {
        m::close();
    }
}