<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Exceptions\InvalidArgumentException;
use Overtrue\Socialite\Exceptions\MethodDoesNotSupportException;
use Overtrue\Socialite\User;

/**
 * @link https://open.work.weixin.qq.com/api/doc/90001/90143/91120
 */
class OpenWeWork extends Base
{
    public const NAME = 'open-wework';
    protected bool $detailed = false;
    protected ?string $suiteTicket = null;
    protected ?int $agentId = null;
    protected ?string $suiteAccessToken = null;
    protected string $baseUrl = 'https://qyapi.weixin.qq.com';

    public function __construct(array $config)
    {
        parent::__construct($config);

        if ($this->getConfig()->has('base_url')) {
            $this->baseUrl = $this->getConfig()->get('base_url');
        }
    }

    public function withAgentId(int $agentId): OpenWeWork
    {
        $this->agentId = $agentId;

        return $this;
    }

    public function userFromCode(string $code): User
    {
        $user = $this->getUser($this->getSuiteAccessToken(), $code);

        if ($this->detailed) {
            $user = array_merge($user, $this->getUserByTicket($user['user_ticket']));
        }

        return $this->mapUserToObject($user)->setProvider($this)->setRaw($user);
    }

    public function withSuiteTicket(string $suiteTicket): OpenWeWork
    {
        $this->suiteTicket = $suiteTicket;

        return $this;
    }

    public function withSuiteAccessToken(string $suiteAccessToken): OpenWeWork
    {
        $this->suiteAccessToken = $suiteAccessToken;

        return $this;
    }

    public function getAuthUrl(): string
    {
        $queries = \array_filter([
            'appid' => $this->getClientId(),
            'redirect_uri' => $this->redirectUrl,
            'response_type' => 'code',
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'state' => $this->state,
            'agentid' => $this->agentId,
        ]);

        if ((\in_array('snsapi_userinfo', $this->scopes) || \in_array('snsapi_privateinfo', $this->scopes)) && empty($this->agentId)) {
            throw new InvalidArgumentException('agentid is required when scopes is snsapi_userinfo or snsapi_privateinfo.');
        }

        return sprintf('https://open.weixin.qq.com/connect/oauth2/authorize?%s#wechat_redirect', http_build_query($queries));
    }

    /**
     * @param string $token
     *
     * @return array
     * @throws \Overtrue\Socialite\Exceptions\MethodDoesNotSupportException
     */
    protected function getUserByToken(string $token): array
    {
        throw new MethodDoesNotSupportException('Open WeWork doesn\'t support access_token mode');
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     */
    protected function getSuiteAccessToken(): string
    {
        return $this->suiteAccessToken ?? $this->suiteAccessToken = $this->requestSuiteAccessToken();
    }

    /**
     * @param  string  $token
     * @param  string  $code
     *
     * @return array
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUser(string $token, string $code): array
    {
        $response = $this->getHttpClient()->get(
            $this->baseUrl . '/cgi-bin/service/getuserinfo3rd',
            [
                'query' => array_filter(
                    [
                        'suite_access_token' => $token,
                        'code' => $code,
                    ]
                ),
            ]
        );

        $response = \json_decode($response->getBody(), true) ?? [];

        if (($response['errcode'] ?? 1) > 0 || (empty($response['UserId']) && empty($response['open_userid']))) {
            throw new AuthorizeFailedException('Failed to get user openid:' . $response['errmsg'] ?? 'Unknown.', $response);
        }

        return $response;
    }

    /**
     * @param  string  $userTicket
     *
     * @return array
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUserByTicket(string $userTicket): array
    {
        $response = $this->getHttpClient()->post(
            $this->baseUrl . '/cgi-bin/user/get',
            [
                'query' => [
                    'suite_access_token' => $this->getSuiteAccessToken(),
                    'user_ticket' => $userTicket,
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
    protected function requestSuiteAccessToken(): string
    {
        $response = $this->getHttpClient()->post(
            $this->baseUrl . '/cgi-bin/service/get_suite_token',
            [
                'json' =>
                    [
                        'suite_id' => $this->config->get('suite_id') ?? $this->config->get('client_id'),
                        'suite_secret' => $this->config->get('suite_secret') ?? $this->config->get('client_secret'),
                        'suite_ticket' => $this->suiteTicket,
                    ]
            ]
        );

        $response = \json_decode($response->getBody()->getContents(), true) ?? [];

        if (isset($response['errcode']) && $response['errcode'] > 0) {
            throw new AuthorizeFailedException('Failed to get api access_token:' . $response['errmsg'] ?? 'Unknown.', $response);
        }

        return $response['suite_access_token'];
    }

    protected function getTokenUrl(): string
    {
        return '';
    }
}
