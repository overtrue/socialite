<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\AccessToken;
use Overtrue\Socialite\AccessTokenInterface;
use Overtrue\Socialite\AuthorizeFailedException;
use Overtrue\Socialite\Exceptions\InvalidArgumentException;
use Overtrue\Socialite\InvalidStateException;
use Overtrue\Socialite\ProviderInterface;
use Overtrue\Socialite\User;

/**
 * Class FeiShuProvider.
 *
 * @author qijian.song@show.world
 *
 * @see https://open.feishu.cn/
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

    /**
     * 获取 app_access_token 地址.
     *
     * {@inheritdoc}
     */
    protected function getTokenUrl(): string
    {
        return $this->baseUrl.'/authen/v1/access_token';
    }

    /**
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields($code): array
    {
        return [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'app_access_token' => $this->config->get('app_access_token'),
        ];
    }

    /**
     * @param string     $token
     * @param array|null $query
     *
     * @return array
     */
    protected function getUserByToken(string $token, ?array $query = []): array
    {
        $userUrl = $this->baseUrl.'/authen/v1/access_token';

        $response = $this->getHttpClient()->post(
            $userUrl,
            [
                'json' => [
                    'app_access_token' => $this->config->get('app_access_token'),
                    'code' => $query['code'],
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
            'id' => $user['open_id'] ?? null,
            'name' => $user['name'] ?? null,
            'nickname' => $user['name'] ?? null,
            'avatar' => $user['avatar_url'] ?? null,
        ]);
    }
}
