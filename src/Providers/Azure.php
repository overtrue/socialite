<?php

namespace Overtrue\Socialite\Providers;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Overtrue\Socialite\Contracts\UserInterface;
use Overtrue\Socialite\User;

class Azure extends Base
{
    public const NAME = 'azure';
    protected array $scopes = ['User.Read'];
    protected string $scopeSeparator = ' ';

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->getBaseUrl() . '/oauth2/v2.0/authorize');
    }

    protected function getBaseUrl(): string
    {
        return 'https://login.microsoftonline.com/'.$this->config["tenant"];
    }

    protected function getTokenUrl(): string
    {
        return $this->getBaseUrl() . '/oauth2/v2.0/token';
    }

    /**
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

    #[Pure]
    protected function mapUserToObject(array $user): UserInterface
    {
        return new User([
            'id' => $user['id'] ?? null,
            'nickname' => null,
            'name' => $user['displayName'] ?? null,
            'email' => $user['userPrincipalName'] ?? null,
            'avatar' => null,
        ]);
    }

    #[ArrayShape([
        'client_id' => "\null|string",
        'client_secret' => "\null|string",
        'code' => "string",
        'redirect_uri' => "mixed"
    ])]
    protected function getTokenFields(string $code): array
    {
        return parent::getTokenFields($code) + [
            'grant_type' => 'authorization_code',
        ];
    }
}
