<?php

namespace Overtrue\Socialite\Providers;

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
        $this->state = $this->state ?: md5(uniqid());
        return $this->buildAuthUrlFromBase('https://access.line.me/oauth2/'.$this->version.'/authorize');
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl.$this->version.'/token';
    }

    /**
     * @param  string  $code
     *
     * @return array
     */
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
     * @param  string  $token
     *
     * @return array
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

    protected function mapUserToObject(array $user): User
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
