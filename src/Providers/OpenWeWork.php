<?php

namespace Overtrue\Socialite\Providers;

use JetBrains\PhpStorm\Pure;
use Overtrue\Socialite\Contracts;
use Overtrue\Socialite\Exceptions;
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

    public function withAgentId(int $agentId): self
    {
        $this->agentId = $agentId;

        return $this;
    }

    public function userFromCode(string $code): Contracts\UserInterface
    {
        $user = $this->getUser($this->getSuiteAccessToken(), $code);

        if ($this->detailed) {
            $user = \array_merge($user, $this->getUserByTicket($user['user_ticket']));
        }

        return $this->mapUserToObject($user)->setProvider($this)->setRaw($user);
    }

    public function withSuiteTicket(string $suiteTicket): self
    {
        $this->suiteTicket = $suiteTicket;

        return $this;
    }

    public function withSuiteAccessToken(string $suiteAccessToken): self
    {
        $this->suiteAccessToken = $suiteAccessToken;

        return $this;
    }

    /**
     * @throws Exceptions\InvalidArgumentException
     */
    public function getAuthUrl(): string
    {
        $queries = \array_filter([
            'appid' => $this->getClientId(),
            Contracts\RFC6749_ABNF_REDIRECT_URI => $this->redirectUrl,
            Contracts\RFC6749_ABNF_RESPONSE_TYPE => Contracts\RFC6749_ABNF_CODE,
            Contracts\RFC6749_ABNF_SCOPE => $this->formatScopes($this->scopes, $this->scopeSeparator),
            Contracts\RFC6749_ABNF_STATE => $this->state,
            'agentid' => $this->agentId,
        ]);

        if ((\in_array('snsapi_userinfo', $this->scopes) || \in_array('snsapi_privateinfo', $this->scopes)) && empty($this->agentId)) {
            throw new Exceptions\InvalidArgumentException('agentid is required when scopes is snsapi_userinfo or snsapi_privateinfo.');
        }

        return \sprintf('https://open.weixin.qq.com/connect/oauth2/authorize?%s#wechat_redirect', \http_build_query($queries));
    }

    /**
     * @throws Exceptions\MethodDoesNotSupportException
     */
    protected function getUserByToken(string $token): array
    {
        throw new Exceptions\MethodDoesNotSupportException('Open WeWork doesn\'t support access_token mode');
    }

    protected function getSuiteAccessToken(): string
    {
        return $this->suiteAccessToken ?? $this->suiteAccessToken = $this->requestSuiteAccessToken();
    }

    /**
     * @throws Exceptions\AuthorizeFailedException
     */
    protected function getUser(string $token, string $code): array
    {
        $responseInstance = $this->getHttpClient()->get(
            $this->baseUrl . '/cgi-bin/service/getuserinfo3rd',
            [
                'query' => \array_filter(
                    [
                        'suite_access_token' => $token,
                        Contracts\RFC6749_ABNF_CODE => $code,
                    ]
                ),
            ]
        );

        $response = $this->fromJsonBody($responseInstance);

        if (($response['errcode'] ?? 1) > 0 || (empty($response['UserId']) && empty($response['open_userid']))) {
            throw new Exceptions\AuthorizeFailedException((string)$responseInstance->getBody(), $response);
        }

        return $response;
    }

    /**
     * @throws Exceptions\AuthorizeFailedException
     */
    protected function getUserByTicket(string $userTicket): array
    {
        $responseInstance = $this->getHttpClient()->post(
            $this->baseUrl . '/cgi-bin/user/get',
            [
                'query' => [
                    'suite_access_token' => $this->getSuiteAccessToken(),
                    'user_ticket' => $userTicket,
                ],
            ]
        );

        $response = $this->fromJsonBody($responseInstance);

        if (($response['errcode'] ?? 1) > 0 || empty($response['userid'])) {
            throw new Exceptions\AuthorizeFailedException((string)$responseInstance->getBody(), $response);
        }

        return $response;
    }

    #[Pure]
    protected function mapUserToObject(array $user): Contracts\UserInterface
    {
        return new User($this->detailed ? [
            Contracts\ABNF_ID => $user['userid'] ?? null,
            Contracts\ABNF_NAME => $user[Contracts\ABNF_NAME] ?? null,
            Contracts\ABNF_AVATAR => $user[Contracts\ABNF_AVATAR] ?? null,
            Contracts\ABNF_EMAIL => $user[Contracts\ABNF_EMAIL] ?? null,
        ] : [
            Contracts\ABNF_ID => $user['UserId'] ?? null ?: $user['OpenId'] ?? null,
        ]);
    }

    /**
     * @throws Exceptions\AuthorizeFailedException
     */
    protected function requestSuiteAccessToken(): string
    {
        $responseInstance = $this->getHttpClient()->post(
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

        $response = $this->fromJsonBody($responseInstance);

        if (isset($response['errcode']) && $response['errcode'] > 0) {
            throw new Exceptions\AuthorizeFailedException((string)$responseInstance->getBody(), $response);
        }

        return $response['suite_access_token'];
    }

    protected function getTokenUrl(): string
    {
        return '';
    }
}
