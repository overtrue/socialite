<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\User;

/**
 * @see https://open.feishu.cn/document/uQjL04CN/ucDOz4yN4MjL3gzM
 */
class FeiShu extends Base
{
    public const NAME = 'feishu';
    protected string $baseUrl = 'https://open.feishu.cn/open-apis/';
    protected string $expiresInKey = 'refresh_expires_in';

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl . 'authen/v1/index');
    }

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
     * @return array
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     *
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
     *
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

        return $this->normalizeAccessTokenResponse($response['data']);
    }

    /**
     * @param string $token
     *
     * @return array
     */
    protected function getUserByToken(string $token): array
    {
        $response = $this->getHttpClient()->get(
            $this->baseUrl . '/authen/v1/user_info',
            [
                'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token],
                'query' => array_filter(
                    [
                        'user_access_token' => $token,
                    ]
                ),
            ]
        );

        $response = \json_decode($response->getBody(), true) ?? [];

        if (empty($response['data'])) {
            throw new \InvalidArgumentException('You have error! ' . json_encode($response, JSON_UNESCAPED_UNICODE));
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
        return new User(
            [
                'id' => $user['user_id'] ?? null,
                'name' => $user['name'] ?? null,
                'nickname' => $user['name'] ?? null,
                'avatar' => $user['avatar_url'] ?? null,
                'email' => $user['email'] ?? null,
            ]
        );
    }
}
