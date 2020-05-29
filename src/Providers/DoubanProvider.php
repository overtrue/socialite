<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\User;

/**
 * @see http://developers.douban.com/wiki/?title=oauth2 [使用 OAuth 2.0 访问豆瓣 API]
 */
class DoubanProvider extends AbstractProvider
{
    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase('https://www.douban.com/service/auth2/auth');
    }

    protected function getTokenUrl(): string
    {
        return 'https://www.douban.com/service/auth2/token';
    }

    protected function getUserByToken(string $token, ?array $query = []): array
    {
        $response = $this->getHttpClient()->get('https://api.douban.com/v2/user/~me', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => $user['id'] ?? null,
            'nickname' => $user['name'] ?? null,
            'name' => $user['name'] ?? null,
            'avatar' => $user['large_avatar'] ?? null,
            'email' => null,
        ]);
    }

    protected function getTokenFields($code)
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }

    public function tokenFromCode(string $code): string
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'form_params' => $this->getTokenFields($code),
        ]);

        return $this->normalizeAccessTokenResponse($response->getBody()->getContents());
    }
}
