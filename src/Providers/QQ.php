<?php

namespace Overtrue\Socialite\Providers;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Overtrue\Socialite\Contracts\UserInterface;
use Overtrue\Socialite\User;

/**
 * @see http://wiki.connect.qq.com/oauth2-0%E7%AE%80%E4%BB%8B [QQ - OAuth 2.0 登录QQ]
 */
class QQ extends Base
{
    public const NAME = 'qq';
    protected string $baseUrl = 'https://graph.qq.com';
    protected array $scopes = ['get_user_info'];
    protected bool $withUnionId = false;

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl.'/oauth2.0/authorize');
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl.'/oauth2.0/token';
    }

    #[ArrayShape([
        'client_id' => "\null|string",
        'client_secret' => "\null|string",
        'code' => "string",
        'redirect_uri' => "mixed"
    ])]
    protected function getTokenFields(string $code): array
    {
        return parent::getTokenFields($code) + [
            'grant_type' => 'authorization_code',
        ];
    }

    /**
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function tokenFromCode(string $code): array
    {
        $response = $this->getHttpClient()->get($this->getTokenUrl(), [
            'query' => $this->getTokenFields($code),
        ]);

        \parse_str($response->getBody()->getContents(), $token);

        return $this->normalizeAccessTokenResponse($token);
    }

    public function withUnionId(): static
    {
        $this->withUnionId = true;

        return $this;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUserByToken(string $token): array
    {
        $url = $this->baseUrl.'/oauth2.0/me?fmt=json&access_token='.$token;
        $this->withUnionId && $url .= '&unionid=1';

        $response = $this->getHttpClient()->get($url);

        $me = \json_decode($response->getBody()->getContents(), true);

        $queries = [
            'access_token' => $token,
            'fmt' => 'json',
            'openid' => $me['openid'],
            'oauth_consumer_key' => $this->getClientId(),
        ];

        $response = $this->getHttpClient()->get($this->baseUrl.'/user/get_user_info?'.http_build_query($queries));

        return (\json_decode($response->getBody()->getContents(), true) ?? []) + [
            'unionid' => $me['unionid'] ?? null,
            'openid' => $me['openid'] ?? null,
        ];
    }

    #[Pure]
    protected function mapUserToObject(array $user): UserInterface
    {
        return new User([
            'id' => $user['openid'] ?? null,
            'name' => $user['nickname'] ?? null,
            'nickname' => $user['nickname'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['figureurl_qq_2'] ?? null,
        ]);
    }
}
