<?php

namespace Overtrue\Socialite\Providers;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Overtrue\Socialite\Contracts\UserInterface;
use Overtrue\Socialite\Exceptions\InvalidArgumentException;
use Overtrue\Socialite\User;

/**
 * @see https://opendocs.alipay.com/open/289/105656
 */
class Alipay extends Base
{
    public const NAME = 'alipay';
    protected string $baseUrl = 'https://openapi.alipay.com/gateway.do';
    protected string $authUrl = 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm';
    protected array $scopes = ['auth_user'];
    protected string $apiVersion = '1.0';
    protected string $signType = 'RSA2';
    protected string $postCharset = 'UTF-8';
    protected string $format = 'json';
    protected bool $sandbox = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->sandbox = $this->config->get('sandbox', false);
        if ($this->sandbox) {
            $this->baseUrl = 'https://openapi.alipaydev.com/gateway.do';
            $this->authUrl = 'https://openauth.alipaydev.com/oauth2/publicAppAuthorize.htm';
        }
    }

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->authUrl);
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Overtrue\Socialite\Exceptions\InvalidArgumentException
     */
    protected function getUserByToken(string $token): array
    {
        $params = $this->getPublicFields('alipay.user.info.share');
        $params += ['auth_token' => $token];
        $params['sign'] = $this->generateSign($params);

        $response = $this->getHttpClient()->post(
            $this->baseUrl,
            [
                'form_params' => $params,
                'headers' => [
                    "content-type" => "application/x-www-form-urlencoded;charset=utf-8",
                ],
            ]
        );

        $response = json_decode($response->getBody()->getContents(), true);

        if (!empty($response['error_response']) || empty($response['alipay_user_info_share_response'])) {
            throw new \InvalidArgumentException('You have error! ' . \json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return $response['alipay_user_info_share_response'];
    }

    #[Pure]
    protected function mapUserToObject(array $user): UserInterface
    {
        return new User(
            [
                'id' => $user['user_id'] ?? null,
                'name' => $user['nick_name'] ?? null,
                'avatar' => $user['avatar'] ?? null,
                'email' => $user['email'] ?? null,
            ]
        );
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     * @throws \Overtrue\Socialite\Exceptions\InvalidArgumentException
     */
    public function tokenFromCode(string $code): array
    {
        $response = $this->getHttpClient()->post(
            $this->getTokenUrl(),
            [
                'form_params' => $this->getTokenFields($code),
                'headers' => [
                    "content-type" => "application/x-www-form-urlencoded;charset=utf-8",
                ],
            ]
        );
        $response = json_decode($response->getBody()->getContents(), true);

        if (!empty($response['error_response'])) {
            throw new \InvalidArgumentException('You have error! ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return $this->normalizeAccessTokenResponse($response['alipay_system_oauth_token_response']);
    }

    /**
     * @throws \Overtrue\Socialite\Exceptions\InvalidArgumentException
     */
    protected function getCodeFields(): array
    {
        if (empty($this->redirectUrl)) {
            throw new InvalidArgumentException('Please set same redirect URL like your Alipay Official Admin');
        }

        $fields = array_merge(
            [
                'app_id' => $this->getConfig()->get('client_id') ?? $this->getConfig()->get('app_id'),
                'scope' => implode(',', $this->scopes),
                'redirect_uri' => $this->redirectUrl,
            ],
            $this->parameters
        );

        return $fields;
    }

    /**
     * @throws \Overtrue\Socialite\Exceptions\InvalidArgumentException
     */
    #[ArrayShape([
        'client_id' => "\null|string",
        'client_secret' => "\null|string",
        'code' => "string",
        'redirect_uri' => "mixed"
    ])]
    protected function getTokenFields(string $code): array
    {
        $params = $this->getPublicFields('alipay.system.oauth.token');
        $params += [
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];
        $params['sign'] = $this->generateSign($params);

        return $params;
    }

    #[ArrayShape([
        'app_id' => "array|mixed",
        'format' => "string",
        'charset' => "string",
        'sign_type' => "string",
        'method' => "string",
        'timestamp' => "string",
        'version' => "string"
    ])]
    public function getPublicFields(string $method): array
    {
        return [
            'app_id' => $this->getConfig()->get('client_id') ?? $this->getConfig()->get('app_id'),
            'format' => $this->format,
            'charset' => $this->postCharset,
            'sign_type' => $this->signType,
            'method' => $method,
            'timestamp' => date('Y-m-d H:m:s'),
            'version' => $this->apiVersion,
        ];
    }

    /**
     * @throws InvalidArgumentException
     * @see https://opendocs.alipay.com/open/289/105656
     */
    protected function generateSign($params): string
    {
        ksort($params);

        return $this->signWithSHA256RSA($this->buildParams($params), $this->getConfig()->get('rsa_private_key'));
    }

    /**
     * @throws \Overtrue\Socialite\Exceptions\InvalidArgumentException
     */
    protected function signWithSHA256RSA(string $signContent, string $key): string
    {
        if (empty($key)) {
            throw new InvalidArgumentException('no RSA private key set.');
        }

        $key = "-----BEGIN RSA PRIVATE KEY-----\n" .
            chunk_split($key, 64, "\n") .
            "-----END RSA PRIVATE KEY-----";

        openssl_sign($signContent, $signValue, $key, OPENSSL_ALGO_SHA256);

        return base64_encode($signValue);
    }

    public static function buildParams(array $params, bool $urlencode = false, array $except = ['sign']): string
    {
        $param_str = '';
        foreach ($params as $k => $v) {
            if (in_array($k, $except)) {
                continue;
            }
            $param_str .= $k . '=';
            $param_str .= $urlencode ? rawurlencode($v) : $v;
            $param_str .= '&';
        }

        return rtrim($param_str, '&');
    }
}
