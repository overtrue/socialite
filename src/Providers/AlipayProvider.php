<?php


namespace Overtrue\Socialite\Providers;


use GuzzleHttp\Exception\BadResponseException;
use Overtrue\Socialite\Exceptions\InvalidArgumentException;
use Overtrue\Socialite\User;

/**
 * Class AlipayProvider
 * @package Overtrue\Socialite\Providers
 *
 * @see https://opendocs.alipay.com/open/289/105656
 *
 * 一定要看这个做配置哦
 * @see https://opendocs.alipay.com/open/200/105310#s2
 */
class AlipayProvider extends AbstractProvider
{

    protected $baseUrl = 'https://openapi.alipay.com/gateway.do';

    protected $scopes = ['auth_user'];

    protected $apiVersion = '1.0';

    protected $signType = 'RSA2';

    protected $postCharset = 'UTF-8';

    protected $format = 'json';

    protected $withPrivateKey = true;

    public function witPublicKey(): self
    {
        $this->withPrivateKey = false;

        return $this;
    }

    public function withPrivateKey(): self
    {
        $this->withPrivateKey = true;

        return $this;
    }

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase('https://openauth.alipay.com/oauth2/publicAppAuthorize.htm');
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl;
    }

    protected function getUserByToken(string $token): array
    {
        $params = $this->getPublicFields();
        $params += ['method' => 'alipay.user.info.share', 'auth_token' => $token];
        $params['sign'] = $this->generateSign($params);

        $response = $this->getHttpClient()->post($this->baseUrl, [
            'form_params' => $params,
            'headers' => [
                "content-type" => "application/x-www-form-urlencoded;charset=utf-8"
            ]
        ]);

        $response = json_decode($response->getBody()->getContents(), true);

        if (!empty($response['error_response'])) {
            throw new \InvalidArgumentException('You have error! ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return $response['alipay_user_info_share_response'];
    }

    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => $user['user_id'] ?? null,
            'name' => $user['nick_name'] ?? null,
            'avatar' => $user['avatar'] ?? null,
            'email' => $user['email'] ?? null,
        ]);
    }

    public function tokenFromCode(string $code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'form_params' => $this->getTokenFields($code),
            'headers' => [
                "content-type" => "application/x-www-form-urlencoded;charset=utf-8"
            ]
        ]);
        $response = json_decode($response->getBody()->getContents(), true);

        if (!empty($response['error_response'])) {
            throw new \InvalidArgumentException('You have error! ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return $this->normalizeAccessTokenResponse($response['alipay_system_oauth_token_response']);    }

    protected function getCodeFields(): array
    {
        if (empty($this->redirectUrl)) {
            throw new InvalidArgumentException('Please set seem redirect URL like your Alipay Admin');
        }

        $fields = array_merge([
            'app_id' => $this->getConfig()->get('client_id') ?? $this->getConfig()->get('app_id'),
            'scope' => implode(',', $this->scopes),
            'redirect_uri' => $this->redirectUrl
        ], $this->parameters);

        return $fields;
    }

    protected function getTokenFields(string $code): array
    {
        $params = $this->getPublicFields() + ['method' => 'alipay.system.oauth.token'];
        $params += [
            'code' => $code,
            'grant_type' => 'authorization_code'
        ];
        $params['sign'] = $this->generateSign($params);

        return $params;
    }

    public function getPublicFields(): array
    {
        return [
            'app_id' => $this->getConfig()->get('client_id') ?? $this->getConfig()->get('app_id'),
            'format' => $this->format,
            'charset' => $this->postCharset,
            'sign_type' => $this->signType,
            'timestamp' => date('Y-m-d H:m:s'),
            'version' => $this->apiVersion,
        ];
    }

    /**
     * @param $params
     * @return string
     * @throws InvalidArgumentException
     *
     * @see https://opendocs.alipay.com/open/289/105656
     */
    protected function generateSign($params)
    {
        ksort($params);

        $signContent = $this->buildParams($params);
        // TODO: 写进　README
        $key = $this->withPrivateKey ? $this->getConfig()->get('private_RSA_key') : $this->getConfig()->get('public_RSA_key');
        $signValue = $this->signWithSHA256RSA($signContent, $key);

        return $signValue;
    }

    protected function signWithSHA256RSA(string $signContent, string $key)
    {
        if (empty($key)) {
            throw new InvalidArgumentException('no private RSA key set.');
        }

        if ($this->withPrivateKey) {
            $key = "-----BEGIN RSA PRIVATE KEY-----\n" .
                chunk_split($key, 64, "\n") .
                "-----END RSA PRIVATE KEY-----";
        } else {
            $key = "-----BEGIN PUBLIC KEY-----\n" .
                chunk_split($key, 64, "\n") .
                "-----END PUBLIC KEY-----";
        }

        openssl_sign($signContent, $signValue, $key, OPENSSL_ALGO_SHA256);

        return base64_encode($signValue);
    }

    public static function buildParams(array $params, bool $urlencode = false, array $except = ['sign'])
    {
        $param_str = '';
        foreach ($params as $k => $v) {
            if (in_array($k, $except)) {
                continue;
            }
            $param_str .= $k . '=';
            $param_str .= $urlencode ? rawurlencode($v) : $v;
            $param_str .= '&';
        }
        return rtrim($param_str, '&');
    }
}