<?php

namespace Overtrue\Socialite\Providers;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Overtrue\Socialite\Contracts\UserInterface;
use Overtrue\Socialite\User;

/**
 * @see https://developer.baidu.com/wiki/index.php?title=docs/oauth [OAuth 2.0 授权机制说明]
 */
class Baidu extends Base
{
    public const NAME = 'baidu';
    protected string $baseUrl = 'https://openapi.baidu.com';
    protected string $version = '2.0';
    protected array $scopes = ['basic'];
    protected string $display = 'popup';

    public function withDisplay(string $display): self
    {
        $this->display = $display;

        return $this;
    }

    public function withScopes(array $scopes): self
    {
        $this->scopes = $scopes;

        return $this;
    }

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl . '/oauth/' . $this->version . '/authorize');
    }

    protected function getCodeFields(): array
    {
        return [
                'response_type' => 'code',
                'client_id' => $this->getClientId(),
                'redirect_uri' => $this->redirectUrl,
                'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
                'display' => $this->display,
            ] + $this->parameters;
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl . '/oauth/' . $this->version . '/token';
    }

    #[ArrayShape([
        'client_id' => "\null|string",
        'client_secret' => "\null|string",
        'code' => "string",
        'redirect_uri' => "mixed"
    ])]
    protected function getTokenFields(string $code): array
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUserByToken(string $token): array
    {
        $response = $this->getHttpClient()->get(
            $this->baseUrl . '/rest/' . $this->version . '/passport/users/getInfo',
            [
                'query' => [
                    'access_token' => $token,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]
        );

        return json_decode($response->getBody(), true) ?? [];
    }

    #[Pure]
    protected function mapUserToObject(array $user): UserInterface
    {
        return new User(
            [
                'id' => $user['userid'] ?? null,
                'nickname' => $user['realname'] ?? null,
                'name' => $user['username'] ?? null,
                'email' => '',
                'avatar' => $user['portrait'] ? 'http://tb.himg.baidu.com/sys/portraitn/item/' . $user['portrait'] : null,
            ]
        );
    }
}
