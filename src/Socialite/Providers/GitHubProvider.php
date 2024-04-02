<?php

namespace Aurora\Socialite\Providers;

use Aurora\Arr;
use Aurora\Socialite\AccessTokenInterface;
use Aurora\Socialite\User;

class GitHubProvider extends AbstractProvider
{
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['user:email'];

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://github.com/login/oauth/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return 'https://github.com/login/oauth/access_token';
    }

    protected function getUserByToken(AccessTokenInterface $token): array
    {
        $userUrl = 'https://api.github.com/user';

        $response = $this->getHttpClient()->get(
            $userUrl,
            $this->createAuthorizationHeaders($token)
        );

        $user = json_decode($response->getBody(), true);

        if (\in_array('user:email', $this->scopes, true)) {
            $user['email'] = $this->getEmailByToken($token);
        }

        return $user;
    }

    /**
     * Get the default options for an HTTP request.
     */
    protected function createAuthorizationHeaders(string $token): array
    {
        return [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'Authorization' => sprintf('token %s', $token),
            ],
        ];
    }

    /**
     * Get the email for the given access token.
     *
     * @param string $token
     */
    protected function getEmailByToken($token): ?string
    {
        $emailsUrl = 'https://api.github.com/user/emails';

        try {
            $response = $this->getHttpClient()->get(
                $emailsUrl,
                $this->createAuthorizationHeaders($token)
            );
        } catch (\Exception $e) {
            return null;
        }

        foreach (json_decode($response->getBody(), true) as $email) {
            if ($email['primary'] && $email['verified']) {
                return $email['email'];
            }
        }
    }

    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => Arr::get($user, 'id'),
            'username' => Arr::get($user, 'login'),
            'nickname' => Arr::get($user, 'login'),
            'name' => Arr::get($user, 'name'),
            'email' => Arr::get($user, 'email'),
            'avatar' => Arr::get($user, 'avatar_url'),
        ]);
    }
}
