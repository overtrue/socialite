<?php

/*
 * This file is part of the octopus/qcloud.
 *
 * (c) Alex <alexytgong@tencent.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Overtrue\Socialite\Providers;

use GuzzleHttp\Exception\BadResponseException;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Contracts\ProviderInterface;
use Overtrue\Socialite\User;

class QCloudProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The base url of qcloud API.
     *
     * @var string
     */
    protected $baseUrl = 'https://open.api.qcloud.com/v2/index.php';

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['login'];

    /**
     * @var string
     */
    protected $openId;

    /**
     * @var string
     */
    protected $unionId;

    /**
     * Get the authentication URL for the provider.
     *
     * @return string
     */
    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase('https://cloud.tencent.com/open/authorize');
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get the access token for the given code.
     *
     * @param string $code
     *
     * @throws AuthorizeFailedException
     * @return array
     */
    public function TokenFromCode($code): array
    {
        $response = $this->getHttpClient()->get($this->getTokenUrl(), [
            'query' => $this->getTokenFields($code),
        ]);

        return $this->setAccessTokenKey('userAccessToken')
            ->setRefreshTokenKey('userRefreshToken')
            ->setExpiresInKey('expiresAt')
            ->parseAccessToken($response->getBody()->getContents());
    }

    /**
     * Get the array the request need by code.
     *
     * @param string $code
     *
     * @return array
     */
    public function getTokenFields($code): array
    {
        $nonce = rand();
        $timestamp = time();
        $fields = [
            'action' => 'GetUserAccessToken',
            'SecretId' => $this->config['secret_id'],
            'userAuthCode' => $code,
            'Nonce' => $nonce,
            'Timestamp' => $timestamp,
        ];
        $fields['Signature'] = $this->generateSign('get', $this->getTokenUrl(), $fields);

        return $fields;
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param string $token
     * @return array|mixed
     */
    protected function getUserByToken(string $token): array
    {
        $secret = $this->getTmpSecretIdAndKey($token);
        $nonce = rand();
        $timestamp = time();
        $queries = [
            'Action' => 'GetUserBaseInfo',
            'SecretId' => $this->config['secret_id'],
            'Nonce' => $nonce,
            'Timestamp' => $timestamp,
            'Token' => $secret['token'],
        ];
        $queries['Signature'] = $this->generateSign('get', $this->baseUrl, $queries, $secret['key']);

        $response = $this->getHttpClient()->get($this->baseUrl, [
            'query' => $queries,
        ]);

        return json_decode($response->getBody()->getContents(), true)['data'];
    }

    /**
     * Map the raw user array to a Socialite User instance.
     *
     * @param array $user
     * @return \Overtrue\Socialite\User
     */
    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => $this->openId ?? null,
            'unionid' => $this->unionId ?? null,
            'name' => $user['nickname'] ?? null,
            'nickname' => $user['nickname'] ?? null,
            'email' => $user['email'] ?? null,
        ]);
    }

    /**
     * Generate the signature for QCloud request.
     *
     * @param string $method
     * @param string $url
     * @param array $params
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
     * Get the access token from the token response body.
     *
     * @param string $body
     *
     * @return array
     * @throws AuthorizeFailedException
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
            throw new BadResponseException('Bad Response', $body);
        }

        $this->openId = $body['data']['userOpenId'] ?? null;
        $this->unionId = $body['data']['userUnionId'] ?? null;

        return $body['data'];
    }

    /**
     * Get temple secretId and secretKey for get user info.
     *
     * @param string $accessToken
     *
     * @return array
     */
    protected function getTmpSecretIdAndKey(string $accessToken)
    {
        $nonce = rand();
        $timestamp = time();
        $params = [
            'Action' => 'ThGetFederationToken',
            'SecretId' => $this->config['secret_id'],
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
}
