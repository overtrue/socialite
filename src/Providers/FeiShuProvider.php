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
    protected $baseUrl = 'https://open.feishu.cn';

    /**
     * 应用授权作用域.
     *
     * @var array
     */
    protected $scopes = ['user_info'];

    /**
     * @var string
     */
    protected $accessTokenKey = 'app_access_token';

    /**
     * 获取登录页面地址.
     *
     * {@inheritdoc}
     */
    protected function getAuthUrl()
    {
        return $this->buildAuthUrlFromBase($this->baseUrl.'/open-apis/authen/v1/index');
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
        return $this->baseUrl.'/open-apis/auth/v3/app_access_token/internal';
    }

    /**
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields($code): array
    {
        return [
            'app_id' => $this->getClientId(),
            'app_secret' => $this->getClientSecret(),
        ];
    }

    /**
     * @param string     $token
     * @param array|null $query
     *
     * @return array
     * @throws \Overtrue\Socialite\Exceptions\InvalidArgumentException
     */
    protected function getUserByToken(string $token, ?array $query = []): array
    {
        $userUrl = $this->baseUrl.'/open-apis/authen/v1/access_token';

        if (empty($query['open_id'])) {
            throw new InvalidArgumentException('code cannot be empty.');
        }

        $response = $this->getHttpClient()->post(
            $userUrl,
            [
                'json' => [
                    'app_access_token' => $token,
                    'code' => $query['code'],
                    'grant_type' => 'authorization_code',
                ],
            ]
        );

        return \json_decode($response->getBody(), true)['data'] ?? [];
    }

    /**
     * 格式化用户信息.
     *
     * @param array $user
     *
     * @return User
     */
    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => $user['open_id'] ?? null,
            'username' => $user['name'] ?? null,
            'nickname' => $user['name'] ?? null,
            'avatar' => $user['avatar_url'] ?? null,
        ]);
    }
}
