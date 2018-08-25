<?php

namespace LukasBestle\Shareable;

use Exception;
use Kirby\Http\Request;
use Kirby\Http\Response;
use Kirby\Http\Router;
use Kirby\Toolkit\Properties;
use Kirby\Toolkit\Tpl;

/**
 * App
 * Main app class
 *
 * @package   Shareable
 * @author    Lukas Bestle <project-shareable@lukasbestle.com>
 * @copyright Lukas Bestle
 * @license   MIT
 */
class App
{
    use Properties;

    // Config properties
    protected $debug = false;
    protected $fileUrl;
    protected $paths;
    protected $routes = [];
    protected $users = [];

    // Request state
    protected $request;
    protected $requestPath;

    // Child objects
    protected $inbox;
    protected $items;

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
     * Returns the response for a specified request
     *
     * @param  string|null $path   Request path; defaults to auto-detection
     * @param  string|null $method Request method; defaults to auto-detection
     * @return Response
     */
    public function render(string $path = null, string $method = null)
    {
        $router = new Router($this->routes);

        // find the route
        $path   = $path   ?? $this->requestPath();
        $method = $method ?? $this->request()->method();
        $route  = $router->find($path, $method);

        // verify authorization of the current user
        $auth = $route->auth();
        if ($auth && $this->user()->hasPermission($auth) !== true) {
            return new Response(
                'Authentication required',
                'text/plain',
                401,
                ['WWW-Authenticate' => 'Basic']
            );
        }

        // call the route
        try {
            return $route->action()->call($route, ...$route->arguments());
        } catch (Exception $e) {
            if ($this->debug === true) {
                return new Response($e, 'text/plain', 500);
            } else {
                return new Response('Internal server error', 'text/plain', 500);
            }
        }
    }

    /**
     * Returns the debugging mode
     *
     * @return boolean
     */
    public function debug(): bool
    {
        return $this->debug;
    }

    /**
     * Returns the file URL for a specified file
     * or the root file URL if no argument is given
     *
     * @param  string $filename Arbitrary filename to be appended
     * @return string
     */
    public function fileUrl(string $filename = ''): string
    {
        return $this->fileUrl . $filename;
    }

    /**
     * Returns the file path for a specified file
     * or the root file path if no argument is given
     *
     * @param  string $filename Arbitrary filename to be appended
     * @return string
     */
    public function filePath(string $filename = ''): string
    {
        return $this->paths['files'] . '/' . $filename;
    }

    /**
     * Returns the Inbox instance
     *
     * @return Inbox
     */
    public function inbox(): Inbox
    {
        return $this->inbox = $this->inbox ?? new Inbox($this, $this->paths['inbox'], $this->paths['files']);
    }

    /**
     * Returns the Items instance
     *
     * @return Items
     */
    public function items(): Items
    {
        return $this->items = $this->items ?? new Items($this, $this->paths['items']);
    }

    /**
     * Returns the user collection
     *
     * @return Users
     */
    public function users(): Users
    {
        return $this->users;
    }

    /**
     * Returns a specified or the current user
     *
     * @param  string|null $username Username or null for the current user
     * @return User
     */
    public function user(string $username = null): ?User
    {
        if (is_string($username)) {
            return $this->users()->get($username);
        } else {
            return $this->users()->current();
        }
    }

    /**
     * Returns the Request singleton
     *
     * @return Request
     */
    public function request(): Request
    {
        return $this->request = $this->request ?? new Request();
    }

    /**
     * Returns the request path
     *
     * @return string
     */
    public function requestPath()
    {
        if (is_string($this->requestPath)) {
            return $this->requestPath;
        }

        $requestUri  = '/' . $this->request()->url()->path();
        $scriptName  = $_SERVER['SCRIPT_NAME'];
        $scriptFile  = basename($scriptName);
        $scriptDir   = dirname($scriptName);
        $scriptPath  = ($scriptFile === 'index.php')? $scriptDir : $scriptName;
        $requestPath = ltrim(preg_replace('!^' . preg_quote($scriptPath) . '!', '', $requestUri), '/');

        $this->setRequestPath($requestPath);
        return $requestPath;
    }

