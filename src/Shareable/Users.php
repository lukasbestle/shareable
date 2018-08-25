<?php

namespace LukasBestle\Shareable;

use Kirby\Toolkit\Collection;

/**
 * Users
 * User collection
 *
 * @package   Shareable
 * @author    Lukas Bestle <project-shareable@lukasbestle.com>
 * @copyright Lukas Bestle
 * @license   MIT
 */
class Users extends Collection
{
    // Cache
    protected $current;

    /**
     * Class constructor
     *
     * @param array $users List of user config arrays
     */
    public function __construct(array $users)
    {
        // make sure there is an anonymous user
        if (!isset($users['anonymous'])) {
            $users['anonymous'] = [
                'permissions' => false
            ];
        }

        $data = [];
        foreach ($users as $username => $props) {
            $props['username'] = $username;
            $data[$username] = new User($props);
        }

        parent::__construct($data);
    }

    /**
     * Returns the current user
     *
     * @return User
     */
    public function current(): User
    {
        // return from cache
        if ($this->current) {
            return $this->current;
        }

        if (isset($_SERVER['PHP_AUTH_USER'])) {
            // Basic auth is set
            $user = $this->get($_SERVER['PHP_AUTH_USER']);

            if ($user && $user->verifyPassword($_SERVER['PHP_AUTH_PW']) === true) {
                // user exists and password was correct
                return $this->current = $user;
            } else {
                // invalid user or password
                return $this->current = $this->get('anonymous');
            }
        } else {
            // no Basic auth is set
            return $this->current = $this->get('anonymous');
        }
    }
}
