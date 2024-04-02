<?php

namespace Aurora\Authentication\Drivers;

use Aurora\Config;
use Aurora\Hash;

class Eloquent extends Driver
{
    /**
     * Get the current user of the application.
     *
     * If the user is a guest, null should be returned.
     *
     * @return mixed|null
     */
    public function retrieve(int $id)
    {
        // We return an object here either if the passed token is an integer (ID)
        // or if we are passed a model object of the correct type
        if (false !== filter_var($id, \FILTER_VALIDATE_INT)) {
            return $this->model()->find($id);
        }

        if (\is_object($id) && $id::class === Config::get('auth.model')) {
            return $id;
        }
    }

    /**
     * Attempt to log a user into the application.
     */
    public function attempt(array $arguments = []): bool
    {
        $user = $this->model()->where(static function ($query) use ($arguments): void {
            $username = Config::get('auth.username');

            $query->where($username, $arguments['username']);

            foreach (array_except($arguments, ['username', 'password', 'remember']) as $column => $val) {
                $query->where($column, $val);
            }
        })->first();

        // If the credentials match what is in the database we will just
        // log the user into the application and remember them if asked.
        $password = $arguments['password'];

        $password_field = Config::get('auth.password', 'password');

        if (null !== $user && Hash::check($password, $user->{$password_field})) {
            return $this->login($user->get_key(), array_get($arguments, 'remember'));
        }

        return false;
    }

    /**
     * Get a fresh model instance.
     */
    protected function model(): self
    {
        $model = Config::get('auth.model');

        return new $model();
    }
}
