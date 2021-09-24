<?php

namespace Overtrue\Socialite\Providers;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Stream;
use Overtrue\Socialite\Config;
use Overtrue\Socialite\Contracts\ProviderInterface;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Exceptions\MethodDoesNotSupportException;
use Overtrue\Socialite\User;

abstract class Base implements ProviderInterface
{
    public const NAME = null;

    protected ?string      $state = null;
    protected Config       $config;
    protected ?string      $redirectUrl;
    protected array        $parameters = [];
    protected array        $scopes = [];
    protected string       $scopeSeparator = ',';
    protected GuzzleClient $httpClient;
    protected array        $guzzleOptions = [];
    protected int          $encodingType = PHP_QUERY_RFC1738;
    protected string       $expiresInKey = 'expires_in';
    protected string       $accessTokenKey = 'access_token';
    protected string       $refreshTokenKey = 'refresh_token';

    public function __construct(array $config)
    {
        $this->config = new Config($config);

        // set scopes
        if ($this->config->has('scopes') && is_array($this->config->get('scopes'))) {
            $this->scopes = $this->getConfig()->get('scopes');
        } elseif ($this->config->has('scope') && is_string($this->getConfig()->get('scope'))) {
            $this->scopes = array($this->getConfig()->get('scope'));
        }

        // normalize 'client_id'
        if (!$this->config->has('client_id')) {
            $id = $this->config->get('app_id');
            if (null != $id) {
                $this->config->set('client_id', $id);
            }
        }

        // normalize 'client_secret'
        if (!$this->config->has('client_secret')) {
            $secret = $this->config->get('app_secret');
            if (null != $secret) {
                $this->config->set('client_secret', $secret);
            }
        }

        // normalize 'redirect_url'
        if (!$this->config->has('redirect_url')) {
            $this->config->set('redirect_url', $this->config->get('redirect'));
        }
        $this->redirectUrl = $this->config->get('redirect_url');
    }

    abstract protected function getAuthUrl(): string;

    abstract protected function getTokenUrl(): string;

    abstract protected function getUserByToken(string $token): array;

    abstract protected function mapUserToObject(array $user): User;

    /**
     * @param string|null $redirectUrl
     *
     * @return string
     */
    public function redirect(?string $redirectUrl = null): string
    {
        if (!empty($redirectUrl)) {
            $this->withRedirectUrl($redirectUrl);
        }

        return $this->getAuthUrl();
    }

    /**
     * @param string $code
     *
     * @return \Overtrue\Socialite\User
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
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

        return $this->mapUserToObject($user)->setProvider($this)->setRaw($user)->setAccessToken($token);
    }

    /**
     * @param string $code
     *
     * @return array
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException|\GuzzleHttp\Exception\GuzzleException
     */
    public function tokenFromCode(string $code): array
    {
        $response = $this->getHttpClient()->post(
            $this->getTokenUrl(),
            [
                'form_params' => $this->getTokenFields($code),
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]
        );

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
     * @param string $redirectUrl
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
     * @param array $parameters
     *
     * @return $this
     */
    public function with(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

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

    public function getClientId(): ?string
    {
        return $this->config->get('client_id');
    }

    public function getClientSecret(): ?string
    {
        return $this->config->get('client_secret');
    }

    public function getHttpClient(): GuzzleClient
    {
        return $this->httpClient ?? new GuzzleClient($this->guzzleOptions);
    }

    /**
     * @param array $config
     *
     * @return \Overtrue\Socialite\Contracts\ProviderInterface
     */
    public function setGuzzleOptions($config = []): ProviderInterface
    {
        $this->guzzleOptions = $config;

        return $this;
    }

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
     * @param string $url
     *
     * @return string
     */
    protected function buildAuthUrlFromBase(string $url): string
    {
        $query = $this->getCodeFields() + ($this->state ? ['state' => $this->state] : []);

        return $url . '?' . \http_build_query($query, '', '&', $this->encodingType);
    }

    protected function getCodeFields(): array
    {
        $fields = array_merge(
            [
                'client_id' => $this->getClientId(),
                'redirect_uri' => $this->redirectUrl,
                'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
                'response_type' => 'code',
            ],
            $this->parameters
        );

        if ($this->state) {
            $fields['state'] = $this->state;
        }

        return $fields;
    }

    /**
     * @param array|string $response
     *
     * @return mixed
     * @return array
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     *
     */
    protected function normalizeAccessTokenResponse($response): array
    {
        if ($response instanceof Stream) {
            $response->rewind();
            $response = $response->getContents();
        }

        if (\is_string($response)) {
            $response = json_decode($response, true) ?? [];
        }

        if (!\is_array($response)) {
            throw new AuthorizeFailedException('Invalid token response', [$response]);
        }

        if (empty($response[$this->accessTokenKey])) {
            throw new AuthorizeFailedException('Authorize Failed: ' . json_encode($response, JSON_UNESCAPED_UNICODE), $response);
        }

        return $response + [
                'access_token' => $response[$this->accessTokenKey],
                'refresh_token' => $response[$this->refreshTokenKey] ?? null,
                'expires_in' => \intval($response[$this->expiresInKey] ?? 0),
            ];
    }
}
