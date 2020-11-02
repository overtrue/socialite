<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\User;

/**
 * @see http://developers.douban.com/wiki/?title=oauth2 [使用 OAuth 2.0 访问豆瓣 API]
 */
class Douban extends Base
{
    public const NAME = 'douban';

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase('https://www.douban.com/service/auth2/auth');
    }

    protected function getTokenUrl(): string
    {
        return 'https://www.douban.com/service/auth2/token';
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
        $response = $this->getHttpClient()->get('https://api.douban.com/v2/user/~me', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true) ?? [];
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
            'nickname' => $user['name'] ?? null,
            'name' => $user['name'] ?? null,
            'avatar' => $user['avatar'] ?? null,
            'email' => null,
        ]);
    }

    /**
     * @param string $code
     *
     * @return array|string[]
     */
    protected function getTokenFields(string $code): array
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }

    /**
     * @param  string  $code
     *
     * @return array
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function tokenFromCode(string $code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'form_params' => $this->getTokenFields($code),
        ]);

        return $this->normalizeAccessTokenResponse($response->getBody()->getContents());
    }
}
