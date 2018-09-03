<?php
namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\AccessTokenInterface;
use Overtrue\Socialite\ProviderInterface;
use Overtrue\Socialite\User;

use Payment\Utils\ArrayUtil;
use Payment\Utils\Rsa2Encrypt;
use Payment\Utils\StrUtil;

class AlipayProvider extends AbstractProvider implements  ProviderInterface
{

    protected $baseUrl = 'https://openapi.alipay.com/gateway.do';

    public function getTokenUrl()
    {
        return $this->baseUrl;
    }

    public function getAuthUrl($state)
    {
        return $this->baseUrl;
    }


    public function getTokenFields($code)
    {
        $params = [];
        $params['app_id'] = $this->clientId;
        $params['method'] = 'alipay.system.oauth.token';
        $params['format'] = 'JSON';
        $params['charset'] = 'utf-8';
        $params['sign_type'] = 'RSA2';
        $params['timestamp'] = date('Y-m-d H:i:s');
        $params['version'] = '1.0';
        $params['grant_type'] = 'authorization_code';
        $params['code'] = $code;
        $params = ArrayUtil::arraySort($params);
        $str = ArrayUtil::createLinkstring($params);
        $rsa = new Rsa2Encrypt(StrUtil::getRsaKeyValue($this->clientSecret, 'private'));
        $params['sign'] = $rsa->encrypt($str);

        return $params;
    }

    public function getAccessToken($code)
    {
        $response = $this->getHttpClient()->get($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'query' => $this->getTokenFields($code),
        ]);

        $result = json_decode($response->getBody(), true);
        if (isset($result['alipay_system_oauth_token_response'])) {
            $result = $result['alipay_system_oauth_token_response'];
            if (isset($result['access_token'])) {
                return $this->parseAccessToken($result);
            }
        }

        throw new \Exception('alipay.system.oauth.token:'.$response->getBody());
    }

    public function getUserByToken(AccessTokenInterface $token)
    {
        $params = [];
        $params['app_id'] = $this->clientId;
        $params['method'] = 'alipay.user.info.share';
        $params['format'] = 'JSON';
        $params['charset'] = 'utf-8';
        $params['sign_type'] = 'RSA2';
        $params['timestamp'] = date('Y-m-d H:i:s');
        $params['version'] = '1.0';
        $params['auth_token'] = $token->getToken();

        $params = ArrayUtil::arraySort($params);
        $str = ArrayUtil::createLinkstring($params);
        $rsa = new Rsa2Encrypt(StrUtil::getRsaKeyValue($this->clientSecret, 'private'));
        $params['sign'] = $rsa->encrypt($str);

        $response = $this->getHttpClient()->get($this->baseUrl, [
            'query' => array_filter($params),
        ]);

        $result = json_decode($response->getBody(), true);
        if (isset($result['alipay_user_info_share_response'])) {
            $result = $result['alipay_user_info_share_response'];
            if (isset($result['code']) && $result['code'] == '10000') {
                unset($result['code'], $result['msg']);
                return $result;
            }
        }

        throw new \Exception('alipay.user.info.share:'.$response->getBody());
    }

    protected function mapUserToObject(array $user)
    {
        return new User([
            'id' => $this->arrayItem($user, 'user_id'),
            'name' => $this->arrayItem($user, 'nick_name'),
            'nickname' => $this->arrayItem($user, 'nick_name'),
            'avatar' => $this->arrayItem($user, 'avatar'),
            'email' => null
        ]);
    }

}