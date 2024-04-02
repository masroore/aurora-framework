<?php

namespace Aurora\Socialite\Providers;

use Aurora\Arr;
use Aurora\Socialite\AccessToken;
use Aurora\Socialite\AccessTokenInterface;
use Aurora\Socialite\User;

/**
 * Class FacebookProvider.
 *
 * @see https://developers.facebook.com/docs/graph-api [Facebook - Graph API]
 */
class FacebookProvider extends AbstractProvider
{
    /**
     * The base Facebook Graph URL.
     *
     * @var string
     */
    protected $graphUrl = 'https://graph.facebook.com';

    /**
     * The Graph API version for the request.
     *
     * @var string
     */
    protected $version = 'v3.3';

    /**
     * The user fields being requested.
     *
     * @var array
     */
    protected $fields = ['first_name', 'last_name', 'email', 'gender', 'verified'];

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['email'];

    /**
     * Display the dialog in a popup view.
     *
     * @var bool
     */
    protected $popup = false;

    /**
     * Get the access token for the given code.
     *
     * @param string $code
     *
     * @return AccessToken
     */
    public function getAccessToken($code)
    {
        $response = $this->getHttpClient()->get($this->getTokenUrl(), [
            'query' => $this->getTokenFields($code),
        ]);

        return $this->parseAccessToken($response->getBody());
    }

    /**
     * Set the user fields to request from Facebook.
     *
     * @return $this
     */
    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Set the dialog to be displayed as a popup.
     *
     * @return $this
     */
    public function asPopup(): self
    {
        $this->popup = true;

        return $this;
    }

    protected function getTokenUrl(): string
    {
        return $this->graphUrl . '/oauth/access_token';
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase('https://www.facebook.com/' . $this->version . '/dialog/oauth', $state);
    }

    protected function getUserByToken(AccessTokenInterface $token): array
    {
        $appSecretProof = hash_hmac('sha256', $token->getToken(), $this->getConfig()->get('client_secret'));

        $uri = sprintf(
            '%s/%s/me?access_token=%s&appsecret_proof=%s&fields=%s',
            $this->graphUrl,
            $this->version,
            $token,
            $appSecretProof,
            implode(',', $this->fields)
        );

        $response = $this->getHttpClient()->get($uri, [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        $userId = Arr::get($user, 'id');
        $avatarUrl = $this->graphUrl . '/' . $this->version . '/' . $userId . '/picture';

        $firstName = Arr::get($user, 'first_name');
        $lastName = Arr::get($user, 'last_name');

        return new User([
            'id' => Arr::get($user, 'id'),
            'nickname' => null,
            'name' => $firstName . ' ' . $lastName,
            'email' => Arr::get($user, 'email'),
            'avatar' => $userId ? $avatarUrl . '?type=normal' : null,
            'avatar_original' => $userId ? $avatarUrl . '?width=1920' : null,
        ]);
    }

    protected function getCodeFields(?string $state = null): array
    {
        $fields = parent::getCodeFields($state);

        if ($this->popup) {
            $fields['display'] = 'popup';
        }

        return $fields;
    }
}
