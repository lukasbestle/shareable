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
            $filename = $this->findFilepath($this->inboxPath, null, $files['name'][$key]);

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
        $props = [];
        $data = $this->app->request()->data();

        if (isset($data['created']) && $data['created'] !== '') {
            $props['created'] = static::parseTime('Created', $data['created'], time());
        }

        if (isset($data['expires']) && $data['expires'] !== '') {
            $props['expires'] = static::parseTime('Expires', $data['expires'], $props['created'] ?? time());
        }

        if (isset($data['id']) && $data['id'] !== '') {
            $props['id'] = $data['id'];
        }

        if (isset($data['timeout']) && $data['timeout'] !== '') {
            if (is_numeric($data['timeout'])) {
                $props['timeout'] = (int)$data['timeout'];
            } else {
                // convert natural language to the number of seconds
                $timestamp = strtotime($data['timeout']);
                if (!is_int($timestamp)) {
                    $message = sprintf('Could not convert value "%s" for field "Timeout" to integer', $data['timeout']);
                    throw new Exception($message);
                }
                $props['timeout'] = $timestamp - time();
            }
        }

        // start timeout immediately if requested
        if (isset($data['timeout-immediately']) && $data['timeout-immediately'] === 'true') {
            // only supported if a timeout is set
            if (!isset($props['timeout'])) {
                throw new Exception('Cannot start timeout immediately if no timeout is set');
            }

            $props['activity'] = $props['created'] ?? time();
        }

        // make sure we don't overwrite any existing file
        $filename = $this->findFilepath($this->filesPath, $props['id'] ?? uniqid(), $file->getFilename());

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
     * filename/name of the subdir until the file/dir doesn't exist
     *
     * @param  string      $directory Path to put the file in
     * @param  string|null $subdir    Subdir name to use (when enabled); null for none
     * @param  string      $filename  Base filename
     * @return string                 Filename with the suffix
     */
    protected function findFilepath(string $directory, ?string $subdir, string $filename): string
    {
        $suffix = 0;
        clearstatcache();

        // check if we are in subdir mode
        if ($this->app->subdirs() === true && is_string($subdir)) {
            // yes, find a unique folder name
            $originalSubdir = $subdir;

            while (file_exists($directory . '/' . $subdir)) {
                $suffix++;
                $subdir = $originalSubdir . '-' . $suffix;
            }

            // ensure that the directory is there
            mkdir($directory . '/' . $subdir);
            return $subdir . '/' . $filename;
        }

        // no, find a unique file name
        $basename  = F::name($filename);
        $extension = F::extension($filename);

        while (file_exists($directory . '/' . $filename)) {
            $suffix++;
            $filename = $basename . '-' . $suffix . '.' . $extension;
        }

        return $filename;
    }

    /**
     * Converts a time string (human-readable form) to a timestamp
     *
     * @param  string $name  Field name for error messages
     * @param  string $value Value to parse
     * @param  int    $base  Base timestamp for relative times
     * @return int
     */
    protected static function parseTime(string $name, string $value, int $base): int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }

        $valueConverted = strtotime($value, $base);
        if (!is_int($valueConverted)) {
            $message = sprintf('Could not convert value "%s" for field "%s" to timestamp', $value, $name);
            throw new Exception($message);
        }

        return $valueConverted;
    }
}
