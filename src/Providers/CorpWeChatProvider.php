<?php

/*
 * This file is part of the overtrue/socialite.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\AccessToken;
use Overtrue\Socialite\AccessTokenInterface;
use Overtrue\Socialite\InvalidArgumentException;
use Overtrue\Socialite\ProviderInterface;
use Overtrue\Socialite\User;

/**
 * Class WeChatProvider.
 *
 * @link http://mp.weixin.qq.com/wiki/9/01f711493b5a02f24b04365ac5d8fd95.html [WeChat - 公众平台OAuth文档]
 * @link https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1419316505&token=&lang=zh_CN [网站应用微信登录开发指南]
 */
class CorpWechatProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The base url of WeChat API.
     *
     * @var string
     */
    protected $userBaseInfoApi = 'https://qyapi.weixin.qq.com/cgi-bin/user/getuserinfo';
    protected $userInfoApi = 'https://qyapi.weixin.qq.com/cgi-bin/user/get';
    protected $accessTokenApi = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken';
    protected $oauthApi= 'https://open.weixin.qq.com/connect/oauth2/authorize';

    /**
     * {@inheritdoc}.
     */
    protected $openId;

    /**
     * {@inheritdoc}.
     */
    protected $scopes = ['snsapi_base'];

    /**
     * Indicates if the session state should be utilized.
     *
     * @var bool
     */
    protected $stateless = true;

    /**
     * {@inheritdoc}.
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->oauthApi, $state);
    }

    /**
     * {@inheritdoc}.
     */
    protected function buildAuthUrlFromBase($url, $state)
    {
        $query = http_build_query($this->getCodeFields($state), '', '&', $this->encodingType);
        $url = $url.'?'.$query.'#wechat_redirect';
        return $url;
    }

    /**
     * {@inheritdoc}.
     */
    protected function getCodeFields($state = null)
    {

        $result = array_merge([
            'appid' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'response_type' => 'code',
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'state' => $state ?: md5(time()),
        ], $this->parameters);

        return $result;
    }

    /**
     * 获取 access token的路径.
     */
    protected function getTokenUrl()
    {
        return $this->accessTokenApi;
    }

    /**
     * {@inheritdoc}.
     */
    protected function getUserByToken(AccessTokenInterface $token)
    {
        if (empty($token['UserId'])) {
            throw new InvalidArgumentException('UserId of AccessToken is required.');
        }

        $response = $this->getHttpClient()->get($this->userInfoApi, [
            'query' => [
                'access_token' => $token->getToken(),
                'userid' => $token['UserId'],
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * {@inheritdoc}.
     */
    protected function mapUserToObject(array $user)
    {
        return new User([
            'userid' => $this->arrayItem($user, 'userid'),
            'name' => $this->arrayItem($user, 'name'),
            'avatar' => $this->arrayItem($user, 'avatar'),
            'mobile' => $this->arrayItem($user, 'mobile'),
            'department' => $this->arrayItem($user, 'department'),
            'gender' => $this->arrayItem($user, 'gender'),
            'email' => $this->arrayItem($user, 'email'),
            'status' => $this->arrayItem($user, 'status'),
        ]);
    }

    /**
     * 构建access_token 的参数列表, 分为两种情况一种是 获取access token, 另一种是直接获取userid
     */
    protected function getTokenFields($code = false)
    {

        if(!$code){
            return [
                'corpid' => $this->clientId,
                'corpsecret' => $this->clientSecret,
            ];
        }
        return [
            'access_token'=>$this->config['longlive_access_token'],
            'code'=>$code,
        ];    
    
     
    }

    /**
     * 原始微信oauth 应该是返回 access token + openid
     * 企业号因为用的是7200秒的, 所以需要支持从外部去获取access_token 不会冲突  要返回 userid 
     */
    public function getAccessToken($code)
    {
        //没有指定则自己获取
        if(!$this->config['longlive_access_token']){
            $this->config['longlive_access_token'] = $this->getLongiveAccessToken();
        }
        $param = $this->getTokenFields($code);
        $response = $this->getHttpClient()->get($this->userBaseInfoApi, [
            'query' => $param,
        ]);
        $content = $response->getBody()->getContents();
        $content = json_decode($content,true);
        $content['access_token'] =  $this->config['longlive_access_token'];
        $token = $this->parseAccessToken($content);
        return $token;

    }
    // !!应该尽量不要调用, 除非 单独与overture/wechat使用, 否则同时获取accesstoken, 会冲突
    public function getLongiveAccessToken($forse_refresh = false){
        $getTokenUrl = $this->getTokenUrl();
        $response = $this->getHttpClient()->get($getTokenUrl, [
            'query' => $this->getTokenFields(),
        ]);
        $content = $response->getBody()->getContents();
        $token = $this->parseAccessToken($content);
        return $token['access_token'];
    }

   

    /**
     * Remove the fucking callback parentheses.
     *
     * @param mixed $response
     *
     * @return string
     */
    protected function removeCallback($response)
    {
        if (strpos($response, 'callback') !== false) {
            $lpos = strpos($response, '(');
            $rpos = strrpos($response, ')');
            $response = substr($response, $lpos + 1, $rpos - $lpos - 1);
        }

        return $response;
    }
}
