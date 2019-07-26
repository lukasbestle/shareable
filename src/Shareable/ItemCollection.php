<?php

namespace LukasBestle\Shareable;

use FilesystemIterator;
use Kirby\Toolkit\Collection;

/**
 * ItemCollection
 * Collection of items
 *
 * @package   Shareable
 * @author    Lukas Bestle <project-shareable@lukasbestle.com>
 * @copyright Lukas Bestle
 * @license   MIT
 */
class ItemCollection extends Collection
{
    /**
     * Class constructor
     *
     * @param App    $app  App instance
     * @param string $path Path to the items directory
     */
    public function __construct(App $app, string $path)
    {
        $data = [];
        foreach (new FilesystemIterator($path) as $file) {
            // skip non-JSON files
            if ($file->getExtension() !== 'json') {
                continue;
            }

            // skip files with an invalid ID
            $basename = $file->getBasename('.json');
            if (Item::isValidId($basename) !== true) {
                continue;
            }

            $data[$basename] = new Item($app, $file->getPathname());
        }

        // sort by item name
        ksort($data);

        $this->data = $data;
    }
}
