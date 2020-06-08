<?php

namespace Overtrue\Socialite\Providers;

use GuzzleHttp\Client;
use Overtrue\Socialite\Config;
use Overtrue\Socialite\Contracts\ProviderInterface;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Exceptions\MethodDoesNotSupportException;
use Overtrue\Socialite\User;

abstract class AbstractProvider implements ProviderInterface
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var string
     */
    protected $state;

    /**
     * @var string
     */
    protected $redirectUrl;

    /**
     * @var array
     */
    protected $parameters = [];

    /**
     * @var array
     */
    protected $scopes = [];

    /**
     * @var string
     */
    protected $scopeSeparator = ',';

    /**
     * @var int Can be either PHP_QUERY_RFC3986 or PHP_QUERY_RFC1738
     */
    protected $encodingType = PHP_QUERY_RFC3986;

    /**
     * @var string
     */
    protected $accessTokenKey = 'access_token';

    /**
     * @var string
     */
    protected $refreshTokenKey = 'refresh_token';

    /**
     * @var string
     */
    protected $expiresInKey = 'expires_in';

    /**
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * @var array
     */
    protected $guzzleOptions = [];

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = new Config($config);
        $this->scopes = $config['scopes'] ?? [];
        $this->redirectUrl = $this->config->get('redirect_url') ?? $this->config->get('redirect');
    }

    /**
     * @return string
     */
    abstract protected function getAuthUrl(): string;

    /**
     * @return string
     */
    abstract protected function getTokenUrl(): string;

    /**
     * @param string $token
     *
     * @return array
     */
    abstract protected function getUserByToken(string $token): array;

    /**
     * @param array $user
     *
     * @return \Overtrue\Socialite\User
     */
    abstract protected function mapUserToObject(array $user): User;

    /**
     * @param string $redirectUrl
     *
     * @return string
     */
    public function redirect(string $redirectUrl = ''): string
    {
        if (!empty($redirectUrl)) {
            $this->withRedirectUrl($redirectUrl);
        }

        return $this->getAuthUrl();
    }

    /**
     * @param string $code
     *
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     *
     * @return \Overtrue\Socialite\User
     */
    public function userFromCode(string $code): User
    {
        $tokenResponse = $this->tokenFromCode($code);
        $user = $this->userFromToken($tokenResponse[$this->accessTokenKey]);

        return $user->setRefreshToken($tokenResponse[$this->refreshTokenKey] ?? null)
            ->setExpiresIn($tokenResponse[$this->expiresInKey] ?? null)
            ->setTokenResponse($tokenResponse);
    }

    /**
     * @param string $token
     *
     * @return \Overtrue\Socialite\User
     */
    public function userFromToken(string $token): User
    {
        $user = $this->getUserByToken($token);

        return $this->mapUserToObject($user)->setRaw($user)->setAccessToken($token);
    }

    /**
     * @param string $code
     *
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     *
     * @return array
     */
    public function tokenFromCode(string $code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'json' => $this->getTokenFields($code),
        ]);

        return $this->normalizeAccessTokenResponse($response->getBody()->getContents());
    }

    /**
     * @param string $refreshToken
     *
     * @throws \Overtrue\Socialite\Exceptions\MethodDoesNotSupportException
     */
    public function refreshToken(string $refreshToken)
    {
        throw new MethodDoesNotSupportException('refreshToken does not support.');
    }

    /**
     * @param $redirectUrl
     *
     * @return $this|\Overtrue\Socialite\Contracts\ProviderInterface
     */
    public function withRedirectUrl(string $redirectUrl): ProviderInterface
    {
        $this->redirectUrl = $redirectUrl;

        return $this;
    }

    /**
     * @param string $state
     *
     * @return \Overtrue\Socialite\Contracts\ProviderInterface
     */
    public function withState(string $state): ProviderInterface
    {
        $this->state = $state;

        return $this;
    }

    /**
     * Set the scopes of the requested access.
     *
     * @param array $scopes
     *
     * @return $this
     */
    public function scopes(array $scopes): self
    {
        $this->scopes = $scopes;

        return $this;
    }

    /**
     * Set the custom parameters of the request.
     *
     * @param array $parameters
     *
     * @return $this
     */
    public function with(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * @return \Overtrue\Socialite\Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * @param string $scopeSeparator
     *
     * @return self
     */
    public function withScopeSeparator(string $scopeSeparator): self
    {
        $this->scopeSeparator = $scopeSeparator;

        return $this;
    }

    /**
     * @param string $url
     *
     * @return string
     */
    protected function buildAuthUrlFromBase(string $url): string
    {
        $query = $this->getCodeFields() + ($this->state ? ['state' => $this->state] : []);

        return $url.'?'.\http_build_query($query, '', '&', $this->encodingType);
    }

    /**
     * @return array
     */
    protected function getCodeFields(): array
    {
        $fields = array_merge([
            'client_id' => $this->getClientId(),
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'response_type' => 'code',
        ], $this->parameters);

        if ($this->state) {
            $fields['state'] = $this->state;
        }

        return $fields;
    }

    public function getClientId(): ?string
    {
        return $this->config->get('client_id');
    }

    /**
     * @return array|mixed|null
     */
    protected function getClientSecret(): ?string
    {
        return $this->config->get('client_secret');
    }

    /**
     * Get a fresh instance of the Guzzle HTTP client.
     *
     * @return \GuzzleHttp\Client
     */
    public function getHttpClient(): Client
    {
        return $this->httpClient ?? new Client($this->guzzleOptions);
    }

    /**
     * @param array $config
     *
     * @return \Overtrue\Socialite\Contracts\ProviderInterface
     */
    public function setGuzzleOptions($config = []): self
    {
        $this->guzzleOptions = $config;

        return $this;
    }

    /**
     * @return array
     */
    public function getGuzzleOptions(): array
    {
        return $this->guzzleOptions;
    }

    /**
     * @param array  $scopes
     * @param string $scopeSeparator
     *
     * @return string
     */
    protected function formatScopes(array $scopes, $scopeSeparator): string
    {
        return implode($scopeSeparator, $scopes);
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields(string $code): array
    {
        return [
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'code' => $code,
            'redirect_uri' => $this->redirectUrl,
        ];
    }

    /**
     * @param array|string $response
     *
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     *
     * @return array
     */
    protected function normalizeAccessTokenResponse($response): array
    {
        if (\is_string($response)) {
            $response = json_decode($response, true) ?? [];
        }

        if (!\is_array($response)) {
            throw new AuthorizeFailedException('Invalid token response', $response);
        }

        if (empty($response[$this->accessTokenKey])) {
            throw new AuthorizeFailedException('Authorize Failed: '.json_encode($response, JSON_UNESCAPED_UNICODE), $response);
        }

        return $response + [
            'access_token' => $response[$this->accessTokenKey],
            'refresh_token' => $response[$this->refreshTokenKey] ?? null,
            'expires_in' => \intval($response[$this->expiresInKey] ?? 0),
        ];
    }
}
