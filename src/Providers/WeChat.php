<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\Exceptions\InvalidArgumentException;
use Overtrue\Socialite\User;
use Psr\Http\Message\ResponseInterface;

/**
 * @see http://mp.weixin.qq.com/wiki/9/01f711493b5a02f24b04365ac5d8fd95.html [WeChat - 公众平台OAuth文档]
 * @see https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1419316505&token=&lang=zh_CN
 *      [网站应用微信登录开发指南]
 */
class WeChat extends Base
{
    public const NAME = 'wechat';
    protected string $baseUrl = 'https://api.weixin.qq.com/sns';
    protected array $scopes = ['snsapi_login'];
    protected bool $withCountryCode = false;
    protected ?array $component = null;
    protected ?string $openid = null;

    public function __construct(array $config)
    {
        parent::__construct($config);

        if ($this->getConfig()->has('component')) {
            $this->prepareForComponent((array) $this->getConfig()->get('component'));
        }
    }

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
     * @param  string  $code
     *
     * @return array
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException|\GuzzleHttp\Exception\GuzzleException
     */
    public function tokenFromCode(string $code): array
    {
        $response = $this->getTokenFromCode($code);

        return $this->normalizeAccessTokenResponse($response->getBody()->getContents());
    }

    /**
     * @param  array  $componentConfig  ['id' => xxx, 'token' => xxx]
     *
     * @return \Overtrue\Socialite\Providers\WeChat
     * @throws \Overtrue\Socialite\Exceptions\InvalidArgumentException
     */
    public function withComponent(array $componentConfig)
    {
        $this->prepareForComponent($componentConfig);

        return $this;
    }

    public function getComponent()
    {
        return $this->component;
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

        return $url . '?' . $query . '#wechat_redirect';
    }

    protected function getCodeFields(): array
    {
        if (!empty($this->component)) {
            $this->with(array_merge($this->parameters, ['component_appid' => $this->component['id']]));
        }

        return array_merge([
            'appid' => $this->getClientId(),
            'redirect_uri' => $this->redirectUrl,
            'response_type' => 'code',
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'state' => $this->state ?: md5(uniqid()),
            'connect_redirect' => 1,
        ], $this->parameters);
    }

    protected function getTokenUrl(): string
    {
        if (!empty($this->component)) {
            return $this->baseUrl . '/oauth2/component/access_token';
        }

        return $this->baseUrl . '/oauth2/access_token';
    }

    /**
     * @param string $code
     *
     * @return \Overtrue\Socialite\User
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException|\GuzzleHttp\Exception\GuzzleException
     */
    public function userFromCode(string $code): User
    {
        if (in_array('snsapi_base', $this->scopes)) {
            return $this->mapUserToObject(\json_decode($this->getTokenFromCode($code)->getBody()->getContents(), true) ?? []);
        }

        $token = $this->tokenFromCode($code);

        $this->withOpenid($token['openid']);

        $user = $this->userFromToken($token[$this->accessTokenKey]);

        return $user->setRefreshToken($token['refresh_token'])
            ->setExpiresIn($token['expires_in'])
            ->setTokenResponse($token);
    }

    /**
     * @param  string  $token
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUserByToken(string $token): array
    {
        $language = $this->withCountryCode ? null : (isset($this->parameters['lang']) ? $this->parameters['lang'] : 'zh_CN');

        $response = $this->getHttpClient()->get($this->baseUrl . '/userinfo', [
            'query' => array_filter([
                'access_token' => $token,
                'openid' => $this->openid,
                'lang' => $language,
            ]),
        ]);

        return \json_decode($response->getBody()->getContents(), true) ?? [];
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
        if (!empty($this->component)) {
            return [
                'appid' => $this->getClientId(),
                'component_appid' => $this->component['id'],
                'component_access_token' => $this->component['token'],
                'code' => $code,
                'grant_type' => 'authorization_code',
            ];
        }

        return [
            'appid' => $this->getClientId(),
            'secret' => $this->getClientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];
    }

    /**
     * @param  string  $code
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getTokenFromCode(string $code): ResponseInterface
    {
        return $this->getHttpClient()->get($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'query' => $this->getTokenFields($code),
        ]);
    }

    protected function prepareForComponent(array $component)
    {
        $config = [];
        foreach ($component as $key => $value) {
            if (\is_callable($value)) {
                $value = \call_user_func($value, $this);
            }

            switch ($key) {
                case 'id':
                case 'app_id':
                case 'component_app_id':
                    $config['id'] = $value;
                    break;
                case 'token':
                case 'app_token':
                case 'access_token':
                case 'component_access_token':
                    $config['token'] = $value;
                    break;
            }
        }

        if (2 !== count($config)) {
            throw new InvalidArgumentException('Please check your config arguments is available.');
        }

        if (1 === count($this->scopes) && in_array('snsapi_login', $this->scopes)) {
            $this->scopes = ['snsapi_base'];
        }

        $this->component = $config;
    }
}
