<?php

namespace Overtrue\Socialite\Providers;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Overtrue\Socialite\Contracts\UserInterface;
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

    public function withView(string $view): static
    {
        $this->view = $view;

        return $this;
    }

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl.'/authorize');
    }

    #[ArrayShape([
        'client_id' => "null|string",
        'redirect_uri' => "mixed",
        'view' => "string",
        'response_type' => "string"
    ])]
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

    #[ArrayShape([
        'client_id' => "\null|string",
        'client_secret' => "\null|string",
        'code' => "string",
        'redirect_uri' => "mixed"
    ])]
    protected function getTokenFields($code): array
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code', 'view' => $this->view];
    }

    /**
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
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUserByToken(string $token, ?array $query = []): array
    {
        $response = $this->getHttpClient()->post($this->getUserInfoUrl($this->gatewayUrl, $token));

        return \json_decode($response->getBody()->getContents(), true) ?? [];
    }

    #[Pure]
    protected function mapUserToObject(array $user): UserInterface
    {
        return new User([
            'id' => $user['open_id'] ?? null,
            'nickname' => $user['nick'] ?? null,
            'name' => $user['nick'] ?? null,
            'avatar' => $user['avatar'] ?? null,
            'email' => $user['email'] ?? null,
        ]);
    }

    protected function generateSign(array $params): string
    {
        ksort($params);

        $stringToBeSigned = $this->getConfig()->get('client_secret');

        foreach ($params as $k => $v) {
            if (!is_array($v) && !str_starts_with($v, '@')) {
                $stringToBeSigned .= "$k$v";
            }
        }

        $stringToBeSigned .= $this->getConfig()->get('client_secret');

        return strtoupper(md5($stringToBeSigned));
    }

    protected function getPublicFields(string $token, array $apiFields = []): array
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

    protected function getUserInfoUrl(string $url, string $token): string
    {
        $apiFields = ['method' => 'taobao.miniapp.userInfo.get'];

        $query = http_build_query($this->getPublicFields($token, $apiFields), '', '&', $this->encodingType);

        return $url.'?'.$query;
    }
}
