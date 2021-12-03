<?php

namespace Overtrue\Socialite\Providers;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Overtrue\Socialite\Contracts\UserInterface;
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

    #[ArrayShape([
        'client_key' => "null|string",
        'redirect_uri' => "mixed",
        'scope' => "string",
        'response_type' => "string"
    ])]
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
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     */
    public function tokenFromCode(string $code): array
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

    #[ArrayShape([
        'client_key' => "null|string",
        'client_secret' => "null|string",
        'code' => "string",
        'grant_type' => "string"
    ])]
    protected function getTokenFields(string $code): array
    {
        return [
            'client_key' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];
    }

    /**
     * @throws \Overtrue\Socialite\Exceptions\InvalidArgumentException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
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

    #[Pure]
    protected function mapUserToObject(array $user): UserInterface
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
