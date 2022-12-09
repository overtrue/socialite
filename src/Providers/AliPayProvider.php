<?php

namespace Overtrue\Socialite\Providers;

use InvalidArgumentException;
use Overtrue\Socialite\AccessToken;
use Overtrue\Socialite\AccessTokenInterface;
use Overtrue\Socialite\AuthorizeFailedException;
use Overtrue\Socialite\ProviderInterface;
use Overtrue\Socialite\User;

/**
 * Class AliPayProvider.
 *
 * @see https://opendocs.alipay.com/open/284/106000 [获取会员信息]
 */
class AliPayProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The base url of AliPay API.
     *
     * @var string
     */
    protected $baseUrl = 'https://openapi.alipay.com/gateway.do';

    /**
     * The base url of AliPay API.
     *
     * @var string
     */
    protected $authUrl = 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm';

    /**
     * The API version for the request.
     *
     * @var string
     */
    protected $version = '1.0';


    protected $signType = 'RSA2';

    protected $postCharset = 'UTF-8';

    protected $format = 'json';

    protected $sandbox = false;

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['auth_user'];

    /**
     * The uid of user authorized.
     *
     * @var int
     */
    protected $uid;

    /**
     * AliPayProvider constructor.
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param $config
     */
    public function __construct($request, $config)
    {
        if($request->get('code') == "") {
            $request->query->set("code",$request->get("auth_code"));
        }
        parent::__construct($request,$config);
        $this->sandbox = $this->getConfig()->get('sandbox', false);
        if ($this->sandbox) {
            $this->baseUrl = 'https://openapi.alipaydev.com/gateway.do';
            $this->authUrl = 'https://openauth.alipaydev.com/oauth2/publicAppAuthorize.htm';
        }
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $state
     *
     * @return string
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->authUrl, $state);
    }

    /**
     * {@inheritdoc}.
     */
    protected function getCodeFields($state = null)
    {
        return array_merge([
            'response_type' => 'code',
            'app_id' => $this->getConfig()->get('client_id'),
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
        ], $this->parameters);
    }

    /**
     * {@inheritdoc}.
     */
    protected function getPublicFields($method)
    {
        return [
            'app_id' => $this->getConfig()->get('client_id'),
            'charset' => $this->postCharset,
            'method' => $method,
            'format' => $this->format,
            'sign_type' => $this->signType,
            'timestamp' => date("Y-m-d H:i:s"),
            'version' => $this->version,
        ];
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl()
    {
        return $this->baseUrl;
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
        $params = $this->getPublicFields('alipay.system.oauth.token');
        $params += [
            "code" => $code,
            "grant_type" => "authorization_code",
        ];
        $params['sign'] = $this->generateSign($params);

        return $params;
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
        $params = $this->getPublicFields('alipay.user.info.share');
        $params += ['auth_token' => $token];
        $params['sign'] = $this->generateSign($params);

        $response = $this->getHttpClient()->post($this->baseUrl, [
            'form_params' => $params,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8',
            ],
        ]);

        return json_decode($response->getBody(), true);
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
            'id' => $this->arrayItem($user, 'user_id'),
            'nickname' => $this->arrayItem($user, 'nick_name'),
            'name' => $this->arrayItem($user, 'nick_name'),
            'email' => '',
            'avatar' => $this->arrayItem($user, 'avatar'),
        ]);
    }

    /**
     * @see https://opendocs.alipay.com/open/289/105656
     */
    protected function generateSign($params)
    {
        \ksort($params);

        return $this->signWithSHA256RSA($this->buildParams($params), $this->getConfig()->get('rsa_private_key'));
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function signWithSHA256RSA($signContent, $key)
    {
        if (empty($key)) {
            throw new InvalidArgumentException('no RSA private key set.');
        }

        $key = "-----BEGIN RSA PRIVATE KEY-----\n" .
            \chunk_split($key, 64, "\n") .
            '-----END RSA PRIVATE KEY-----';

        \openssl_sign($signContent, $signValue, $key, \OPENSSL_ALGO_SHA256);

        return \base64_encode($signValue);
    }

    public static function buildParams($params, $urlencode = false, $except = ['sign'])
    {
        $param_str = '';
        foreach ($params as $k => $v) {
            if (\in_array($k, $except)) {
                continue;
            }
            $param_str .= $k . '=';
            $param_str .= $urlencode ? \rawurlencode($v) : $v;
            $param_str .= '&';
        }

        return \rtrim($param_str, '&');

    }

    /**
     * Determine if the provider is operating as stateless.
     *
     * @return bool
     */
    protected function isStateless()
    {
        return true;
    }

    /**
     * Get the access token from the token response body.
     *
     * @param \Psr\Http\Message\StreamInterface|array $body
     *
     * @return \Overtrue\Socialite\AccessTokenInterface
     */
    protected function parseAccessToken($body)
    {
        if (!is_array($body)) {
            $body = json_decode($body, true);
        }

        if (empty($body['alipay_system_oauth_token_response']['access_token'])) {
            throw new AuthorizeFailedException('Authorize Failed: '.json_encode($body, JSON_UNESCAPED_UNICODE), $body);
        }

        return new AccessToken($body['alipay_system_oauth_token_response']);
    }
}