    /**
     * Renders a template/snippet and returns the HTML content
     *
     * @param  string $snippet Snippet name
     * @param  array  $data    Optional additional data
     * @return string
     */
    public function snippet(string $snippet, array $data = []): string
    {
        $data['app'] = $this;
        $path        = dirname(dirname(__DIR__)) . '/etc/' . $snippet . '.php';

        return Tpl::load($path, $data);
    }

    /**
     * Renders a template and returns a response object
     *
     * @param  string   $template Template name
     * @param  array    $data     Optional additional data
     * @return Response           HTML response object
     */
    public function template(string $template, array $data = []): Response
    {
        $template = $this->snippet('templates/' . $template, $data);
        return new Response($template, 'text/html', 200);
    }

    /**
     * Sets the debugging option
     * When enabled, all thrown Exceptions are output to the client
     *
     * @param bool $debug Whether debugging should be enabled
     */
    protected function setDebug(bool $debug = false): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Sets the file URL
     *
     * @param string $url Absolute URL
     */
    protected function setFileUrl(string $url): self
    {
        // ensure that the URL ends with exactly one slash
        $this->fileUrl = rtrim($url, '/') . '/';
        return $this;
    }

    /**
     * Sets the paths
     *
     * @param array $paths Array with the "files" and "items" paths
     */
    protected function setPaths(array $paths): self
    {
        $keys = ['files', 'inbox', 'items'];
        foreach ($keys as $key) {
            if (!isset($paths[$key])) {
                throw new Exception(sprintf('The property "paths[%s]" is required', $key));
            }

            // check if the directory exists and is writable
            if (!is_writable($paths[$key])) {
                throw new Exception(sprintf('The path "%s" does not exist or is not writable', $paths[$key]));
            }

            $this->paths[$key] = rtrim($paths[$key], '/');
        }

        return $this;
    }

    /**
     * Sets the request path that is
     * used for the router
     *
     * @param string $path URI path that is currently being requested
     */
    protected function setRequestPath(string $requestPath = null): self
    {
        $this->requestPath = ($requestPath !== null)? trim($requestPath, '/') : null;
        return $this;
    }

