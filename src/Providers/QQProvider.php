<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\User;

/**
 * @see http://wiki.connect.qq.com/oauth2-0%E7%AE%80%E4%BB%8B [QQ - OAuth 2.0 登录QQ]
 */
class QQProvider extends AbstractProvider
{
    /**
     * @var bool
     */
    protected $withUnionId = false;

    /**
     * @var string
     */
    protected $baseUrl = 'https://graph.qq.com';

    /**
     * @var array
     */
    protected $scopes = ['get_user_info'];

    /**
     * @return string
     */
    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl . '/oauth2.0/authorize');
    }

    /**
     * @return string
     */
    protected function getTokenUrl(): string
    {
        return $this->baseUrl . '/oauth2.0/token';
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
     * @param string $code
     *
     * @return string
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     */
    public function tokenFromCode($code): string
    {
        $response = $this->getHttpClient()->get($this->getTokenUrl(), [
            'query' => $this->getTokenFields($code),
        ]);

        \parse_str($response->getBody()->getContents(), $token);

        return $this->parseAccessToken($token);
    }

    /**
     * @return self
     */
    public function withUnionId()
    {
        $this->withUnionId = true;

        return $this;
    }

    /**
     *
     * @param string     $token
     * @param array|null $query
     *
     * @return array
     */
    protected function getUserByToken(string $token, ?array $query = []): array
    {
        $url = $this->baseUrl . '/oauth2.0/me?access_token=' . $token;
        $this->withUnionId && $url .= '&unionid=1';

        $response = $this->getHttpClient()->get($url);

        $me = json_decode($this->removeCallback($response->getBody()->getContents()), true);

        $queries = [
            'access_token' => $token,
            'openid' => $me['openid'],
            'oauth_consumer_key' => $this->getClientId(),
        ];

        $response = $this->getHttpClient()->get($this->baseUrl . '/user/get_user_info?' . http_build_query($queries));

        return \json_decode($this->removeCallback($response->getBody()->getContents()), true) ?? [] + [
                'unionid' => $me['unionid'] ?? null,
                'openid' => $me['openid'] ?? null,
            ];
    }

    /**
     * @param array $user
     *
     * @return \Overtrue\Socialite\User
     */
    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => $user['openid'] ?? null,
            'nickname' => $user['nickname'] ?? null,
            'name' => $user['nickname'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['figureurl_qq_2'] ?? null,
        ]);
    }

    /**
     * @param string $response
     *
     * @return string
     */
    protected function removeCallback($response)
    {
        if (false !== strpos($response, 'callback')) {
            $lpos = strpos($response, '(');
            $rpos = strrpos($response, ')');
            $response = substr($response, $lpos + 1, $rpos - $lpos - 1);
        }

        return $response;
    }
}
