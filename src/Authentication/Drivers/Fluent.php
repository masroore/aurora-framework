<?php

namespace Aurora\Authentication\Drivers;

use Aurora\Config;
use Aurora\Database as DB;
use Aurora\Hash;

class Fluent extends Driver
{
    /**
     * Get the current user of the application.
     *
     * If the user is a guest, null should be returned.
     *
     * @return mixed|null
     */
    public function retrieve(int $id): mixed
    {
        if (false !== filter_var($id, \FILTER_VALIDATE_INT)) {
            return DB::table(Config::get('auth.table'))->find($id);
        }
    }

    /**
     * Attempt to log a user into the application.
     */
    public function attempt(array $arguments = []): bool
    {
        $user = $this->getUser($arguments);

        // If the credentials match what is in the database we will just
        // log the user into the application and remember them if asked.
        $password = $arguments['password'];

        $password_field = Config::get('auth.password', 'password');

        if (null !== $user && Hash::check($password, $user->{$password_field})) {
            return $this->login($user->id, array_get($arguments, 'remember'));
        }

        return false;
    }

    /**
     * Get the user from the database table.
     */
    protected function getUser(array $arguments): mixed
    {
        $table = Config::get('auth.table');

        return DB::table($table)->where(static function ($query) use ($arguments): void {
            $username = Config::get('auth.username');

            $query->where($username, $arguments['username']);

            foreach (array_except($arguments, ['username', 'password', 'remember']) as $column => $val) {
                $query->where($column, $val);
            }
        })->first();
    }
}
