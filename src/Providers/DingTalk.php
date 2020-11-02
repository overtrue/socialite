<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\User;

/**
 * “第三方个人应用”获取用户信息
 *
 * @see https://ding-doc.dingtalk.com/doc#/serverapi3/mrugr3
 *
 * 暂不支持“第三方企业应用”获取用户信息
 * @see https://ding-doc.dingtalk.com/doc#/serverapi3/hv357q
 */
class DingTalk extends Base
{
    public const NAME = 'dingtalk';
    protected string $getUserByCode = 'https://oapi.dingtalk.com/sns/getuserinfo_bycode';
    protected array $scopes = ['snsapi_login'];

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase('https://oapi.dingtalk.com/connect/qrconnect');
    }

    protected function getTokenUrl(): string
    {
        throw new \InvalidArgumentException('not supported to get access token.');
    }

    /**
     * @param string $token
     *
     * @return array
     */
    protected function getUserByToken(string $token): array
    {
        throw new \InvalidArgumentException('Unable to use token get User.');
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
                'name' => $user['nick'] ?? null,
                'nickname' => $user['nick'] ?? null,
                'id' => $user['openid'] ?? null,
                'email' => null,
                'avatar' => null,
            ]
        );
    }

    protected function getCodeFields(): array
    {
        return array_merge(
            [
                'appid' => $this->getClientId(),
                'response_type' => 'code',
                'scope' => implode($this->scopes),
                'redirect_uri' => $this->redirectUrl,
            ],
            $this->parameters
        );
    }

    public function getClientId(): ?string
    {
        return $this->getConfig()->get('app_id') ?? $this->getConfig()->get('appid') ?? $this->getConfig()->get('appId')
            ?? $this->getConfig()->get('client_id');
    }

    public function getClientSecret(): ?string
    {
        return $this->getConfig()->get('app_secret') ?? $this->getConfig()->get('appSecret')
            ?? $this->getConfig()->get('client_secret');
    }

    protected function createSignature(int $time)
    {
        return base64_encode(hash_hmac('sha256', $time, $this->getClientSecret(), true));
    }

    /**
     * @param  string  $code
     *
     * @return User
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @see https://ding-doc.dingtalk.com/doc#/personnal/tmudue
     */
    public function userFromCode(string $code): User
    {
        $time = (int)microtime(true) * 1000;
        $queryParams = [
            'accessKey' => $this->getClientId(),
            'timestamp' => $time,
            'signature' => $this->createSignature($time),
        ];

        $response = $this->getHttpClient()->post(
            $this->getUserByCode . '?' . http_build_query($queryParams),
            [
                'json' => ['tmp_auth_code' => $code],
            ]
        );
        $response = \json_decode($response->getBody()->getContents(), true);

        if (0 != $response['errcode'] ?? 1) {
            throw new \InvalidArgumentException('You get error: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return new User(
            [
                'name' => $response['user_info']['nick'],
                'nickname' => $response['user_info']['nick'],
                'id' => $response['user_info']['openid'],
            ]
        );
    }
}
