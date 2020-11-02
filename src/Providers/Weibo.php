<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\Exceptions\InvalidTokenException;
use Overtrue\Socialite\User;

/**
 * @see http://open.weibo.com/wiki/%E6%8E%88%E6%9D%83%E6%9C%BA%E5%88%B6%E8%AF%B4%E6%98%8E [OAuth 2.0 授权机制说明]
 */
class Weibo extends Base
{
    public const NAME = 'weibo';
    protected string $baseUrl = 'https://api.weibo.com';
    protected array $scopes = ['email'];

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl.'/oauth2/authorize');
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl.'/2/oauth2/access_token';
    }

    /**
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields(string $code): array
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }

    /**
     * @param  string  $token
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @throws \Overtrue\Socialite\Exceptions\InvalidTokenException
     */
    protected function getUserByToken(string $token): array
    {
        $uid = $this->getTokenPayload($token)['uid'] ?? null;

        if (empty($uid)) {
            throw new InvalidTokenException('Invalid token.', $token);
        }

        $response = $this->getHttpClient()->get($this->baseUrl.'/2/users/show.json', [
            'query' => [
                'uid' => $uid,
                'access_token' => $token,
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        return \json_decode($response->getBody(), true) ?? [];
    }

    /**
     * @param  string  $token
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Overtrue\Socialite\Exceptions\InvalidTokenException
     */
    protected function getTokenPayload(string $token): array
    {
        $response = $this->getHttpClient()->post($this->baseUrl.'/oauth2/get_token_info', [
            'query' => [
                'access_token' => $token,
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $response = \json_decode($response->getBody(), true) ?? [];

        if (empty($response['uid'])) {
            throw new InvalidTokenException(\sprintf('Invalid token %s', $token), $token);
        }

        return $response;
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
            'nickname' => $user['screen_name'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['avatar_large'] ?? null,
        ]);
    }
}
