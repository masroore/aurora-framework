<?php

namespace Aurora\Socialite\Providers;

use Aurora\Arr;
use Aurora\Socialite\AccessTokenInterface;
use Aurora\Socialite\User;

/**
 * Class OutlookProvider.
 */
class OutlookProvider extends AbstractProvider
{
    protected $scopes = ['User.Read'];

    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase('https://login.microsoftonline.com/common/oauth2/v2.0/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    }

    protected function getUserByToken(AccessTokenInterface $token): array
    {
        $response = $this->getHttpClient()->get(
            'https://graph.microsoft.com/v1.0/me',
            ['headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token->getToken(),
            ],
            ]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => Arr::get($user, 'id'),
            'nickname' => null,
            'name' => Arr::get($user, 'displayName'),
            'email' => Arr::get($user, 'userPrincipalName'),
            'avatar' => null,
        ]);
    }

    protected function getTokenFields(string $code): array
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }
}
