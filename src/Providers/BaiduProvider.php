<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\User;

/**
 * @see https://developer.baidu.com/wiki/index.php?title=docs/oauth [OAuth 2.0 授权机制说明]
 */
class BaiduProvider extends AbstractProvider
{
    /**
     * @var string
     */
    protected $baseUrl = 'https://openapi.baidu.com';

    /**
     * @var string
     */
    protected $version = '2.0';

    /**
     * @var array
     */
    protected $scopes = [''];

    /**
     * @var string
     */
    protected $display = 'popup';

    /**
     *
     * @return string
     */
    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl.'/oauth/'.$this->version.'/authorize');
    }

    protected function getCodeFields(): array
    {
        return [
            'response_type' => 'code',
            'client_id' => $this->getClientId(),
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'display' => $this->display,
        ] + $this->parameters;
    }

    /**
     * @return string
     */
    protected function getTokenUrl(): string
    {
        return $this->baseUrl.'/oauth/'.$this->version.'/token';
    }

    /**
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields($code): array
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }

    /**
     * @param string $token
     *
     * @return array
     */
    protected function getUserByToken(string $token): array
    {
        $response = $this->getHttpClient()->get($this->baseUrl.'/rest/'.$this->version.'/passport/users/getInfo', [
            'query' => [
                'access_token' => $token,
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        return json_decode($response->getBody(), true) ?? [];
    }

    /**
     * @param array $user
     *
     * @return \Overtrue\Socialite\User
     */
    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => $user['userid'] ?? null,
            'nickname' => $user['realname'] ?? null,
            'name' => $user['username'] ?? null,
            'email' => '',
            'avatar' => $user['portrait'] ?? null,
        ]);
    }
}
