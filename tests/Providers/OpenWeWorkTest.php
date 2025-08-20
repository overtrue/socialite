<?php

namespace Tests\Providers;

use Mockery as m;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Providers\OpenWeWork;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class OpenWeWorkTest extends TestCase
{
    public function testThrowsExceptionWhenUserTicketMissing()
    {
        $provider = new OpenWeWork([
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_url' => 'http://localhost/callback',
            'suite_id' => 'suite_id',
            'suite_secret' => 'suite_secret',
        ]);

        // Set detailed to true to trigger the user_ticket validation
        $detailedProperty = new ReflectionProperty(OpenWeWork::class, 'detailed');
        $detailedProperty->setAccessible(true);
        $detailedProperty->setValue($provider, true);

        // Mock the methods
        $mockProvider = $this->getMockBuilder(OpenWeWork::class)
            ->setConstructorArgs([[
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_url' => 'http://localhost/callback',
                'suite_id' => 'suite_id',
                'suite_secret' => 'suite_secret',
            ]])
            ->onlyMethods(['getSuiteAccessToken', 'getUser'])
            ->getMock();

        // Set detailed to true
        $detailedProperty->setValue($mockProvider, true);

        $mockProvider->method('getSuiteAccessToken')->willReturn('suite_token');
        $mockProvider->method('getUser')->willReturn([
            'UserId' => 'user123',
            // Missing user_ticket
        ]);

        $this->expectException(AuthorizeFailedException::class);
        $this->expectExceptionMessage('Authorization failed: missing user_ticket in response');

        $mockProvider->userFromCode('test_code');
    }

    protected function tearDown(): void
    {
        m::close();
    }
}