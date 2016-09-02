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
use Overtrue\Socialite\ProviderInterface;
use Overtrue\Socialite\User;

/**
 * Class QQProvider.
 *
 * @link http://wiki.connect.qq.com/oauth2-0%E7%AE%80%E4%BB%8B [QQ - OAuth 2.0 登录QQ]
 */
class QQProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The base url of QQ API.
     *
     * @var string
     */
    protected $baseUrl = 'https://graph.qq.com';

    /**
     * User openid.
     *
     * @var string
     */
    protected $openId;

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['get_user_info'];

    /**
     * The uid of user authorized.
     *
     * @var int
     */
    protected $uid;

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $state
     *
     * @return string
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->baseUrl.'/oauth2.0/authorize', $state);
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl()
    {
        return $this->baseUrl.'/oauth2.0/token';
    }

    /**
     * Get the Post fields for the token request.
     *
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields($code)
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }

    /**
     * Get the access token for the given code.
     *
     * @param string $code
     *
     * @return \Overtrue\Socialite\AccessToken
     */
    public function getAccessToken($code)
    {
        $response = $this->getHttpClient()->get($this->getTokenUrl(), [
            'query' => $this->getTokenFields($code),
        ]);

        return $this->parseAccessToken($response->getBody()->getContents());
    }

    /**
     * Get the access token from the token response body.
     *
     * @param string $body
     *
     * @return \Overtrue\Socialite\AccessToken
     */
    public function parseAccessToken($body)
    {
        parse_str($body, $token);

        return new AccessToken($token);
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param \Overtrue\Socialite\AccessTokenInterface $token
     *
     * @return array
     */
    protected function getUserByToken(AccessTokenInterface $token)
    {
        $response = $this->getHttpClient()->get($this->baseUrl.'/oauth2.0/me?access_token='.$token->getToken());

        $this->openId = json_decode($this->removeCallback($response->getBody()->getContents()), true)['openid'];

        $queries = [
            'access_token'       => $token->getToken(),
            'openid'             => $this->openId,
            'oauth_consumer_key' => $this->clientId,
        ];

        $response = $this->getHttpClient()->get($this->baseUrl.'/user/get_user_info?'.http_build_query($queries));

        return json_decode($this->removeCallback($response->getBody()->getContents()), true);
    }

    /**
     * Map the raw user array to a Socialite User instance.
     *
     * @param array $user
     *
     * @return \Overtrue\Socialite\User
     */
    protected function mapUserToObject(array $user)
    {
        return new User([
            'id'       => $this->openId,
            'nickname' => $this->arrayItem($user, 'nickname'),
            'name'     => $this->arrayItem($user, 'nickname'),
            'email'    => $this->arrayItem($user, 'email'),
            'avatar'   => $this->arrayItem($user, 'figureurl_qq_2'),
        ]);
    }

    /**
     * Remove the fucking callback parentheses.
     *
     * @param string $response
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
