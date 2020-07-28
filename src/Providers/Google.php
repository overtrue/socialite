<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\User;

/**
 * @see https://developers.google.com/identity/protocols/OpenIDConnect [OpenID Connect]
 */
class Google extends Base
{
    public const NAME = 'google';
    protected string $scopeSeparator = ' ';
    protected array $scopes = [
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
    ];

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase('https://accounts.google.com/o/oauth2/v2/auth');
    }

    protected function getTokenUrl(): string
    {
        return 'https://www.googleapis.com/oauth2/v4/token';
    }

    /**
     * @param string $code
     *
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     *
     * @return array
     */
    public function tokenFromCode($code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'body' => $this->getTokenFields($code),
        ]);

        return $this->normalizeAccessTokenResponse($response->getBody());
    }

    /**
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields($code): array
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }

    /**
     * @param string     $token
     * @param array|null $query
     *
     * @return array
     */
    protected function getUserByToken(string $token, ?array $query = []): array
    {
        $response = $this->getHttpClient()->get('https://www.googleapis.com/userinfo/v2/me', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return \json_decode($response->getBody(), true) ?? [];
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
            'username' => $user['email'] ?? null,
            'nickname' => $user['name'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['picture'] ?? null,
        ]);
    }
}
