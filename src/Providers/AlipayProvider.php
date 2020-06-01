<?php


namespace Overtrue\Socialite\Providers;


use Overtrue\Socialite\User;

/**
 * Class AlipayProvider
 * @package Overtrue\Socialite\Providers
 *
 * @see https://opendocs.alipay.com/open/289/105656
 */
class AlipayProvider extends AbstractProvider
{

    protected $baseUrl = 'https://openapi.alipay.com/gateway.do';

    protected $scopes = ['auth_user'];

    protected $apiVersion = '1.0';

    protected $signType = 'RSA2';

    protected $postCharset = 'UTF-8';

    protected $format = 'json';

    protected function getAuthUrl(): string
    {
        $this->buildAuthUrlFromBase('https://openauth.alipay.com/oauth2/publicAppAuthorize.htm');
    }

    protected function getTokenUrl(): string
    {
        return 'https://openapi.alipay.com/gateway.do';
    }

    protected function getUserByToken(string $token): array
    {
        // TODO: Implement getUserByToken() method.
    }

    protected function mapUserToObject(array $user): User
    {
        // TODO: Implement mapUserToObject() method.
    }

    protected function getCodeFields(): array
    {
        return [
            'app_id' => $this->getConfig()->get('client_id'),
            'scope' => $this->scopes,
            'redirect_uri' => $this->redirectUrl
        ];
    }

    protected function getTokenFields(): array
    {
        $params = [
            'app_id' => $this->getConfig()->get('client_id') ?? $this->getConfig()->get('app_id'),
            'method' => 'alipay.system.oauth.token',
            'format' => $this->format,
            'charset' => $this->postCharset,
            'sign_type' => $this->signType,
            'timestamp' => date('y-m-d H:m:s'),
            'version' => $this->apiVersion,
        ];


        $sign = $this->generateSign();

    }

    protected function generateSign($method, $url, $params, $sha = 'HmacSHA256', $key = null)
    {
        $params['SignatureMethod'] = $sha;

        if (empty($key)) {
            $key = $this->config->get('secret_key');
        }

        ksort($params);

        foreach ($params as $k => $v) {
            if (strpos($k, '_')) {
                str_replace('_', '.', $k);
            }
        }

        $srcStr = sprintf('%s%s?%s', strtoupper($method), $url, http_build_query($params));

        return base64_encode(hash_hmac($sha, $srcStr, $key, true));
    }
}
