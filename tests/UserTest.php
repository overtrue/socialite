<?php


use Overtrue\Socialite\AccessToken;
use Overtrue\Socialite\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testJsonserialize()
    {
        $this->assertSame('[]', json_encode(new User([])));

        $this->assertSame('{"token":"mock-token"}', json_encode(new User(['token' => new AccessToken(['access_token' => 'mock-token'])])));
    }
}
