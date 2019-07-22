<?php

namespace LukasBestle\Shareable;

use Exception;
use Kirby\Http\Response;
use Kirby\Toolkit\F;
use Kirby\Toolkit\Properties;

/**
 * Item
 * A single item
 *
 * @package   Shareable
 * @author    Lukas Bestle <project-shareable@lukasbestle.com>
 * @copyright Lukas Bestle
 * @license   MIT
 */
class Item
{
    use Properties;

    // Constructor data
    protected $app;
    protected $path;

    // Properties
    protected $activity = null;
    protected $created;
    protected $downloads = 0;
    protected $expires;
    protected $filename;
    protected $timeout;
    protected $user;

    // Temporary state flags
    protected $deleted = false;

    /**
     * Class constructor
     *
     * @param App    $app  App instance
     * @param string $path Absolute path to the item file
     * @param array  $data Data for newly created items
     */
    public function __construct(App $app, string $path, array $data = null)
    {
        $this->app  = $app;
        $this->path = $path;

        // verify structure of the item name
        $itemName = F::name($path);
        if (static::isValidId($itemName) !== true) {
            throw new Exception(sprintf('The item name "%s" is invalid', $itemName));
        }

        if (is_array($data)) {
            // new item, validate that the item doesn't already exist
            if (is_file($path)) {
                throw new Exception(sprintf('Item "%s" already exists', $itemName));
            }

            // does not already exist, init and save
            $this->setProperties($data);
            $this->save();
        } else {
            // existing item, load from file system
            if (!is_file($path)) {
                throw new Exception(sprintf('Item "%s" does not exist', $itemName));
            }

            $data = json_decode(file_get_contents($path), true);
            if (!is_array($data)) {
                throw new Exception(sprintf('Could not read item "%s"', $itemName));
            }

            $this->setProperties($data);
        }
    }

    /**
     * Returns the creation date
     *
     * @param  string $format If given, the date is returned as formatted string
     * @return mixed          Timestamp or formatted string
     */
    public function created(string $format = null)
    {
        if (is_string($format)) {
            return date($format, $this->created);
        } else {
            return $this->created;
        }
    }

    /**
     * Returns the number of downloads
     *
     * @return int
     */
    public function downloads(): int
    {
        return $this->downloads;
    }

    /**
     * Returns the expiry date
     *
     * @param  string $format If given, the date is returned as formatted string
     * @return mixed          Timestamp or formatted string
     */
    public function expires(string $format = null)
    {
        if (is_string($format)) {
            if (is_int($this->expires)) {
                return date($format, $this->expires);
            } else {
                return '–';
            }
        } else {
            return $this->expires;
        }
    }

    /**
     * Returns the filename
     *
     * @return string
     */
    public function filename(): string
    {
        return $this->filename;
    }

    /**
     * Returns the expiry date or timeout date, whichever is shorter
     *
     * @param  string $format If given, the date is returned as formatted string
     * @return mixed          Timestamp or formatted string
     */
    public function invalidityDate(string $format = null)
    {
        $expires = $this->expires();
        $timeout = $this->timeout();
        if (is_int($expires) && is_int($timeout)) {
            $invalidityDate = min($expires, $timeout);
        } elseif (is_int($expires)) {
            $invalidityDate = $expires;
        } elseif (is_int($timeout)) {
            $invalidityDate = $timeout;
        } else {
            $invalidityDate = false;
        }

        if (is_string($format)) {
            if (is_int($invalidityDate)) {
                return date($format, $invalidityDate);
            } else {
                return '–';
            }
        } else {
            return $invalidityDate;
        }
    }

    /**
     * Returns the username
     *
     * @return string
     */
    public function user(): string
    {
        return $this->user;
    }

    /**
     * Returns the timeout date based on the current activity timestamp
     *
     * @param  string $format If given, the date is returned as formatted string
     * @return mixed          Timestamp or formatted string
     */
    public function timeout(string $format = null)
    {
        if (is_int($this->timeout) && is_int($this->activity)) {
            $timeout = $this->activity + $this->timeout;
        } else {
            // timeout has not been configured or not been started
            $timeout = false;
        }


        if (is_string($format)) {
            if (is_int($timeout)) {
                return date($format, $timeout);
            } else {
                return '–';
            }
        } else {
            return $timeout;
        }
    }

    /**
     * Returns a response for the redirection route
     *
     * @return Response
     */
    public function handleRedirect(): Response
    {
        // ensure that the item is valid
        if ($this->isValid() !== true) {
            $message = ($this->app->debug())? 'Item is invalid' : 'Not found';
            return new Response($message, 'text/plain', 404);
        }

        // update item file
        $this->activity = time();
        $this->downloads++;
        $this->save();

        $url = $this->app->fileUrl($this->filename);
        return Response::redirect($url);
    }

    /**
     * Returns a response for the meta route
     *
     * @return Response
     */
    public function handleMeta(): Response
    {
        return Response::json($this->toArray(), 200, true);
    }

    /**
     * Returns a response for the deletion route
     *
     * @return Response
     */
    public function handleDeletion(): Response
    {
        $this->delete();
        return new Response('Success', 'text/plain', 200);
    }

    /**
     * Verifies that the item is currently valid
     *
     * @return boolean
     */
    public function isValid(): bool
    {
        // deleted items are automatically invalid
        if ($this->deleted === true) {
            return false;
        }

        // verify start and expiry/timeout date
        if (time() < $this->created || $this->isExpired() !== false) {
            return false;
        }

        // no error
        return true;
    }

