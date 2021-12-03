<?php

namespace Overtrue\Socialite\Providers;

use GuzzleHttp\Exception\GuzzleException;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Overtrue\Socialite\Contracts\UserInterface;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Exceptions\BadRequestException;
use Overtrue\Socialite\Exceptions\Feishu\InvalidTicketException;
use Overtrue\Socialite\Exceptions\InvalidTokenException;
use Overtrue\Socialite\User;

/**
 * @see https://open.feishu.cn/document/uQjL04CN/ucDOz4yN4MjL3gzM
 */
class FeiShu extends Base
{
    public const NAME = 'feishu';
    protected string $baseUrl = 'https://open.feishu.cn/open-apis';
    protected string $expiresInKey = 'refresh_expires_in';
    protected bool   $isInternalApp = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->isInternalApp = ($this->config->get('app_mode') ?? $this->config->get('mode')) == 'internal';
    }

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl . '/authen/v1/index');
    }

    #[ArrayShape(['redirect_uri' => "mixed", 'app_id' => "null|string"])]
    protected function getCodeFields(): array
    {
        return [
            'redirect_uri' => $this->redirectUrl,
            'app_id' => $this->getClientId(),
        ];
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl . '/authen/v1/access_token';
    }

    /**
     * @throws AuthorizeFailedException
     * @throws GuzzleException
     */
    public function tokenFromCode(string $code): array
    {
        return $this->normalizeAccessTokenResponse($this->getTokenFromCode($code));
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     * @throws \Overtrue\Socialite\Exceptions\Feishu\InvalidTicketException
     * @throws \Overtrue\Socialite\Exceptions\InvalidTokenException
     */
    protected function getTokenFromCode(string $code): array
    {
        $this->configAppAccessToken();
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
     * @throws \GuzzleHttp\Exception\GuzzleException
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

    #[Pure]
    protected function mapUserToObject(array $user): UserInterface
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

    public function withInternalAppMode(): static
    {
        $this->isInternalApp = true;

        return $this;
    }

    public function withDefaultMode(): static
    {
        $this->isInternalApp = false;

        return $this;
    }

    /**
     * set 'app_ticket' in config attribute
     */
    public function withAppTicket(string $appTicket): static
    {
        $this->config->set('app_ticket', $appTicket);

        return $this;
    }

    /**
     * 设置 app_access_token 到 config 设置中
     * 应用维度授权凭证，开放平台可据此识别调用方的应用身份
     * 分内建和自建
     *
     * @throws \Overtrue\Socialite\Exceptions\Feishu\InvalidTicketException
     * @throws \Overtrue\Socialite\Exceptions\InvalidTokenException
     */
    protected function configAppAccessToken()
    {
        $url = $this->baseUrl . '/auth/v3/app_access_token/';
        $params = [
            'json' => [
                'app_id' => $this->config->get('client_id'),
                'app_secret' => $this->config->get('client_secret'),
                'app_ticket' => $this->config->get('app_ticket'),
            ],
        ];

        if ($this->isInternalApp) {
            $url = $this->baseUrl . '/auth/v3/app_access_token/internal/';
            $params = [
                'json' => [
                    'app_id' => $this->config->get('client_id'),
                    'app_secret' => $this->config->get('client_secret'),
                ],
            ];
        }

        if (!$this->isInternalApp && !$this->config->has('app_ticket')) {
            throw new InvalidTicketException('You are using default mode, please config \'app_ticket\' first');
        }

        $response = $this->getHttpClient()->post($url, $params);
        $response = \json_decode($response->getBody(), true) ?? [];

        if (empty($response['app_access_token'])) {
            throw new InvalidTokenException('Invalid \'app_access_token\' response', json_encode($response));
        }

        $this->config->set('app_access_token', $response['app_access_token']);
    }

    /**
     * 设置 tenant_access_token 到 config 属性中
     * 应用的企业授权凭证，开放平台据此识别调用方的应用身份和企业身份
     * 分内建和自建
     *
     * @throws \Overtrue\Socialite\Exceptions\BadRequestException
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     */
    protected function configTenantAccessToken()
    {
        $url = $this->baseUrl . '/auth/v3/tenant_access_token/';
        $params = [
            'json' => [
                'app_id' => $this->config->get('client_id'),
                'app_secret' => $this->config->get('client_secret'),
                'app_ticket' => $this->config->get('app_ticket'),
            ],
        ];

        if ($this->isInternalApp) {
            $url = $this->baseUrl . '/auth/v3/tenant_access_token/internal/';
            $params = [
                'json' => [
                    'app_id' => $this->config->get('client_id'),
                    'app_secret' => $this->config->get('client_secret'),
                ],
            ];
        }

        if (!$this->isInternalApp && !$this->config->has('app_ticket')) {
            throw new BadRequestException('You are using default mode, please config \'app_ticket\' first');
        }

        $response = $this->getHttpClient()->post($url, $params);
        $response = \json_decode($response->getBody(), true) ?? [];
        if (empty($response['tenant_access_token'])) {
            throw new AuthorizeFailedException('Invalid tenant_access_token response', $response);
        }

        $this->config->set('tenant_access_token', $response['tenant_access_token']);
    }
}
