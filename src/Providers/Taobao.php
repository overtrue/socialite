<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\User;

/**
 * @see https://open.taobao.com/doc.htm?docId=102635&docType=1&source=search [Taobao - OAuth 2.0 授权登录]
 */
class Taobao extends Base
{
    public const NAME = 'taobao';
    protected string $baseUrl = 'https://oauth.taobao.com';
    protected string $gatewayUrl = 'https://eco.taobao.com/router/rest';
    protected string $view = 'web';
    protected array $scopes = ['user_info'];

    public function withView(string $view): self
    {
        $this->view = $view;

        return $this;
    }

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl.'/authorize');
    }

    public function getCodeFields(): array
    {
        return [
            'client_id' => $this->getClientId(),
            'redirect_uri' => $this->redirectUrl,
            'view' => $this->view,
            'response_type' => 'code',
        ];
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl.'/token';
    }

    /**
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields($code): array
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code', 'view' => $this->view];
    }

    /**
     * @param  string  $code
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     */
    public function tokenFromCode(string $code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'query' => $this->getTokenFields($code),
        ]);

        return $this->normalizeAccessTokenResponse($response->getBody()->getContents());
    }

    /**
     * @param  string  $token
     * @param  array|null  $query
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUserByToken(string $token, ?array $query = []): array
    {
        $response = $this->getHttpClient()->post($this->getUserInfoUrl($this->gatewayUrl, $token));

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
            'id' => $user['open_id'] ?? null,
            'nickname' => $user['nick'] ?? null,
            'name' => $user['nick'] ?? null,
            'avatar' => $user['avatar'] ?? null,
            'email' => $user['email'] ?? null,
        ]);
    }

    /**
     * @param array $params
     *
     * @return string
     */
    protected function generateSign(array $params)
    {
        ksort($params);

        $stringToBeSigned = $this->getConfig()->get('client_secret');

        foreach ($params as $k => $v) {
            if (!is_array($v) && '@' != substr($v, 0, 1)) {
                $stringToBeSigned .= "$k$v";
            }
        }

        $stringToBeSigned .= $this->getConfig()->get('client_secret');

        return strtoupper(md5($stringToBeSigned));
    }

    /**
     * @param string $token
     * @param array  $apiFields
     *
     * @return array
     */
    protected function getPublicFields(string $token, array $apiFields = [])
    {
        $fields = [
            'app_key' => $this->getClientId(),
            'sign_method' => 'md5',
            'session' => $token,
            'timestamp' => \date('Y-m-d H:i:s'),
            'v' => '2.0',
            'format' => 'json',
        ];

        $fields = array_merge($apiFields, $fields);
        $fields['sign'] = $this->generateSign($fields);

        return $fields;
    }

    /**
     * @param string $url
     * @param string $token
     *
     * @return string
     */
    protected function getUserInfoUrl(string $url, string $token)
    {
        $apiFields = ['method' => 'taobao.miniapp.userInfo.get'];

        $query = http_build_query($this->getPublicFields($token, $apiFields), '', '&', $this->encodingType);

        return $url.'?'.$query;
    }
}
