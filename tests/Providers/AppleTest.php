<?php

namespace Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Exceptions\InvalidArgumentException;
use Overtrue\Socialite\Providers\Apple;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionObject;

class AppleTest extends TestCase
{
    private function makeProvider(array $extra = []): Apple
    {
        return new Apple(\array_merge([
            'client_id' => 'com.example.app',
            'client_secret' => 'test-client-secret',
            'redirect_url' => 'https://example.com/callback',
        ], $extra));
    }

    private function makeIdToken(array $claims = []): string
    {
        $header = \rtrim(\strtr(\base64_encode(\json_encode(['kid' => 'test', 'alg' => 'ES256'])), '+/', '-_'), '=');
        $payload = \rtrim(\strtr(\base64_encode(\json_encode(\array_merge([
            'sub' => 'apple-user-id',
            'email' => 'user@privaterelay.appleid.com',
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.example.app',
            'exp' => \time() + 3600,
            'iat' => \time(),
        ], $claims))), '+/', '-_'), '=');

        return $header.'.'.$payload.'.fake-signature';
    }

    public function test_apple_provider_redirect_url()
    {
        $provider = $this->makeProvider();
        $url = $provider->redirect();

        $this->assertStringStartsWith('https://appleid.apple.com/auth/authorize', $url);
        $this->assertStringContainsString('client_id=com.example.app', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('response_mode=form_post', $url);
        $this->assertStringContainsString('scope=name+email', $url);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fexample.com%2Fcallback', $url);
    }

    public function test_apple_provider_token_url()
    {
        $provider = $this->makeProvider();
        $getTokenUrl = new ReflectionMethod(Apple::class, 'getTokenUrl');
        $getTokenUrl->setAccessible(true);

        $this->assertSame('https://appleid.apple.com/auth/token', $getTokenUrl->invoke($provider));
    }

    public function test_apple_provider_token_fields()
    {
        $provider = $this->makeProvider();
        $getTokenFields = new ReflectionMethod(Apple::class, 'getTokenFields');
        $getTokenFields->setAccessible(true);

        $fields = $getTokenFields->invoke($provider, 'test-code');

        $this->assertSame('com.example.app', $fields['client_id']);
        $this->assertSame('test-client-secret', $fields['client_secret']);
        $this->assertSame('test-code', $fields['code']);
        $this->assertSame('https://example.com/callback', $fields['redirect_uri']);
        $this->assertSame('authorization_code', $fields['grant_type']);
    }

    public function test_apple_provider_uses_provided_client_secret()
    {
        $provider = $this->makeProvider(['client_secret' => 'my-custom-secret']);
        $this->assertSame('my-custom-secret', $provider->getClientSecret());
    }

    public function test_apple_provider_get_user_by_token_decodes_jwt_payload()
    {
        $provider = $this->makeProvider();
        $getUserByToken = new ReflectionMethod(Apple::class, 'getUserByToken');
        $getUserByToken->setAccessible(true);

        $idToken = $this->makeIdToken(['name' => 'John Doe']);
        $result = $getUserByToken->invoke($provider, $idToken);

        $this->assertSame('apple-user-id', $result['sub']);
        $this->assertSame('user@privaterelay.appleid.com', $result['email']);
        $this->assertSame('John Doe', $result['name']);
    }

    public function test_apple_provider_get_user_by_token_throws_on_invalid_jwt()
    {
        $provider = $this->makeProvider();
        $getUserByToken = new ReflectionMethod(Apple::class, 'getUserByToken');
        $getUserByToken->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);
        $getUserByToken->invoke($provider, 'not-a-valid-jwt');
    }

    public function test_apple_provider_map_user_to_object()
    {
        $provider = $this->makeProvider();
        $mapUserToObject = new ReflectionMethod(Apple::class, 'mapUserToObject');
        $mapUserToObject->setAccessible(true);

        $result = $mapUserToObject->invoke($provider, [
            'sub' => 'apple-user-id',
            'email' => 'user@example.com',
            'name' => 'Jane Doe',
        ]);

        $this->assertSame('apple-user-id', $result->getId());
        $this->assertSame('user@example.com', $result->getEmail());
        $this->assertSame('Jane Doe', $result->getName());
        $this->assertSame('Jane Doe', $result->getNickname()); // falls back to getName()
        $this->assertNull($result->getAvatar());
    }

    public function test_apple_provider_user_from_code()
    {
        $provider = $this->makeProvider();
        $idToken = $this->makeIdToken();

        $tokenResponseBody = \json_encode([
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'id_token' => $idToken,
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]);

        $mock = new MockHandler([
            new Response(200, [], $tokenResponseBody),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $reflection = new ReflectionObject($provider);
        $httpClientProp = $reflection->getProperty('httpClient');
        $httpClientProp->setAccessible(true);
        $httpClientProp->setValue($provider, $client);

        $user = $provider->userFromCode('test-code');

        $this->assertSame('apple-user-id', $user->getId());
        $this->assertSame('user@privaterelay.appleid.com', $user->getEmail());
        $this->assertSame('test-access-token', $user->getAccessToken());
        $this->assertSame('test-refresh-token', $user->getRefreshToken());
        $this->assertSame(3600, $user->getExpiresIn());
    }

    public function test_apple_provider_user_from_code_throws_when_id_token_missing()
    {
        $provider = $this->makeProvider();

        $tokenResponseBody = \json_encode([
            'access_token' => 'test-access-token',
            'expires_in' => 3600,
        ]);

        $mock = new MockHandler([
            new Response(200, [], $tokenResponseBody),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $reflection = new ReflectionObject($provider);
        $httpClientProp = $reflection->getProperty('httpClient');
        $httpClientProp->setAccessible(true);
        $httpClientProp->setValue($provider, $client);

        $this->expectException(AuthorizeFailedException::class);
        $this->expectExceptionMessage('Missing id_token in token response');

        $provider->userFromCode('test-code');
    }

    public function test_apple_provider_user_from_token_decodes_id_token()
    {
        $provider = $this->makeProvider();
        $idToken = $this->makeIdToken(['name' => 'Alice']);

        $user = $provider->userFromToken($idToken);

        $this->assertSame('apple-user-id', $user->getId());
        $this->assertSame('user@privaterelay.appleid.com', $user->getEmail());
        $this->assertSame('Alice', $user->getName());
    }
}
