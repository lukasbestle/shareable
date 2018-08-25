<?php

namespace LukasBestle\Shareable;

use Exception;
use Kirby\Toolkit\Properties;

/**
 * User
 *
 * @package   Shareable
 * @author    Lukas Bestle <project-shareable@lukasbestle.com>
 * @copyright Lukas Bestle
 * @license   MIT
 */
class User
{
    use Properties;

    // Properties
    protected $username;
    protected $password = null;
    protected $permissions = [];

    /**
     * Class constructor
     *
     * @param array $props Config properties; see the example config for more
     */
    public function __construct(array $props)
    {
        $this->setProperties($props);
    }

    /**
     * Returns the username
     *
     * @return string
     */
    public function username(): string
    {
        return $this->username;
    }

    /**
     * Checks if the given password is correct
     *
     * @param  string  $password
     * @return boolean
     */
    public function verifyPassword(string $password): bool
    {
        // if the user doesn't have a password, using it is not possible
        if (!is_string($this->password)) {
            return false;
        }

        return password_verify($password, $this->password);
    }

    /**
     * Checks if the user has the given permission
     *
     * @param  string|array $permission Either:
     *                                   - a permission name
     *                                   - an array of permissions
     *                                     (user must have *any*, not all permissions)
     *                                   - '*' for "any permission whatsoever"
     * @return boolean
     */
    public function hasPermission($permission): bool
    {
        if (is_array($permission)) {
            // multiple permissions are requested; any one of those must be set
            foreach ($permission as $p) {
                if ($this->hasPermission($p)) {
                    return true;
                }
            }

            return false;
        } elseif (is_string($permission)) {
            if (is_array($this->permissions)) {
                if ($permission === '*') {
                    // any permission is requested; any one must be set
                    return !empty($this->permissions);
                } else {
                    // a specific permission is requested; it must be set
                    return in_array($permission, $this->permissions);
                }
            } else {
                // defined permissions are a wildcard boolean, return that
                return $this->permissions;
            }
        } else {
            throw new Exception('Invalid param $permission, expected string or array');
        }
    }

    /**
     * Sets the username
     *
     * @param string $username Username
     */
    protected function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Sets the password hash
     *
     * @param string $password Password hash (created with password_hash())
     */
    protected function setPassword(string $password = null): self
    {
        if (is_string($password)) {
            // verify that $password is a valid password hash
            $info = password_get_info($password);
            if ($info['algoName'] === 'unknown') {
                throw new Exception(sprintf('Invalid password hash for user "%s", expected one created with password_hash()', $this->username));
            }
        }

        $this->password = $password;
        return $this;
    }

    /**
     * Sets the permissions
     *
     * @param array|bool $permissions Either:
     *                                 - Array of permissions
     *                                 - `false` for "no permissions"
     *                                 - `true` for "all permissions"
     */
    protected function setPermissions($permissions = []): self
    {
        if (!is_array($permissions) && !is_bool($permissions)) {
            throw new Exception(sprintf('Invalid permissions for user "%s", expected array or boolean', $this->username));
        }

        $this->permissions = $permissions;
        return $this;
    }
}