    /**
     * Checks if the item is expired based on
     * expiry and timeout date
     *
     * @return boolean
     */
    public function isExpired(): bool
    {
        $invalidityDate = $this->invalidityDate();
        if (is_int($invalidityDate)) {
            return time() > $invalidityDate;
        } else {
            // no expiry at all
            return false;
        }
    }

    /**
     * Deletes the item and its file
     *
     * @return void
     */
    public function delete(): void
    {
        $item = $this->path;
        if (is_file($item) && @unlink($item) !== true) {
            throw new Exception(sprintf('Could not delete item "%s"', $item)); // @codeCoverageIgnore
        }

        $file = $this->app->filePath($this->filename);
        if (is_file($file) && @unlink($file) !== true) {
            throw new Exception(sprintf('Could not delete file "%s" for item "%s"', $file, $item)); // @codeCoverageIgnore
        }

        $this->deleted = true;
    }

    /**
     * Returns the item data as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'activity'  => $this->activity,
            'created'   => $this->created,
            'downloads' => $this->downloads,
            'expires'   => $this->expires,
            'filename'  => $this->filename,
            'timeout'   => $this->timeout,
            'user'      => $this->user
        ];
    }

    /**
     * Checks if the given string would be a valid item name
     *
     * @param  string  $name Arbitrary string to check
     * @return boolean
     */
    public static function isValidId(string $name): bool
    {
        return preg_match('/^[a-zA-Z0-9.\-=]+$/', $name) === 1;
    }

    /**
     * Creates a new item from options
     *
     * @param  App          $app      App instance
     * @param  string       $basePath Directory path to store new item in
     * @param  string|array $props    Filename string or option properties:
     *                                 - `created`:  When the item first becomes valid;
     *                                               defaults to "now"
     *                                 - `expires`:  When the item is no longer valid;
     *                                               defaults to "never"
     *                                 - `filename`: Reference to the filename to download;
     *                                               REQUIRED
     *                                 - `id`:       Custom ID for the item;
     *                                               defaults to a random six-char ID
     *                                 - `timeout`:  Number of seconds of inactivity (no
     *                                               downloads) after which file is deleted
     * @return Item
     */
    public static function create(App $app, string $basePath, $props): Item
    {
        if (is_string($props)) {
            $props = ['filename' => $props];
        } elseif (!is_array($props)) {
            throw new Exception('$props must be a string or array');
        }

        $props = array_merge([
            'created' => time(),
            'expires' => false,
            'timeout' => false,
            'user'    => $app->user()->username()
        ], $props);

        // expiry needs to be after creation
        if (is_int($props['expires']) && $props['expires'] < $props['created']) {
            throw new Exception(sprintf('Expiry time "%d" cannot be before creation time "%d"', $props['expires'], $props['created']));
        }

        if (isset($props['id'])) {
            // make sure the ID only contains valid chars
            if (static::isValidId($props['id']) !== true) {
                throw new Exception(sprintf('Item ID "%s" contains invalid characters', $props['id']));
            }
        } else {
            // create random ID

            // first four characters of the random ID are based on the current date
            $date = BijectiveEncoder::encode(date('ymd', $props['created']));

            // randomize second part of the ID until we have a new item
            do {
                $random = BijectiveEncoder::randomString(2);
            } while ($app->items()->exists($date . $random));

            $props['id'] = $date . $random;
        }

        $path = $basePath . '/' . $props['id'] . '.json';
        return new Item($app, $path, $props);
    }

    /**
     * Saves the item to disk
     *
     * @return void
     */
    protected function save(): void
    {
        if ($this->deleted === true) {
            throw new Exception(sprintf('The deleted item "%s" cannot be written to', $this->path));
        }

        $result = @file_put_contents($this->path, json_encode($this->toArray()));
        if (!is_int($result) || $result < 1) {
            throw new Exception(sprintf('Could not write to item file "%s"', $this->path));
        }
    }

    /**
     * Sets the activity
     *
     * @param int $activity Timestamp of last activity
     */
    protected function setActivity(int $activity = null): self
    {
        $this->activity = $activity;
        return $this;
    }

    /**
     * Sets the creation date
     *
     * @param int $created Creation timestamp
     */
    protected function setCreated(int $created): self
    {
        $this->created = $created;
        return $this;
    }

    /**
     * Sets the number of downloads
     *
     * @param int $downloads Number of downloads
     */
    protected function setDownloads(int $downloads = 0): self
    {
        $this->downloads = $downloads;
        return $this;
    }

    /**
     * Sets the expiry date
     *
     * @param int|false $expires Expiration timestamp or `false` for "never expires"
     */
    protected function setExpires($expires): self
    {
        if (!is_int($expires) && $expires !== false) {
            throw new Exception(sprintf('Invalid expiry date "%s", expected int or false', $expires));
        }

        $this->expires = $expires;
        return $this;
    }

    /**
     * Sets the filename to download
     *
     * @param string $filename Filename to download
     */
    protected function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    /**
     * Sets the timeout
     *
     * @param int|false $timeout Number of seconds or `false` for "no timeout"
     */
    protected function setTimeout($timeout): self
    {
        if (!is_int($timeout) && $timeout !== false) {
            throw new Exception(sprintf('Invalid timeout "%s", expected int or false', $timeout));
        }

        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Sets the user that created the item
     *
     * @param string $user Username
     */
    protected function setUser(string $user): self
    {
        $this->user = $user;
        return $this;
    }
}
