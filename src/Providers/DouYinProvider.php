<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\Exceptions\InvalidArgumentException;
use Overtrue\Socialite\User;

/**
 * @author haoliang@qiyuankeji.vip
 *
 * @see http://open.douyin.com/platform
 */
class DouYinProvider extends AbstractProvider
{
    /**
     * @var string
     */
    protected $baseUrl = 'https://open.douyin.com';

    /**
     * @var array
     */
    protected $scopes = ['user_info'];

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl.'/platform/oauth/connect');
    }

    /**
     * @return array
     */
    public function getCodeFields()
    {
        return [
            'client_key' => $this->getClientId(),
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'response_type' => 'code',
        ];
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl.'/oauth/access_token';
    }

    /**
     * @param string $code
     *
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     *
     * @return string
     */
    public function tokenFromCode($code): string
    {
        $response = $this->getHttpClient()->get($this->getTokenUrl(), [
            'query' => $this->getTokenFields($code),
        ]);

        $response = \json_decode($response->getBody()->getContents(), true) ?? [];

        return $this->normalizeAccessTokenResponse($response);
    }

    /**
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields($code)
    {
        return [
            'client_key' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];
    }

    /**
     * @param string     $token
     * @param array|null $query
     *
     * @throws \Overtrue\Socialite\Exceptions\InvalidArgumentException
     *
     * @return array
     */
    protected function getUserByToken(string $token, ?array $query = []): array
    {
        $userUrl = $this->baseUrl.'/oauth/userinfo/';

        if (empty($query['open_id'])) {
            throw new InvalidArgumentException('open_id cannot be empty.');
        }

        $response = $this->getHttpClient()->get(
            $userUrl,
            [
                'query' => [
                    'access_token' => $token,
                    'open_id' => $query['open_id'],
                ],
            ]
        );

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
            'id' => $user['open_id'] ?? null,
            'username' => $user['nickname'] ?? null,
            'nickname' => $user['nickname'] ?? null,
            'avatar' => $user['avatar'] ?? null,
        ]);
    }
}
