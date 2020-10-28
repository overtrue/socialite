<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\User;

/**
 * @see https://developers.facebook.com/docs/graph-api [Facebook - Graph API]
 */
class Facebook extends Base
{
    public const NAME = 'facebook';
    protected string $graphUrl = 'https://graph.facebook.com';
    protected string $version = 'v3.3';
    protected array $fields = ['first_name', 'last_name', 'email', 'gender', 'verified'];
    protected array $scopes = ['email'];
    protected bool $popup = false;

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase('https://www.facebook.com/' . $this->version . '/dialog/oauth');
    }

    protected function getTokenUrl(): string
    {
        return $this->graphUrl . '/oauth/access_token';
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
        $response = $this->getHttpClient()->get(
            $this->getTokenUrl(),
            [
                'query' => $this->getTokenFields($code),
            ]
        );

        return $this->normalizeAccessTokenResponse($response->getBody());
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
        $appSecretProof = hash_hmac('sha256', $token, $this->getConfig()->get('client_secret'));
        $endpoint = $this->graphUrl . '/' . $this->version . '/me?access_token=' . $token . '&appsecret_proof=' . $appSecretProof . '&fields=' .
            implode(',', $this->fields);

        $response = $this->getHttpClient()->get(
            $endpoint,
            [
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]
        );

        return \json_decode($response->getBody(), true) ?? [];
    }

    protected function mapUserToObject(array $user): User
    {
        $userId = $user['id'] ?? null;
        $avatarUrl = $this->graphUrl . '/' . $this->version . '/' . $userId . '/picture';

        $firstName = $user['first_name'] ?? null;
        $lastName = $user['last_name'] ?? null;

        return new User(
            [
                'id' => $user['id'] ?? null,
                'nickname' => null,
                'name' => $firstName . ' ' . $lastName,
                'email' => $user['email'] ?? null,
                'avatar' => $userId ? $avatarUrl . '?type=normal' : null,
                'avatar_original' => $userId ? $avatarUrl . '?width=1920' : null,
            ]
        );
    }

    protected function getCodeFields(): array
    {
        $fields = parent::getCodeFields();

        if ($this->popup) {
            $fields['display'] = 'popup';
        }

        return $fields;
    }

    /**
     * @param array $fields
     *
     * @return $this
     */
    public function fields(array $fields)
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @return $this
     */
    public function asPopup()
    {
        $this->popup = true;

        return $this;
    }
}
