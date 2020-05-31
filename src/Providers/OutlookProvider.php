<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\User;

class OutlookProvider extends AbstractProvider
{
    /**
     * @var string[]
     */
    protected $scopes = ['User.Read'];

    /**
     * @var string
     */
    protected $scopeSeparator = ' ';

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase('https://login.microsoftonline.com/common/oauth2/v2.0/authorize');
    }

    protected function getTokenUrl(): string
    {
        return 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    }

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

    protected function getTokenFields($code): array
    {
        return parent::getTokenFields($code) + [
            'grant_type' => 'authorization_code',
        ];
    }
}
