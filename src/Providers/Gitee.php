<?php


namespace Overtrue\Socialite\Providers;


use Overtrue\Socialite\User;

class Gitee extends Base
{
    public const NAME = 'gitee';

    protected string $expiresInKey = 'expires_in';

    protected string $accessTokenKey = 'access_token';

    protected string $refreshTokenKey = 'refresh_token';

    protected array $scopes = ['user_info'];


    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase('https://gitee.com/oauth/authorize');
    }

    protected function getTokenUrl(): string
    {
        return 'https://gitee.com/oauth/token';
    }

    protected function getUserByToken(string $token): array
    {
        $userUrl = 'https://gitee.com/api/v5/user';
        $response = $this->getHttpClient()->get(
            $userUrl,
            [
                'query' => ['access_token' => $token],
            ]
        );
        return \json_decode($response->getBody()->getContents(), true) ?? [];
    }

    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => $user['id'] ?? null,
            'nickname' => $user['login'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['avatar_url'] ?? null,
        ]);
    }

    protected function getTokenFields(string $code): array
    {
        return [
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'code' => $code,
            'redirect_uri' => $this->redirectUrl,
            'grant_type' => 'authorization_code',
        ];
    }
}