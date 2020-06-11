<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\User;

class WeWorkProvider extends AbstractProvider
{
    public const NAME = 'wework';
    protected ?int $agentId;
    protected bool $detailed = false;

    /**
     * @param int $agentId
     *
     * @return $this
     */
    public function setAgentId(int $agentId)
    {
        $this->agentId = $agentId;

        return $this;
    }

    public function detailed(): self
    {
        $this->detailed = true;

        return $this;
    }

    protected function getAuthUrl(): string
    {
        // 网页授权登录
        if (!empty($this->scopes)) {
            return $this->getOAuthUrl();
        }

        // 第三方网页应用登录（扫码登录）
        return $this->getQrConnectUrl();
    }

    protected function getOAuthUrl(): string
    {
        $queries = [
            'appid' => $this->getClientId(),
            'redirect_uri' => $this->redirectUrl,
            'response_type' => 'code',
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'agentid' => $this->agentId,
            'state' => $this->state,
        ];

        return sprintf('https://open.weixin.qq.com/connect/oauth2/authorize?%s#wechat_redirect', http_build_query($queries));
    }

    protected function getQrConnectUrl()
    {
        $queries = [
            'appid' => $this->getClientId(),
            'agentid' => $this->agentId,
            'redirect_uri' => $this->redirectUrl,
            'state' => $this->state,
        ];

        return 'https://open.work.weixin.qq.com/wwopen/sso/qrConnect?'.http_build_query($queries);
    }

    protected function getTokenUrl(): string
    {
        return '';
    }

    /**
     * @param string     $token
     * @param array|null $query
     *
     * @return array
     */
    protected function getUserByToken(string $token, ?array $query = []): array
    {
        $userInfo = $this->getUserInfo($token, $query['code']);

        if ($this->detailed && isset($userInfo['user_ticket'])) {
            return $this->getUserDetail($token, $userInfo['user_ticket']);
        }

        $this->detailed = false;

        return $userInfo;
    }

    /**
     * @param string $token
     * @param string $code
     *
     * @return mixed
     */
    protected function getUserInfo(string $token, string $code): array
    {
        $response = $this->getHttpClient()->get('https://qyapi.weixin.qq.com/cgi-bin/user/getuserinfo', [
            'query' => array_filter([
                'access_token' => $token,
                'code' => $code,
            ]),
        ]);

        return \json_decode($response->getBody(), true) ?? [];
    }

    /**
     * @param string $token
     * @param string $ticket
     *
     * @return mixed
     */
    protected function getUserDetail(string $token, string $ticket): array
    {
        $response = $this->getHttpClient()->post('https://qyapi.weixin.qq.com/cgi-bin/user/getuserdetail', [
            'query' => [
                'access_token' => $token,
            ],
            'json' => [
                'user_ticket' => $ticket,
            ],
        ]);

        return \json_decode($response->getBody(), true) ?? [];
    }

    /**
     * @param array $user
     *
     * @return \Overtrue\Socialite\User
     */
    protected function mapUserToObject(array $user): User
    {
        if ($this->detailed) {
            return new User([
                'id' => $user['userid'] ?? null,
                'name' => $user['name'] ?? null,
                'avatar' => $user['avatar'] ?? null,
                'email' => $user['email'] ?? null,
            ]);
        }

        return new User(array_filter([
            'id' => $user['UserId'] ?? null ?: $user['OpenId'] ?? null,
            'userId' => $user['UserId'] ?? null,
            'openid' => $user['OpenId'] ?? null,
            'deviceId' => $user['DeviceId'] ?? null,
        ]));
    }
}
