<?php

namespace Aurora\Socialite;

use Closure;

class SocialiteManager implements FactoryInterface
{
    /**
     * The configuration.
     *
     * @var Config
     */
    protected $config;

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * The initial drivers.
     *
     * @var array
     */
    protected $initialDrivers = [
        'facebook' => 'Facebook',
        'github' => 'GitHub',
        'google' => 'Google',
        'linkedin' => 'Linkedin',
        'outlook' => 'Outlook',
    ];

    /**
     * The array of created "drivers".
     *
     * @var ProviderInterface[]
     */
    protected $drivers = [];

    /**
     * SocialiteManager constructor.
     */
    public function __construct(array $config)
    {
        $this->config = new Config($config);

        if ($this->config->has('guzzle')) {
            Providers\AbstractProvider::setGuzzleOptions($this->config->get('guzzle'));
        }
    }

    /**
     * Set config instance.
     *
     * @return $this
     */
    public function config(Config $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get a driver instance.
     */
    public function driver(string $driver): ProviderInterface
    {
        $driver = mb_strtolower($driver);

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Build an OAuth 2 provider instance.
     */
    public function buildProvider(string $provider, array $config): ProviderInterface
    {
        return new $provider($config);
    }

    /**
     * Format the server configuration.
     */
    public function formatConfig(array $config): array
    {
        return array_merge([
            'identifier' => $config['client_id'],
            'secret' => $config['client_secret'],
            'callback_uri' => $config['redirect'],
        ], $config);
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @return $this
     */
    public function extend(string $driver, \Closure $callback): self
    {
        $driver = mb_strtolower($driver);

        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Get all of the created "drivers".
     *
     * @return ProviderInterface[]
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * Create a new driver instance.
     */
    protected function createDriver(string $driver): ProviderInterface
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        if (isset($this->initialDrivers[$driver])) {
            $provider = $this->initialDrivers[$driver];
            $provider = __NAMESPACE__ . '\\Providers\\' . $provider . 'Provider';

            return $this->buildProvider($provider, $this->formatConfig($this->config->get($driver)));
        }

        throw new \InvalidArgumentException("Driver [$driver] not supported.");
    }

    /**
     * Call a custom driver creator.
     */
    protected function callCustomCreator(string $driver): ProviderInterface
    {
        return $this->customCreators[$driver]($this->config);
    }
}
