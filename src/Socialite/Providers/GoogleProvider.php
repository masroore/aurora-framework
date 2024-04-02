<?php

namespace Aurora\Socialite\Providers;

use Aurora\Arr;
use Aurora\Socialite\AccessTokenInterface;
use Aurora\Socialite\User;
use GuzzleHttp\ClientInterface;

/**
 * Class GoogleProvider.
 *
 * @see https://developers.google.com/identity/protocols/OpenIDConnect [OpenID Connect]
 */
class GoogleProvider extends AbstractProvider
{
    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = [
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
    ];

    /**
     * Get the access token for the given code.
     *
     * @param string $code
     *
     * @return string
     */
    public function getAccessToken($code)
    {
        $postKey = (1 === version_compare(ClientInterface::VERSION, '6')) ? 'form_params' : 'body';

        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            $postKey => $this->getTokenFields($code),
        ]);

        return $this->parseAccessToken($response->getBody());
    }

    protected function getTokenUrl(): string
    {
        return 'https://www.googleapis.com/oauth2/v4/token';
    }

    /**
     * Get the POST fields for the token request.
     */
    protected function getTokenFields(string $code): array
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase('https://accounts.google.com/o/oauth2/v2/auth', $state);
    }

    protected function getUserByToken(AccessTokenInterface $token): array
    {
        $response = $this->getHttpClient()->get('https://www.googleapis.com/userinfo/v2/me', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token->getToken(),
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => Arr::get($user, 'id'),
            'username' => Arr::get($user, 'email'),
            'nickname' => Arr::get($user, 'name'),
            'name' => Arr::get($user, 'name'),
            'email' => Arr::get($user, 'email'),
            'avatar' => Arr::get($user, 'picture'),
        ]);
    }
}
