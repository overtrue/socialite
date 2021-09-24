<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Exceptions\InvalidArgumentException;
use Overtrue\Socialite\User;

/**
 * @see http://open.douyin.com/platform
 * @see https://open.douyin.com/platform/doc/OpenAPI-overview
 */
class DouYin extends Base
{
    public const NAME = 'douyin';
    protected string $baseUrl = 'https://open.douyin.com';
    protected array $scopes = ['user_info'];
    protected ?string $openId;

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl . '/platform/oauth/connect/');
    }

    public function getCodeFields(): array
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
        return $this->baseUrl . '/oauth/access_token/';
    }

    /**
     * @param  string  $code
     *
     * @return array
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     */
    public function tokenFromCode($code): array
    {
        $response = $this->getHttpClient()->get(
            $this->getTokenUrl(),
            [
                'query' => $this->getTokenFields($code),
            ]
        );

        $body = \json_decode($response->getBody()->getContents(), true) ?? [];

        if (empty($body['data']) || $body['data']['error_code'] != 0) {
            throw new AuthorizeFailedException('Invalid token response', $body);
        }

        $this->withOpenId($body['data']['open_id']);

        return $this->normalizeAccessTokenResponse($body['data']);
    }

    protected function getTokenFields(string $code): array
    {
        return [
            'client_key' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];
    }

    protected function getUserByToken(string $token): array
    {
        $userUrl = $this->baseUrl . '/oauth/userinfo/';

        if (empty($this->openId)) {
            throw new InvalidArgumentException('please set open_id before your query.');
        }

        $response = $this->getHttpClient()->get(
            $userUrl,
            [
                'query' => [
                    'access_token' => $token,
                    'open_id' => $this->openId,
                ],
            ]
        );

        $body = \json_decode($response->getBody()->getContents(), true);

        return $body['data'] ?? [];
    }

    protected function mapUserToObject(array $user): User
    {
        return new User(
            [
                'id' => $user['open_id'] ?? null,
                'name' => $user['nickname'] ?? null,
                'nickname' => $user['nickname'] ?? null,
                'avatar' => $user['avatar'] ?? null,
                'email' => $user['email'] ?? null,
            ]
        );
    }

    public function withOpenId(string $openId): self
    {
        $this->openId = $openId;

        return $this;
    }
}
