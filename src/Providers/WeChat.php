<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\Contracts\WeChatComponentInterface;
use Overtrue\Socialite\User;
use Psr\Http\Message\ResponseInterface;

/**
 * @see http://mp.weixin.qq.com/wiki/9/01f711493b5a02f24b04365ac5d8fd95.html [WeChat - 公众平台OAuth文档]
 * @see https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1419316505&token=&lang=zh_CN [网站应用微信登录开发指南]
 */
class WeChat extends Base
{
    public const NAME = 'wechat';
    protected string $baseUrl = 'https://api.weixin.qq.com/sns';
    protected array $scopes = ['snsapi_login'];
    protected bool $withCountryCode = false;
    protected WeChatComponentInterface $component;
    protected ?string $openid;

    /**
     * @param string $openid
     *
     * @return $this
     */
    public function withOpenid(string $openid): self
    {
        $this->openid = $openid;

        return $this;
    }

    public function withCountryCode()
    {
        $this->withCountryCode = true;

        return $this;
    }

    /**
     * @param \Overtrue\Socialite\Contracts\WeChatComponentInterface $component
     *
     * @return $this
     */
    public function component(WeChatComponentInterface $component)
    {
        $this->scopes = ['snsapi_base'];

        $this->component = $component;

        return $this;
    }

    /**
     * @param string $code
     *
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     *
     * @return array
     */
    public function tokenFromCode($code): array
    {
        $response = $this->getTokenFromCode($code);

        return $this->normalizeAccessTokenResponse($response->getBody()->getContents());
    }

    protected function getAuthUrl(): string
    {
        $path = 'oauth2/authorize';

        if (in_array('snsapi_login', $this->scopes)) {
            $path = 'qrconnect';
        }

        return $this->buildAuthUrlFromBase("https://open.weixin.qq.com/connect/{$path}");
    }

    /**
     * @param string $url
     *
     * @return string
     */
    protected function buildAuthUrlFromBase(string $url): string
    {
        $query = http_build_query($this->getCodeFields(), '', '&', $this->encodingType);

        return $url.'?'.$query.'#wechat_redirect';
    }

    protected function getCodeFields(): array
    {
        if ($this->component) {
            $this->with(array_merge($this->parameters, ['component_appid' => $this->component->getAppId()]));
        }

        return array_merge([
            'appid' => $this->getClientId(),
            'redirect_uri' => $this->redirectUrl,
            'response_type' => 'code',
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'state' => $this->state ?: md5(time()),
            'connect_redirect' => 1,
        ], $this->parameters);
    }

    protected function getTokenUrl(): string
    {
        if ($this->component) {
            return $this->baseUrl.'/oauth2/component/access_token';
        }

        return $this->baseUrl.'/oauth2/access_token';
    }

    /**
     * @param string $code
     *
     * @return \Overtrue\Socialite\User
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     */
    public function userFromCode(string $code): User
    {
        if (in_array('snsapi_base', $this->scopes)) {
            return $this->mapUserToObject(\json_decode($this->getTokenFromCode($code), true) ?? []);
        }

        $token = $this->tokenFromCode($code);

        $this->withOpenid($token['openid']);

        $user = $this->userFromToken($token[$this->accessTokenKey]);

        return $user->setRefreshToken($token['refresh_token'])
            ->setExpiresIn($token['expires_in']);
    }

    /**
     * @param string $token
     *
     * @return array
     */
    protected function getUserByToken(string $token): array
    {
        $language = $this->withCountryCode ? null : (isset($this->parameters['lang']) ? $this->parameters['lang'] : 'zh_CN');

        $response = $this->getHttpClient()->get($this->baseUrl.'/userinfo', [
            'query' => array_filter([
                'access_token' => $token,
                'openid' => $this->openid,
                'lang' => $language,
            ]),
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
        return new User([
            'id' => $user['openid'] ?? null,
            'name' => $user['nickname'] ?? null,
            'nickname' => $user['nickname'] ?? null,
            'avatar' => $user['headimgurl'] ?? null,
            'email' => null,
        ]);
    }

    /**
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields(string $code): array
    {
        return array_filter([
            'appid' => $this->getClientId(),
            'secret' => $this->getClientSecret(),
            'component_appid' => $this->component ? $this->component->getAppId() : null,
            'component_access_token' => $this->component ? $this->component->getToken() : null,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * @param string $code
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function getTokenFromCode(string $code): ResponseInterface
    {
        return $this->getHttpClient()->get($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'query' => $this->getTokenFields($code),
        ]);
    }
}
