<?php

namespace Aurora\Socialite\Providers;

use Aurora\Request;
use Aurora\Session;
use Aurora\Socialite\AccessToken;
use Aurora\Socialite\AccessTokenInterface;
use Aurora\Socialite\AuthorizeFailedException;
use Aurora\Socialite\Config;
use Aurora\Socialite\InvalidStateException;
use Aurora\Socialite\ProviderInterface;
use Aurora\Socialite\User;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

abstract class AbstractProvider implements ProviderInterface
{
    protected const SESSION_KEY = '_state';

    /**
     * The options for guzzle\client.
     *
     * @var array
     */
    protected static $guzzleOptions = ['http_errors' => false];

    /**
     * Provider name.
     *
     * @var string
     */
    protected $name;

    /**
     * Driver config.
     *
     * @var Config
     */
    protected $config;

    /**
     * The client ID.
     *
     * @var string
     */
    protected $clientId;

    /**
     * The client secret.
     *
     * @var string
     */
    protected $clientSecret;

    /**
     * @var AccessTokenInterface
     */
    protected $accessToken;

    /**
     * The redirect URL.
     *
     * @var string
     */
    protected $redirectUrl;

    /**
     * The custom parameters to be sent with the request.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = [];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ',';

    /**
     * The type of the encoding in the query.
     *
     * @var int Can be either PHP_QUERY_RFC3986 or PHP_QUERY_RFC1738
     */
    protected $encodingType = \PHP_QUERY_RFC1738;

    /**
     * Indicates if the session state should be utilized.
     *
     * @var bool
     */
    protected $stateless = false;

    /**
     * Create a new provider instance.
     */
    public function __construct(array $config)
    {
        $this->config = new Config($config);
        $this->redirectUrl = $config['redirect'] ?? null;
    }

    /**
     * Set options for Guzzle HTTP client.
     */
    public static function setGuzzleOptions(array $config = []): array
    {
        return self::$guzzleOptions = $config;
    }

    /**
     * Redirect the user of the application to the provider's authentication screen.
     */
    public function redirect(?string $redirectUrl = null): RedirectResponse
    {
        $state = null;

        if (null !== $redirectUrl) {
            $this->redirectUrl = $redirectUrl;
        }

        if ($this->usesState()) {
            $state = $this->makeState();
        }

        return new RedirectResponse($this->getAuthUrl($state));
    }

    public function user(?AccessTokenInterface $token = null): User
    {
        if (null === $token && $this->hasInvalidState()) {
            throw new InvalidStateException();
        }

        $token = $token ?: $this->getAccessToken($this->getCode());

        $user = $this->getUserByToken($token);

        $user = $this->mapUserToObject($user)->merge(['original' => $user]);

        return $user->setToken($token)->setProviderName($this->getName());
    }

    /**
     * Get the access token for the given code.
     *
     * @param string $code
     *
     * @return AccessTokenInterface
     */
    public function getAccessToken($code)
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $postKey = (1 === version_compare(ClientInterface::MAJOR_VERSION, '6')) ? 'form_params' : 'body';

        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            $postKey => $this->getTokenFields($code),
        ]);

        return $this->parseAccessToken($response->getBody());
    }

    /**
     * @return $this
     */
    public function setAccessToken(AccessTokenInterface $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    public function getName(): string
    {
        if (empty($this->name)) {
            $this->name = mb_strstr((new \ReflectionClass(static::class))->getShortName(), 'Provider', true);
        }

        return $this->name;
    }

    /**
     * Set redirect url.
     *
     * @param string $redirectUrl
     *
     * @return $this
     */
    public function withRedirectUrl($redirectUrl): self
    {
        $this->redirectUrl = $redirectUrl;

        return $this;
    }

    /**
     * Return the redirect url.
     */
    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    /**
     * Set redirect url.
     *
     * @return $this
     */
    public function setRedirectUrl(string $redirectUrl): self
    {
        $this->redirectUrl = $redirectUrl;

        return $this;
    }

    /**
     * Set the scopes of the requested access.
     *
     * @return $this
     */
    public function scopes(array $scopes): self
    {
        $this->scopes = $scopes;

        return $this;
    }

    /**
     * Indicates that the provider should operate as stateless.
     *
     * @return $this
     */
    public function stateless(): self
    {
        $this->stateless = true;

        return $this;
    }

    /**
     * Set the custom parameters of the request.
     *
     * @return $this
     */
    public function with(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Determine if the provider is operating with state.
     */
    protected function usesState(): bool
    {
        return !$this->stateless;
    }

    /**
     * Put state to session storage and return it.
     *
     * @return bool|string
     */
    protected function makeState()
    {
        if (!Session::started()) {
            return false;
        }

        $state = sha1(uniqid(random_int(1, 1000000), true));

        Session::put(self::SESSION_KEY, $state);

        return $state;
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $state
     *
     * @return string
     */
    abstract protected function getAuthUrl($state);

    /**
     * Determine if the current request / session has a mismatching "state".
     */
    protected function hasInvalidState(): bool
    {
        if ($this->isStateless()) {
            return false;
        }

        $state = Session::get(self::SESSION_KEY);

        return blank($state) || Request::get(self::SESSION_KEY) !== $state;
    }

    /**
     * Determine if the provider is operating as stateless.
     */
    protected function isStateless(): bool
    {
        return !Session::started() || $this->stateless;
    }

    /**
     * Get a fresh instance of the Guzzle HTTP client.
     */
    protected function getHttpClient(): Client
    {
        return new Client(self::$guzzleOptions);
    }

    /**
     * Get the token URL for the provider.
     */
    abstract protected function getTokenUrl(): string;

    /**
     * Get the POST fields for the token request.
     */
    protected function getTokenFields(string $code): array
    {
        return [
            'client_id' => $this->getConfig()->get('client_id'),
            'client_secret' => $this->getConfig()->get('client_secret'),
            'code' => $code,
            'redirect_uri' => $this->redirectUrl,
        ];
    }

    /**
     * Get the access token from the token response body.
     *
     * @param array|StreamInterface $body
     *
     * @return AccessTokenInterface
     */
    protected function parseAccessToken($body)
    {
        if (!\is_array($body)) {
            $body = json_decode($body, true);
        }

        if (empty($body['access_token'])) {
            throw new AuthorizeFailedException('Authorize Failed: ' . json_encode($body, \JSON_UNESCAPED_UNICODE), $body);
        }

        return new AccessToken($body);
    }

    /**
     * Get the code from the request.
     */
    protected function getCode(): string
    {
        return Request::get('code');
    }

    /**
     * Get the raw user for the given access token.
     */
    abstract protected function getUserByToken(AccessTokenInterface $token): array;

    /**
     * Map the raw user array to a Socialite User instance.
     */
    abstract protected function mapUserToObject(array $user): User;

    /**
     * Get the authentication URL for the provider.
     */
    protected function buildAuthUrlFromBase(string $url, string $state): string
    {
        return $url . '?' . http_build_query($this->getCodeFields($state), '', '&', $this->encodingType);
    }

    /**
     * Get the GET parameters for the code request.
     *
     * @param ?string $state
     */
    protected function getCodeFields(?string $state = null): array
    {
        $fields = array_merge([
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'response_type' => 'code',
        ], $this->parameters);

        if ($this->usesState()) {
            $fields[self::SESSION_KEY] = $state;
        }

        return $fields;
    }

    /**
     * Format the given scopes.
     */
    protected function formatScopes(array $scopes, string $scopeSeparator): string
    {
        return implode($scopeSeparator, $scopes);
    }
}
