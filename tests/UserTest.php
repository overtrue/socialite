<?php


use Overtrue\Socialite\AccessToken;
use Overtrue\Socialite\User;

class UserTest extends \PHPUnit_Framework_TestCase
{
    public function testJsonserialize()
    {
        $this->assertSame('{"token":null}', json_encode(new User([])));

        $this->assertSame('{"token":{"access_token":"mock-token"}}', json_encode(new User(['token' => new AccessToken(['access_token' => 'mock-token'])])));
    }
}
