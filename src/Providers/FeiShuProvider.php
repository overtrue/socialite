<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\User;

/**
 * @link https://open.feishu.cn
 */
class FeiShuProvider extends AbstractProvider
{
    /**
     * 飞书接口域名.
     *
     * @var string
     */
    protected $baseUrl = 'https://open.feishu.cn/open-apis/';

    /**
     * 应用授权作用域.
     *
     * @var array
     */
    protected $scopes = ['user_info'];

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl.'/authen/v1/index');
    }

    /**
     * @return array
     */
    protected function getCodeFields()
    {
        return [
            'redirect_uri' => $this->redirectUrl,
            'app_id' => $this->getClientId(),
        ];
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl.'/authen/v1/access_token';
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
        return $this->normalizeAccessTokenResponse($this->getTokenFromCode($code));
    }

    /**
     * @param string $token
     *
     * @return array
     */
    protected function getUserByToken(string $token): array
    {
        $response = $this->getHttpClient()->get($this->baseUrl.'/authen/v1/user_info', [
            'headers' => ['Accept' => 'application/json'],
            'query' => array_filter([
                'user_access_token' => $token,
            ]),
        ]);

        return \json_decode($response->getBody(), true) ?? [];
    }

    /**
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFromCode(string $code): array
    {
        $userUrl = $this->baseUrl.'/authen/v1/access_token';

        $response = $this->getHttpClient()->post(
            $userUrl,
            [
                'json' => [
                    'app_access_token' => $this->config->get('app_access_token'),
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                ],
            ]
        );

        return \json_decode($response->getBody(), true)['data'] ?? [];
    }

    /**
     * @param array $user
     *
     * @return User
     */
    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => $user['user_id'] ?? null,
            'name' => $user['name'] ?? null,
            'nickname' => $user['name'] ?? null,
            'avatar' => $user['avatar_url'] ?? null,
        ]);
    }
}
