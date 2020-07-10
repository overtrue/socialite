<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\Contracts\ProviderInterface;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\User;

class QCloud extends Base implements ProviderInterface
{
    public const NAME = 'qcloud';
    protected string $baseUrl = 'https://open.api.qcloud.com/v2/index.php';
    protected array $scopes = ['login'];
    protected string $accessTokenKey = 'userAccessToken';
    protected string $refreshTokenKey = 'userRefreshToken';
    protected string $expiresInKey = 'expiresAt';
    protected ?string $openId;
    protected ?string $unionId;

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase('https://cloud.tencent.com/open/authorize');
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl;
    }

    protected function getAppId()
    {
        return $this->config->get('app_id');
    }

    /**
     * @param string $code
     *
     * @return array
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     */
    public function TokenFromCode($code): array
    {
        $response = $this->getHttpClient()->get(
            $this->getTokenUrl(),
            [
                'query' => $this->getTokenFields($code),
            ]
        );

        return $this->parseAccessToken($response->getBody()->getContents());
    }

    /**
     * @param string $code
     *
     * @return array
     */
    public function getTokenFields(string $code): array
    {
        $nonce = rand();
        $timestamp = time();
        $fields = [
            'action' => 'GetUserAccessToken',
            'SecretId' => $this->config->get('secret_id'),
            'userAuthCode' => $code,
            'Nonce' => $nonce,
            'Timestamp' => $timestamp,
        ];
        $fields['Signature'] = $this->generateSign('get', $this->getTokenUrl(), $fields);

        return $fields;
    }

    /**
     * @param string $token
     *
     * @return array|mixed
     * @throws \GuzzleHttp\Exception\BadResponseException
     */
    protected function getUserByToken(string $token): array
    {
        $secret = $this->getFederationToken($token);
        $nonce = rand();
        $timestamp = time();
        $queries = [
            'Action' => 'GetUserBaseInfo',
            'SecretId' => $this->getClientId(),
            'Nonce' => $nonce,
            'Timestamp' => $timestamp,
            'Token' => $secret['token'],
        ];
        $queries['Signature'] = $this->generateSign('get', $this->baseUrl, $queries, $secret['key']);

        $response = $this->getHttpClient()->get(
            $this->baseUrl,
            [
                'query' => $queries,
            ]
        );
        $response = json_decode($response->getBody()->getContents(), true) ?? [];

        if (empty($response['data'])) {
            throw new \InvalidArgumentException('You have error! ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return $response['data'];
    }

    /**
     * @param array $user
     *
     * @return \Overtrue\Socialite\User
     */
    protected function mapUserToObject(array $user): User
    {
        return new User(
            [
                'id' => $this->openId ?? null,
                'name' => $user['nickname'] ?? null,
                'nickname' => $user['nickname'] ?? null,
                'email' => $user['email'] ?? null,
            ]
        );
    }

    /**
     * @param string $method
     * @param string $url
     * @param array  $params
     * @param string $sha
     * @param string $key
     *
     * @return string
     */
    protected function generateSign($method, $url, $params, $sha = 'HmacSHA256', $key = null)
    {
        $params['SignatureMethod'] = $sha;

        if (empty($key)) {
            $key = $this->config->get('secret_key');
        }

        ksort($params);

        foreach ($params as $k => $v) {
            if (strpos($k, '_')) {
                str_replace('_', '.', $k);
            }
        }

        $srcStr = sprintf('%s%s?%s', strtoupper($method), $url, http_build_query($params));

        return base64_encode(hash_hmac($sha, $srcStr, $key, true));
    }

    /**
     * @param string $body
     *
     * @return array
     * @throws AuthorizeFailedException
     *
     */
    protected function parseAccessToken(string $body)
    {
        if (!is_array($body)) {
            $body = json_decode($body, true);
        }

        if (0 != $body['code']) {
            throw new AuthorizeFailedException('Authorize Failed: ' . json_encode($body, JSON_UNESCAPED_UNICODE), $body);
        }

        if (empty($body['data'])) {
            throw new \InvalidArgumentException('You have error! ' . json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $this->openId = $body['data']['userOpenId'] ?? null;
        $this->unionId = $body['data']['userUnionId'] ?? null;

        return $body['data'];
    }

    /**
     * @param string $accessToken
     *
     * @return array
     */
    protected function getFederationToken(string $accessToken)
    {
        $nonce = rand();
        $timestamp = time();
        $params = [
            'Action' => 'ThGetFederationToken',
            'SecretId' => $this->config->get('secret_id'),
            'Nonce' => $nonce,
            'Timestamp' => $timestamp,
            'openAccessToken' => $accessToken,
            'duration' => 7200,
        ];
        $params['Signature'] = $this->generateSign('get', $this->baseUrl, $params);

        $response = $this->getHttpClient()->get($this->baseUrl, ['query' => $params]);
        $credentials = json_decode($response->getBody()->getContents(), true)['credentials'];

        $secret['id'] = $credentials['tmpSecretId'];
        $secret['key'] = $credentials['tmpSecretKey'];
        $secret['token'] = $credentials['token'];

        return $secret;
    }

    protected function getCodeFields(): array
    {
        $fields = array_merge(
            [
                'app_id' => $this->getAppId(),
                'redirect_url' => $this->redirectUrl,
                'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
                'response_type' => 'code',
            ],
            $this->parameters
        );

        if ($this->state) {
            $fields['state'] = $this->state;
        }

        return $fields;
    }
}
