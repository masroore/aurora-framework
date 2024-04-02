<?php

namespace Aurora\Socialite\Providers;

use Aurora\Arr;
use Aurora\Socialite\AccessToken;
use Aurora\Socialite\AccessTokenInterface;
use Aurora\Socialite\User;

/**
 * Class LinkedinProvider.
 *
 * @see https://developer.linkedin.com/docs/oauth2 [Authenticating with OAuth 2.0]
 */
class LinkedinProvider extends AbstractProvider
{
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['r_liteprofile', 'r_emailaddress'];

    /**
     * Get the access token for the given code.
     *
     * @param string $code
     *
     * @return AccessToken
     */
    public function getAccessToken($code)
    {
        $response = $this->getHttpClient()
            ->post($this->getTokenUrl(), ['form_params' => $this->getTokenFields($code)]);

        return $this->parseAccessToken($response->getBody());
    }

    /**
     * Set the user fields to request from LinkedIn.
     *
     * @return $this
     */
    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    protected function getTokenUrl(): string
    {
        return 'https://www.linkedin.com/oauth/v2/accessToken';
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
        return $this->buildAuthUrlFromBase('https://www.linkedin.com/oauth/v2/authorization', $state);
    }

    protected function getUserByToken(AccessTokenInterface $token): array
    {
        $basicProfile = $this->getBasicProfile($token);
        $emailAddress = $this->getEmailAddress($token);

        return array_merge($basicProfile, $emailAddress);
    }

    /**
     * Get the basic profile fields for the user.
     *
     * @param string $token
     */
    protected function getBasicProfile($token): array
    {
        $url = 'https://api.linkedin.com/v2/me?projection=(id,firstName,lastName,profilePicture(displayImage~:playableStreams))';

        $response = $this->getHttpClient()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'X-RestLi-Protocol-Version' => '2.0.0',
            ],
        ]);

        return (array)json_decode($response->getBody(), true);
    }

    /**
     * Get the email address for the user.
     *
     * @param string $token
     */
    protected function getEmailAddress($token): array
    {
        $url = 'https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))';

        $response = $this->getHttpClient()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'X-RestLi-Protocol-Version' => '2.0.0',
            ],
        ]);

        return (array)Arr::get(json_decode($response->getBody(), true), 'elements.0.handle~');
    }

    protected function mapUserToObject(array $user): User
    {
        $preferredLocale = Arr::get($user, 'firstName.preferredLocale.language') . '_' . Arr::get($user, 'firstName.preferredLocale.country');
        $firstName = Arr::get($user, 'firstName.localized.' . $preferredLocale);
        $lastName = Arr::get($user, 'lastName.localized.' . $preferredLocale);
        $name = $firstName . ' ' . $lastName;

        $images = (array)Arr::get($user, 'profilePicture.displayImage~.elements', []);
        $avatars = array_filter($images, static fn ($image) => 100 === $image['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize']['width']);
        $avatar = array_shift($avatars);
        $originalAvatars = array_filter($images, static fn ($image) => 800 === $image['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize']['width']);
        $originalAvatar = array_shift($originalAvatars);

        return new User([
            'id' => Arr::get($user, 'id'),
            'nickname' => $name,
            'name' => $name,
            'email' => Arr::get($user, 'emailAddress'),
            'avatar' => $avatar ? Arr::get($avatar, 'identifiers.0.identifier') : null,
            'avatar_original' => $originalAvatar ? Arr::get($originalAvatar, 'identifiers.0.identifier') : null,
        ]);
    }

    /**
     * Determine if the provider is operating as stateless.
     */
    protected function isStateless(): bool
    {
        return true;
    }
}
