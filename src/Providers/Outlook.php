<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\User;

class Outlook extends Base
{
    public const NAME = 'outlook';
    protected array $scopes = ['User.Read'];
    protected string $scopeSeparator = ' ';

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase('https://login.microsoftonline.com/common/oauth2/v2.0/authorize');
    }

    protected function getTokenUrl(): string
    {
        return 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    }

    /**
     * @param  string  $token
     * @param  array|null  $query
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUserByToken(string $token, ?array $query = []): array
    {
        $response = $this->getHttpClient()->get(
            'https://graph.microsoft.com/v1.0/me',
            ['headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
            ]
        );

        return \json_decode($response->getBody()->getContents(), true) ?? [];
    }

    /**
     * @param array $user
     *
     * @return \Overtrue\Socialite\User
     */
    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => $user['id'] ?? null,
            'nickname' => null,
            'name' => $user['displayName'] ?? null,
            'email' => $user['userPrincipalName'] ?? null,
            'avatar' => null,
        ]);
    }

    /**
     * @param string $code
     *
     * @return array|string[]
     */
    protected function getTokenFields(string $code): array
    {
        return parent::getTokenFields($code) + [
            'grant_type' => 'authorization_code',
        ];
    }
}
