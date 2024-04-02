<?php

namespace Aurora\Session\Drivers;

use Aurora\Config;
use Aurora\Database\Connection;

class Database extends Driver implements Sweeper
{
    /**
     * The database connection.
     *
     * @var Connection
     */
    protected $connection;

    /**
     * Create a new database session driver.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Load a session from storage by a given ID.
     *
     * If no session is found for the ID, null will be returned.
     *
     * @param string $id
     *
     * @return array
     */
    public function load($id)
    {
        $session = $this->table()->find($id);

        if (null !== $session) {
            return [
                'id' => $session->id,
                'last_activity' => $session->last_activity,
                'data' => unserialize($session->data),
            ];
        }
    }

    /**
     * Save a given session to storage.
     *
     * @param array $session
     * @param array $config
     * @param bool  $exists
     */
    public function save($session, $config, $exists): void
    {
        if ($exists) {
            $this->table()->where('id', '=', $session['id'])->update([
                'last_activity' => $session['last_activity'],
                'data' => serialize($session['data']),
            ]);
        } else {
            $this->table()->insert([
                'id' => $session['id'],
                'last_activity' => $session['last_activity'],
                'data' => serialize($session['data']),
            ]);
        }
    }

    /**
     * Delete a session from storage by a given ID.
     *
     * @param string $id
     */
    public function delete($id): void
    {
        $this->table()->delete($id);
    }

    /**
     * Delete all expired sessions from persistent storage.
     *
     * @param int $expiration
     */
    public function sweep($expiration): void
    {
        $this->table()->where('last_activity', '<', $expiration)->delete();
    }

    /**
     * Get a session database query.
     *
     * @return Query
     */
    private function table()
    {
        return $this->connection->table(Config::get('session.table'));
    }
}
