<?php

namespace Overtrue\Socialite\Providers;

use GuzzleHttp\Client;
use Overtrue\Socialite\Config;
use Overtrue\Socialite\Contracts\ProviderInterface;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
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
    protected $encodingType = PHP_QUERY_RFC1738;

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
        $this->redirectUrl = $this->config->get('redirect_url');
    }

    /**
     * @param string $state
     *
     * @return string
     */
    abstract protected function getAuthUrl();

    /**
     * @return string
     */
    abstract protected function getTokenUrl(): string;

    /**
     * @param string     $token
     * @param array|null $query
     *
     * @return array
     */
    abstract protected function getUserByToken(string $token, ?array $query = []): array;

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
    public function redirect(string $redirectUrl = null): string
    {
        if (!is_null($redirectUrl)) {
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
        return $this->userFromToken($this->tokenFromCode($code));
    }

    /**
     * @param string     $token
     * @param array|null $query
     *
     * @return \Overtrue\Socialite\User
     */
    public function userFromToken(string $token, ?array $query = []): \Overtrue\Socialite\User
    {
        $user = $this->getUserByToken($token, $query);

        return $this->mapUserToObject($user)->setRaw($user)->setToken($token);
    }

    /**
     * @param string $code
     *
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     *
     * @return string
     */
    public function tokenFromCode(string $code): string
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'json' => $this->getTokenFields($code),
        ]);

        return $this->parseAccessToken($response->getBody()->getContents());
    }

    /**
     * @param $redirectUrl
     *
     * @return $this|\Overtrue\Socialite\Contracts\ProviderInterface
     */
    public function withRedirectUrl($redirectUrl): ProviderInterface
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
    public function scopes(array $scopes)
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
    public function with(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * @return \Overtrue\Socialite\Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param string $url
     *
     * @return string
     */
    protected function buildAuthUrlFromBase(string $url)
    {
        $query = $this->getCodeFields() + ($this->state ? ['state' => $this->state] : []);

        return $url.'?'.http_build_query($query, '', '&', $this->encodingType);
    }

    /**
     * Get the GET parameters for the code request.
     *
     * @return array
     */
    protected function getCodeFields()
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

    public function getClientId()
    {
        return $this->config->get('client_id');
    }

    /**
     * Format the given scopes.
     *
     * @param array  $scopes
     * @param string $scopeSeparator
     *
     * @return string
     */
    protected function formatScopes(array $scopes, $scopeSeparator)
    {
        return implode($scopeSeparator, $scopes);
    }

    /**
     * Determine if the current request / session has a mismatching "state".
     *
     * @return bool
     */
    protected function hasInvalidState()
    {
        if ($this->isStateless()) {
            return false;
        }

        $state = $this->request->getSession()->get('state');

        return !(strlen($state) > 0 && $this->request->get('state') === $state);
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields(string $code)
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
     * @return mixed
     */
    protected function parseAccessToken($response)
    {
        if (\is_string($response)) {
            $response = json_decode($response, true);
        }

        $token = (array) $response[$this->accessTokenKey] ?? null;

        if (empty($token)) {
            throw new AuthorizeFailedException('Authorize Failed: '.json_encode($response, JSON_UNESCAPED_UNICODE), $response);
        }

        return $token;
    }

    /**
     * Get a fresh instance of the Guzzle HTTP client.
     *
     * @return \GuzzleHttp\Client
     */
    protected function getHttpClient()
    {
        return $this->httpClient ?? new Client($this->guzzleOptions);
    }

    /**
     * @return array|mixed|null
     */
    protected function getClientSecret()
    {
        return $this->config->get('client_secret');
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

    /**
     * @return array
     */
    public function getGuzzleOptions(): array
    {
        return $this->guzzleOptions;
    }
}
