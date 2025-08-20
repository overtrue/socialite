<?php

namespace Tests\Providers;

use Mockery as m;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Providers\DingTalk;
use PHPUnit\Framework\TestCase;

class DingTalkTest extends TestCase
{
    public function testThrowsExceptionWhenUserInfoMissing()
    {
        $provider = new DingTalk([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
        ]);

        // Mock the getHttpClient method to return response without user_info
        $mockProvider = $this->getMockBuilder(DingTalk::class)
            ->setConstructorArgs([[
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
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
            ->andReturn('{"errcode": 0}'); // Missing user_info

        $mockProvider->method('getHttpClient')->willReturn($mockHttpClient);

        $this->expectException(AuthorizeFailedException::class);
        $this->expectExceptionMessage('Authorization failed: missing user_info in response');

        $mockProvider->userFromCode('test_code');
    }

    protected function tearDown(): void
    {
        m::close();
    }
}