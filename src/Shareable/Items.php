<?php

namespace LukasBestle\Shareable;

use Exception;
use FilesystemIterator;

/**
 * Items
 * Management class for items
 *
 * @package   Shareable
 * @author    Lukas Bestle <project-shareable@lukasbestle.com>
 * @copyright Lukas Bestle
 * @license   MIT
 */
class Items
{
    // Constructor data
    protected $app;
    protected $path;

    // Cache
    protected $collection;

    /**
     * Class constructor
     *
     * @param App    $app  App instance
     * @param string $path Path to the items directory
     */
    public function __construct(App $app, string $path)
    {
        $this->app  = $app;
        $this->path = $path;
    }

    /**
     * Returns a collection with all items
     *
     * @return ItemCollection
     */
    public function collection(): ItemCollection
    {
        if ($this->collection) {
            return $this->collection;
        }
        return $this->collection = new ItemCollection($this->app, $this->path);
    }

    /**
     * Checks if a specified item exists
     *
     * @param  string  $item ID of the item
     * @return boolean
     */
    public function exists(string $item): bool
    {
        $path = $this->path . '/' . $item . '.json';
        return is_file($path);
    }

    /**
     * Gets an item object by ID
     *
     * @param  string $item ID of the item
     * @return Item         Item object or null if not found
     */
    public function get(string $item): ?Item
    {
        // check if the item exists
        if (!$this->exists($item)) {
            return null;
        }

        return new Item($this->app, $this->path . '/' . $item . '.json');
    }

    /**
     * Creates a new item
     *
     * @param  string|array $props Options, see Item::create()
     * @return Item                Item object
     */
    public function create($props): Item
    {
        return Item::create($this->app, $this->path, $props);
    }

    /**
     * Verifies the integrity of the items and
     * files and cleans up expired items
     *
     * @return string Warning messages
     */
    public function cleanUp(): string
    {
        $warnings = '';
        $files    = [];

        // verify validity of the items
        foreach ($this->collection() as $itemName => $item) {
            if ($item->isExpired()) {
                // delete expired item and its file
                $item->delete();
            } else {
                // item is not expired, verify if its file is still there
                $file = $this->app->filePath($item->filename());

                if (is_file($file)) {
                    // note the filename down for later
                    $files[] = $item->filename();
                } else {
                    $warnings .= sprintf(
                        'File "%s" for item "%s" does not exist' . "\n",
                        $file,
                        $itemName
                    );
                }
            }
        }

        // find orphaned files
        foreach (new FilesystemIterator($this->app->filePath()) as $file) {
            // skip dotfiles like .gitignore
            if (substr($file->getFilename(), 0, 1) === '.') {
                continue;
            }

            // check if we encountered the file in any item before
            if (!in_array($file->getFilename(), $files)) {
                $warnings .= sprintf('File "%s" is orphaned' . "\n", $file->getPathname());
            }
        }

        return $warnings;
    }
}
