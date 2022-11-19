<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Exceptions\InvalidArgumentException;
use Overtrue\Socialite\Exceptions\MethodDoesNotSupportException;
use Overtrue\Socialite\User;

/**
 * @link https://open.work.weixin.qq.com/api/doc/90000/90135/91022
 */
class WeWork extends Base
{
    public const NAME = 'wework';
    protected bool $detailed = false;
    protected ?int $agentId = null;
    protected ?string $apiAccessToken = null;
    protected string $baseUrl = 'https://qyapi.weixin.qq.com';

    public function __construct(array $config)
    {
        parent::__construct($config);

        if ($this->getConfig()->has('base_url')) {
            $this->baseUrl = $this->getConfig()->get('base_url');
        }
    }

    /**
     * @deprecated will remove at 4.0
     */
    public function setAgentId(int $agentId): WeWork
    {
        $this->agentId = $agentId;

        return $this;
    }

    /**
     * @deprecated will remove at 4.0
     */
    public function withAgentId(int $agentId): WeWork
    {
        $this->agentId = $agentId;

        return $this;
    }

    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    public function userFromCode(string $code): User
    {
        $token = $this->getApiAccessToken();
        $user = $this->getUser($token, $code);

        if ($this->detailed) {
            $userTicket = $user['user_ticket'] ?? '';
            $user = $this->getUserById($user['UserId']);
            if ($userTicket) {
                $user += $this->getUserDetail($userTicket);
            }
        }

        return $this->mapUserToObject($user)->setProvider($this)->setRaw($user);
    }

    public function detailed(): self
    {
        $this->detailed = true;

        return $this;
    }

    public function withApiAccessToken(string $apiAccessToken): WeWork
    {
        $this->apiAccessToken = $apiAccessToken;

        return $this;
    }

    public function getAuthUrl(): string
    {
        // 网页授权登录
        if (empty($this->agentId)) {
            $queries = [
                'appid' => $this->getClientId(),
                'redirect_uri' => $this->redirectUrl,
                'response_type' => 'code',
                'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
                'state' => $this->state,
            ];

            return sprintf('https://open.weixin.qq.com/connect/oauth2/authorize?%s#wechat_redirect', http_build_query($queries));
        }

        // 第三方网页应用登录（扫码登录）
        return $this->getQrConnectUrl();
    }

    /**
     * @deprecated will remove at 4.0
     */
    public function getQrConnectUrl()
    {
        $queries = [
            'appid' => $this->getClientId(),
            'agentid' => $this->agentId ?? $this->config->get('agentid'),
            'redirect_uri' => $this->redirectUrl,
            'state' => $this->state,
        ];

        if (empty($queries['agentid'])) {
            throw new InvalidArgumentException('You must config the `agentid` in configuration or using `setAgentid($agentId)`.');
        }

        return sprintf('https://open.work.weixin.qq.com/wwopen/sso/qrConnect?%s#wechat_redirect', http_build_query($queries));
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
     * 获取访问用户敏感信息
     * see:https://developer.work.weixin.qq.com/document/path/95833
     * @param string $userTicket
     *
     * @return array
     * @throws AuthorizeFailedException
     */
    protected function getUserDetail(string $userTicket): array
    {
        $response = $this->getHttpClient()->post(
            $this->baseUrl.'/cgi-bin/user/getuserdetail',
            [
                'query'       => [
                    'access_token' => $this->getApiAccessToken(),
                ],
                'json' => [
                    'user_ticket' => $userTicket,
                ]
            ]
        );

        $response = \json_decode($response->getBody(), true) ?? [];

        if (($response['errcode'] ?? 1) > 0 || empty($response['userid'])) {
            throw new AuthorizeFailedException('Failed to get user detail:' . $response['errmsg'] ?? 'Unknown.', $response);
        }

        return $response;
    }

    /**
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

    /**
     * @param array $user
     *
     * @return \Overtrue\Socialite\User
     */
    protected function mapUserToObject(array $user): User
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
     * @return string
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