    /**
     * Sets the routes
     *
     * @param array $routes Array of optional routes
     */
    protected function setRoutes(array $routes = []): self
    {
        $app = $this;

        // @codeCoverageIgnoreStart
        $defaultRoutes = [
            // Homepage
            [
                'pattern' => '/',
                'method'  => 'GET',
                'action'  => function () {
                    return Response::redirect(url('_admin'));
                }
            ],

            // Redirect to an item
            [
                'pattern' => '([a-zA-Z0-9.\-=]+)',
                'method'  => 'GET',
                'action'  => function ($item) use ($app) {
                    $item = $app->items()->get($item);

                    if (!is_a($item, Item::class)) {
                        return new Response('Not found', 'text/plain', 404);
                    }

                    return $item->handleRedirect();
                }
            ],

            // Admin homepage
            [
                'pattern' => '_admin',
                'method'  => 'GET',
                'auth'    => '*',
                'action'  => function () use ($app) {
                    return $app->template('admin/home');
                }
            ],

            // CSS file for the admin area
            [
                'pattern' => '_admin.css',
                'method'  => 'GET',
                'auth'    => '*',
                'action'  => function () use ($app) {
                    $css = file_get_contents(dirname(dirname(__DIR__)) . '/etc/admin.css');
                    return new Response($css, 'text/css', 200);
                }
            ],

            // Render inbox page
            [
                'pattern' => '_admin/inbox',
                'method'  => 'GET',
                'auth'    => ['upload', 'publish'],
                'action'  => function () use ($app) {
                    return $app->template('admin/inbox');
                }
            ],

            // Handle file upload
            [
                'pattern' => '_admin/inbox',
                'method'  => 'POST',
                'auth'    => 'upload',
                'action'  => function () use ($app) {
                    try {
                        return $app->inbox()->upload();
                    } catch (Exception $e) {
                        return $app->template('admin/inbox', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            ],

            // Render inbox page for file
            [
                'pattern' => '_admin/inbox/(:any)',
                'method'  => 'GET',
                'auth'    => 'publish',
                'action'  => function ($file) use ($app) {
                    $file = $app->inbox()->get(urldecode($file));

                    // make sure that the file exists
                    if (!$file) {
                        return new Response('Not found', 'text/plain', 404);
                    }

                    return $app->template('admin/inbox-file', compact('file'));
                }
            ],

            // Delete an inbox file from the admin interface
            [
                'pattern' => '_admin/inbox/(:any)/delete',
                'method'  => 'GET',
                'auth'    => 'publish',
                'action'  => function ($file) use ($app) {
                    return $app->inbox()->delete(urldecode($file));
                }
            ],

            // Handle publishing request
            [
                'pattern' => '_admin/inbox/(:any)',
                'method'  => 'POST',
                'auth'    => 'publish',
                'action'  => function ($file) use ($app) {
                    $file = urldecode($file);

                    try {
                        return $app->inbox()->publish($file);
                    } catch (Exception $e) {
                        return $app->template('admin/inbox-file', [
                            'file'  => $app->inbox()->get($file),
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            ],

            // Render item overview
            [
                'pattern' => '_admin/items',
                'method'  => 'GET',
                'auth'    => 'meta',
                'action'  => function () use ($app) {
                    $collection = $app->items()->collection()->sortBy('created', 'desc')->paginate(20, get('page'));
                    return $app->template('admin/items', compact('collection'));
                }
            ],

            // Return meta info about an item
            [
                'pattern' => '_admin/items/([a-zA-Z0-9.\-=]+)',
                'method'  => 'GET',
                'auth'    => 'meta',
                'action'  => function ($item) use ($app) {
                    $item = $app->items()->get($item);

                    if (!is_a($item, Item::class)) {
                        return new Response('Not found', 'text/plain', 404);
                    }

                    return $item->handleMeta();
                }
            ],

            // Delete an item
            [
                'pattern' => '_admin/items/([a-zA-Z0-9.\-=]+)',
                'method'  => 'DELETE',
                'auth'    => 'delete',
                'action'  => function ($item) use ($app) {
                    $item = $app->items()->get($item);

                    if (!is_a($item, Item::class)) {
                        return new Response('Not found', 'text/plain', 404);
                    }

                    return $item->handleDeletion();
                }
            ],

            // Delete an item from the admin interface
            [
                'pattern' => '_admin/items/([a-zA-Z0-9.\-=]+)/delete',
                'auth'    => 'delete',
                'action'  => function ($item) use ($app) {
                    $item = $app->items()->get($item);

                    if (!is_a($item, Item::class)) {
                        return new Response('Not found', 'text/plain', 404);
                    }

                    $item->delete();
                    return Response::redirect(url('_admin/items'));
                }
            ],

            // Fallback for invalid methods or paths
            [
                'pattern' => '(:all)',
                'method'  => 'ALL',
                'action'  => function ($path) {
                    return new Response('Bad Request', 'text/plain', 400);
                }
            ],
        ];
        // @codeCoverageIgnoreEnd

        // merge in the custom routes from the config
        $this->routes = array_merge($routes, $defaultRoutes);
        return $this;
    }

    /**
     * Sets the users
     *
     * @param array $users Array of optional users
     */
    protected function setUsers(array $users = []): self
    {
        $this->users = new Users($users);
        return $this;
    }
}
