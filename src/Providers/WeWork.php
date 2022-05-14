<?php

namespace Overtrue\Socialite\Providers;

use JetBrains\PhpStorm\Pure;
use Overtrue\Socialite\Contracts\UserInterface;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Exceptions\MethodDoesNotSupportException;
use Overtrue\Socialite\User;

/**
 * @link https://open.work.weixin.qq.com/api/doc/90000/90135/91022
 */
class WeWork extends Base
{
    public const NAME = 'wework';
    protected bool $detailed = false;
    protected ?string $apiAccessToken;
    protected string $baseUrl = 'https://qyapi.weixin.qq.com';

    public function __construct(array $config)
    {
        parent::__construct($config);

        if ($this->getConfig()->has('base_url')) {
            $this->baseUrl = $this->getConfig()->get('base_url');
        }
    }

    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    public function userFromCode(string $code): UserInterface
    {
        $token = $this->getApiAccessToken();
        $user = $this->getUser($token, $code);

        if ($this->detailed) {
            $user = $this->getUserById($user['UserId']);
        }

        return $this->mapUserToObject($user)->setProvider($this)->setRaw($user);
    }

    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    public function withApiAccessToken(string $apiAccessToken): static
    {
        $this->apiAccessToken = $apiAccessToken;

        return $this;
    }

    /**
     * @throws \Overtrue\Socialite\Exceptions\InvalidArgumentException
     */
    public function getAuthUrl(): string
    {
        $queries = [
            'appid' => $this->getClientId(),
            'redirect_uri' => $this->redirectUrl,
            'response_type' => 'code',
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'state' => $this->state,
        ];

        return sprintf('https://open.weixin.qq.com/connect/oauth2/authorize?%s#wechat_redirect', http_build_query($queries));
    }

    /**
     * @throws \Overtrue\Socialite\Exceptions\MethodDoesNotSupportException
     */
    protected function getUserByToken(string $token): array
    {
        throw new MethodDoesNotSupportException('WeWork doesn\'t support access_token mode');
    }

    protected function getApiAccessToken(): string
    {
        return $this->apiAccessToken ?? $this->apiAccessToken = $this->requestApiAccessToken();
    }

    /**
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     */
    protected function getUser(string $token, string $code): array
    {
        $response = $this->getHttpClient()->get(
            $this->baseUrl . '/cgi-bin/user/getuserinfo',
            [
                'query' => array_filter(
                    [
                        'access_token' => $token,
                        'code' => $code,
                    ]
                ),
            ]
        );

        $response = \json_decode($response->getBody(), true) ?? [];

        if (($response['errcode'] ?? 1) > 0 || (empty($response['UserId']) && empty($response['OpenId']))) {
            throw new AuthorizeFailedException('Failed to get user openid:' . $response['errmsg'] ?? 'Unknown.', $response);
        } elseif (empty($response['UserId'])) {
            $this->detailed = false;
        }

        return $response;
    }

    /**
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     */
    protected function getUserById(string $userId): array
    {
        $response = $this->getHttpClient()->post(
            $this->baseUrl . '/cgi-bin/user/get',
            [
                'query' => [
                    'access_token' => $this->getApiAccessToken(),
                    'userid' => $userId,
                ],
            ]
        );

        $response = \json_decode($response->getBody(), true) ?? [];

        if (($response['errcode'] ?? 1) > 0 || empty($response['userid'])) {
            throw new AuthorizeFailedException('Failed to get user:' . $response['errmsg'] ?? 'Unknown.', $response);
        }

        return $response;
    }

    #[Pure]
    protected function mapUserToObject(array $user): UserInterface
    {
        if ($this->detailed) {
            return new User(
                [
                    'id' => $user['userid'] ?? null,
                    'name' => $user['name'] ?? null,
                    'avatar' => $user['avatar'] ?? null,
                    'email' => $user['email'] ?? null,
                ]
            );
        }

        return new User(
            [
                'id' => $user['UserId'] ?? null ?: $user['OpenId'] ?? null,
            ]
        );
    }

    /**
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function requestApiAccessToken(): string
    {
        $response = $this->getHttpClient()->get(
            $this->baseUrl . '/cgi-bin/gettoken',
            [
                'query' => array_filter(
                    [
                        'corpid' => $this->config->get('corp_id') ?? $this->config->get('corpid') ?? $this->config->get('client_id'),
                        'corpsecret' => $this->config->get('corp_secret') ?? $this->config->get('corpsecret') ?? $this->config->get('client_secret'),
                    ]
                ),
            ]
        );

        $response = \json_decode($response->getBody(), true) ?? [];

        if (($response['errcode'] ?? 1) > 0) {
            throw new AuthorizeFailedException('Failed to get api access_token:' . $response['errmsg'] ?? 'Unknown.', $response);
        }

        return $response['access_token'];
    }

    protected function getTokenUrl(): string
    {
        return '';
    }
}
