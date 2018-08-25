<?php

namespace LukasBestle\Shareable;

use Exception;
use FilesystemIterator;
use Kirby\Http\Response;
use Kirby\Toolkit\Collection;
use Kirby\Toolkit\F;

/**
 * Inbox
 * Collection of files in the inbox
 *
 * @package   Shareable
 * @author    Lukas Bestle <project-shareable@lukasbestle.com>
 * @copyright Lukas Bestle
 * @license   MIT
 */
class Inbox extends Collection
{
    // Constructor data
    protected $app;
    protected $filesPath;
    protected $inboxPath;

    /**
     * Class constructor
     *
     * @param App    $app       App instance
     * @param string $inboxPath Path to the inbox directory
     * @param string $filesPath Path to the files directory
     */
    public function __construct(App $app, string $inboxPath, string $filesPath)
    {
        $this->app       = $app;
        $this->inboxPath = $inboxPath;
        $this->filesPath = $filesPath;

        $data = [];
        foreach (new FilesystemIterator($inboxPath) as $file) {
            // skip dotfiles like .gitignore
            if (substr($file->getFilename(), 0, 1) === '.') {
                continue;
            }

            $data[$file->getFilename()] = $file;
        }

        parent::__construct($data);
    }

    /**
     * Deletes a file from the inbox
     *
     * @param  string   $file Filename in the inbox
     * @return Response       Redirect response back to the inbox
     */
    public function delete(string $file): Response
    {
        // make sure that the file exists
        $file = $this->get($file);
        if (!$file) {
            return new Response('Not found', 'text/plain', 404);
        }

        // delete the file
        if (@unlink($file) !== true) {
            throw new Error(sprintf('Could not delete file "%s"', $file)); // @codeCoverageIgnore
        }

        return Response::redirect(url('_admin/inbox'));
    }

    /**
     * Uploads a file to the inbox
     *
     * @return Response Redirect or plain text response depending on the
     *                  "response" property in the request
     */
    public function upload(): Response
    {
        // check if any files were uploaded
        if (!isset($_FILES['files'])) {
            throw new Exception('No file was uploaded');
        }
        $files = $_FILES['files'];

        // adapt array structure if only one file was uploaded
        if (!is_array($files['error'])) {
            $files['error']    = [$files['error']];
            $files['name']     = [$files['name']];
            $files['tmp_name'] = [$files['tmp_name']];
        }

        // loop through all uploaded files
        foreach ($files['error'] as $key => $error) {
            // every value besides 0 is some kind of error
            if ($error !== 0) {
                throw new Exception(sprintf('File upload error: "%d"', $error));
            }

            // security check
            if (!is_uploaded_file($files['tmp_name'][$key])) {
                throw new Exception(sprintf('File "%s" was not properly uploaded', $files['name'][$key]));
            }

            // make sure we don't overwrite any existing file in the inbox
            $filename = static::findFilepath($this->inboxPath, $files['name'][$key]);

            // move the uploaded file to the inbox
            $destination = $this->inboxPath . '/' . $filename;
            if (@move_uploaded_file($files['tmp_name'][$key], $destination) !== true) {
                throw new Exception(sprintf('Could not move uploaded file "%s"', $files['name'][$key]));
            }
        }

        // the response param is set inside the admin panel;
        // if not set we don't redirect (useful for other types of API clients)
        if (get('response') === 'redirect') {
            return Response::redirect(url('_admin/inbox'));
        } else {
            return new Response('Success', 'text/plain', 201);
        }
    }

    /**
     * Publishes a file from the inbox by creating the item and
     * moving the file
     *
     * @param  string   $file Filename in the inbox
     * @return Response       Redirect or plain text response depending on the
     *                        "response" property in the request
     */
    public function publish(string $file): Response
    {
        // make sure that the file exists
        $file = $this->get($file);
        if (!$file) {
            return new Response('Not found', 'text/plain', 404);
        }

        // clean up props
        $props = $this->app->request()->data();
        foreach ($props as $prop => $value) {
            // delete empty props so that the default is used
            if ($value === '') {
                unset($props[$prop]);
                continue;
            }

            // convert the date fields to timestamp
            if (in_array($prop, ['created', 'expires']) && !is_numeric($value)) {
                $props[$prop] = strtotime($value);
                if (!is_int($props[$prop])) {
                    $message = sprintf('Could not convert value "%s" for prop "%s" to timestamp', $value, $prop);
                    throw new Exception($message);
                }
            }

            // convert the timeout to an int
            if ($prop === 'timeout') {
                if (is_numeric($value)) {
                    $props[$prop] = (int)$value;
                } else {
                    // convert natural language to the number of seconds
                    $timestamp = strtotime($value);
                    if (!is_int($timestamp)) {
                        $message = sprintf('Could not convert value "%s" for prop "%s" to integer', $value, $prop);
                        throw new Exception($message);
                    }
                    $props[$prop] = $timestamp - time();
                }
            }
        }

        // make sure we don't overwrite any existing file
        $filename = static::findFilepath($this->filesPath, $file->getFilename());

        // create the item
        $props['filename'] = $filename;
        $props['user']     = $this->app->user()->username();
        $this->app->items()->create($props);

        // move the file at the end now that everything else worked
        if (rename($file, $this->filesPath . '/' . $filename) !== true) {
            throw new Exception(sprintf('Could not move file "%s" to "%s"', $file, $this->filesPath)); // @codeCoverageIgnore
        }

        // the response param is set inside the admin panel;
        // if not set we don't redirect (useful for other types of API clients)
        if (get('response') === 'redirect') {
            return Response::redirect(url('_admin/items'));
        } else {
            return new Response('Success', 'text/plain', 201);
        }
    }

    /**
     * Finds a unique file path by adding a number suffix to the
     * filename until the file doesn't exist
     *
     * @param  string $directory Path to put the file in
     * @param  string $filename  Base filename
     * @return string            Filename with the suffix
     */
    protected static function findFilepath(string $directory, string $filename): string
    {
        $basename  = F::name($filename);
        $extension = F::extension($filename);

        $suffix = 0;
        clearstatcache();
        while (is_file($directory . '/' . $filename)) {
            $suffix++;
            $filename = $basename . '-' . $suffix . '.' . $extension;
        }

        return $filename;
    }
}
