<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\Contracts;
use Overtrue\Socialite\Exceptions;
use Overtrue\Socialite\User;

/**
 * @see https://developer.apple.com/documentation/sign_in_with_apple/sign_in_with_apple_rest_api
 */
class Apple extends Base
{
    public const NAME = 'apple';

    protected string $scopeSeparator = ' ';

    protected array $scopes = ['name', 'email'];

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase('https://appleid.apple.com/auth/authorize');
    }

    protected function getCodeFields(): array
    {
        return \array_merge(parent::getCodeFields(), [
            'response_mode' => 'form_post',
        ]);
    }

    protected function getTokenUrl(): string
    {
        return 'https://appleid.apple.com/auth/token';
    }

    public function getClientSecret(): ?string
    {
        if ($secret = $this->config->get(Contracts\RFC6749_ABNF_CLIENT_SECRET)) {
            return $secret;
        }

        return $this->generateClientSecret();
    }

    protected function generateClientSecret(): string
    {
        $teamId = $this->config->get('team_id');
        $keyId = $this->config->get('key_id');
        $privateKey = $this->config->get('private_key');

        $header = $this->base64UrlEncode(\json_encode(['kid' => $keyId, 'alg' => 'ES256']));
        $now = \time();
        $payload = $this->base64UrlEncode(\json_encode([
            'iss' => $teamId,
            'iat' => $now,
            'exp' => $now + 86400 * 180,
            'aud' => 'https://appleid.apple.com',
            'sub' => $this->getClientId(),
        ]));

        $data = $header.'.'.$payload;
        $key = \openssl_pkey_get_private($privateKey);
        \openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);

        return $data.'.'.$this->base64UrlEncode($signature);
    }

    public function tokenFromCode(string $code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'form_params' => $this->getTokenFields($code),
        ]);

        return $this->normalizeAccessTokenResponse($response->getBody());
    }

    protected function getTokenFields(string $code): array
    {
        return [
            Contracts\RFC6749_ABNF_CLIENT_ID => $this->getClientId(),
            Contracts\RFC6749_ABNF_CLIENT_SECRET => $this->getClientSecret(),
            Contracts\RFC6749_ABNF_CODE => $code,
            Contracts\RFC6749_ABNF_REDIRECT_URI => $this->redirectUrl,
            Contracts\RFC6749_ABNF_GRANT_TYPE => Contracts\RFC6749_ABNF_AUTHORATION_CODE,
        ];
    }

    public function userFromCode(string $code): Contracts\UserInterface
    {
        $tokenResponse = $this->tokenFromCode($code);
        $idToken = $tokenResponse['id_token'] ?? null;

        if (empty($idToken)) {
            throw new Exceptions\AuthorizeFailedException('Missing id_token in token response', $tokenResponse);
        }

        $user = $this->getUserByToken($idToken);

        return $this->mapUserToObject($user)
            ->setProvider($this)
            ->setRaw($user)
            ->setAccessToken($tokenResponse[Contracts\RFC6749_ABNF_ACCESS_TOKEN] ?? '')
            ->setRefreshToken($tokenResponse[Contracts\RFC6749_ABNF_REFRESH_TOKEN] ?? null)
            ->setExpiresIn($tokenResponse[Contracts\RFC6749_ABNF_EXPIRES_IN] ?? null)
            ->setTokenResponse($tokenResponse);
    }

    /**
     * Decode the id_token (JWT) payload to get user claims.
     * For Apple Sign In, the token parameter should be the id_token.
     */
    protected function getUserByToken(string $token): array
    {
        $parts = \explode('.', $token);

        if (\count($parts) !== 3) {
            throw new Exceptions\InvalidArgumentException('Invalid id_token format.');
        }

        $payload = \base64_decode(\strtr($parts[1], '-_', '+/'));
        $claims = \json_decode($payload, true);

        if (! \is_array($claims)) {
            throw new Exceptions\InvalidArgumentException('Failed to decode id_token payload.');
        }

        return $claims;
    }

    protected function mapUserToObject(array $user): Contracts\UserInterface
    {
        return new User([
            Contracts\ABNF_ID => $user['sub'] ?? null,
            Contracts\ABNF_NAME => $user[Contracts\ABNF_NAME] ?? null,
            Contracts\ABNF_NICKNAME => null,
            Contracts\ABNF_EMAIL => $user[Contracts\ABNF_EMAIL] ?? null,
            Contracts\ABNF_AVATAR => null,
        ]);
    }

    private function base64UrlEncode(string $data): string
    {
        return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
    }
}
