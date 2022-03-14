<?php

namespace Overtrue\Socialite\Providers;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Overtrue\Socialite\Contracts\UserInterface;
use Overtrue\Socialite\User;

/**
 * @see https://www.figma.com/developers/api#oauth2
 */
class Figma extends Base
{
    public const NAME = 'figma';
    protected string $scopeSeparator = '';
    protected array $scopes = ['file_read'];

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase('https://www.figma.com/oauth');
    }

    protected function getTokenUrl(): string
    {
        return 'https://www.figma.com/api/oauth/token';
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     */
    public function tokenFromCode(string $code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'form_params' => $this->getTokenFields($code),
        ]);

        return $this->normalizeAccessTokenResponse($response->getBody());
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

    protected function getCodeFields(): array
    {
        return parent::getCodeFields() + ['state' => \md5(\uniqid('state_', true))];
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUserByToken(string $token, ?array $query = []): array
    {
        $response = $this->getHttpClient()->get('https://api.figma.com/v1/me', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return \json_decode($response->getBody(), true) ?? [];
    }

    #[Pure]
    protected function mapUserToObject(array $user): UserInterface
    {
        return new User([
            'id' => $user['id'] ?? null,
            'username' => $user['email'] ?? null,
            'nickname' => $user['handle'] ?? null,
            'name' => $user['handle'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['img_url'] ?? null,
        ]);
    }
}
