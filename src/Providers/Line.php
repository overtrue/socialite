<?php

namespace Overtrue\Socialite\Providers;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Overtrue\Socialite\Contracts\UserInterface;
use Overtrue\Socialite\User;

/**
 * @see https://developers.line.biz/en/docs/line-login/integrate-line-login/ [Integrating LINE Login with your web app]
 */
class Line extends Base
{
    public const NAME = 'line';
    protected string $baseUrl = 'https://api.line.me/oauth2/';
    protected string $version = 'v2.1';
    protected array $scopes = ['profile'];

    protected function getAuthUrl(): string
    {
        $this->state = $this->state ?: \md5(\uniqid('state', true));
        return $this->buildAuthUrlFromBase('https://access.line.me/oauth2/'.$this->version.'/authorize');
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl.$this->version.'/token';
    }

    #[ArrayShape([
        'client_id' => "null|string",
        'client_secret' => "null|string",
        'code' => "string",
        'grant_type' => "string",
        'redirect_uri' => "mixed"
    ])]
    protected function getTokenFields(string $code): array
    {
        return [
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUrl,
        ];
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUserByToken(string $token): array
    {
        $response = $this->getHttpClient()->get(
            'https://api.line.me/v2/profile',
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$token,
                ],
            ]
        );

        return \json_decode($response->getBody(), true) ?? [];
    }

    #[Pure]
    protected function mapUserToObject(array $user): UserInterface
    {
        return new User(
            [
                'id' => $user['userId'] ?? null,
                'name' => $user['displayName'] ?? null,
                'nickname' => $user['displayName'] ?? null,
                'avatar' => $user['pictureUrl'] ?? null,
                'email' => null,
            ]
        );
    }
}
