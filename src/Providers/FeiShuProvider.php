<?php

namespace Overtrue\Socialite\Providers;

use GuzzleHttp\Exception\BadResponseException;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\User;

/**
 * @see https://open.feishu.cn/document/uQjL04CN/ucDOz4yN4MjL3gzM
 */
class FeiShuProvider extends AbstractProvider
{
    /**
     * 飞书接口域名.
     *
     * @var string
     */
    protected $baseUrl = 'https://open.feishu.cn/open-apis/';

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl . 'authen/v1/index');
    }

    /**
     * @return array
     */
    protected function getCodeFields(): array
    {
        return [
            'redirect_uri' => $this->redirectUrl,
            'app_id' => $this->getClientId(),
        ];
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl . 'authen/v1/access_token';
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
     * @param string $code
     *
     * @return array
     * @throws AuthorizeFailedException
     */
    protected function getTokenFromCode(string $code): array
    {
        $response = $this->getHttpClient()->post(
            $this->getTokenUrl(),
            [
                'json' => [
                    'app_access_token' => $this->config->get('app_access_token'),
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                ],
            ]
        );
        $response = \json_decode($response->getBody(), true) ?? [];

        if (empty($response['data'])) {
            throw new AuthorizeFailedException('Invalid token response', $response);
        }
        $this->setExpiresInKey('refresh_expires_in');

        return $this->normalizeAccessTokenResponse($response['data']);
    }

    /**
     * @param string $token
     *
     * @return array
     */
    protected function getUserByToken(string $token): array
    {
        $response = $this->getHttpClient()->get($this->baseUrl.'/authen/v1/user_info', [
            'headers' => ['Content-Type' => 'application/json', 'AuthoriBearer ' . $token],
            'query' => array_filter([
                'user_access_token' => $token,
            ]),
        ]);

        $response = \json_decode($response->getBody(), true) ?? [];

        if (empty($response['data'])) {
            throw new BadResponseException('This query response is not except.', $response);
        }

        return $response['data'];
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
            'email' => $user['email'] ?? null,
        ]);
    }
}
