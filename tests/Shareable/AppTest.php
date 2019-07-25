<?php

namespace LukasBestle\Shareable;

use Exception;
use ReflectionProperty;

/**
 * @coversDefaultClass LukasBestle\Shareable\App
 */
class AppTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::setDebug
     * @covers ::setFileUrl
     * @covers ::setSubdirs
     * @covers ::setPaths
     * @covers ::setRoutes
     * @covers ::setUsers
     * @covers ::debug
     * @covers ::filePath
     * @covers ::subdirs
     * @covers ::users
     */
    public function testConstruct()
    {
        $routesProp = new ReflectionProperty(App::class, 'routes');
        $routesProp->setAccessible(true);

        // minimum config
        $app = new App([
            'fileUrl' => 'https://cdn.example.com',
            'paths'   => [
                'files' => $this->filesPath,
                'inbox' => $this->inboxPath,
                'items' => $this->itemsPath
            ]
        ]);
        $this->assertEquals(false, $app->debug());
        $this->assertEquals('https://cdn.example.com/', $app->fileUrl());
        $this->assertEquals(true, $app->subdirs());
        $this->assertEquals($this->filesPath . '/', $app->filePath());
        $this->assertEquals(1, count($app->users()));
        $this->assertEquals('anonymous', $app->users()->first()->username());

        // overridden defaults
        $app = new App([
            'debug'   => true,
            'fileUrl' => 'https://example.com/cdn///',
            'subdirs' => false,
            'paths'   => [
                'files' => $this->filesPath . '///',
                'inbox' => $this->inboxPath,
                'items' => $this->itemsPath
            ],
            'routes' => [
                'test-route'
            ],
            'users' => [
                'lukas' => []
            ]
        ]);
        $this->assertEquals(true, $app->debug());
        $this->assertEquals('https://example.com/cdn/', $app->fileUrl());
        $this->assertEquals(false, $app->subdirs());
        $this->assertEquals($this->filesPath . '/', $app->filePath());
        $this->assertContains('test-route', $routesProp->getValue($app));
        $this->assertEquals(2, count($app->users()));
        $this->assertEquals('lukas', $app->users()->first()->username());
    }

    /**
     * @covers ::__construct
     * @covers ::setPaths
     */
    public function testConstructMissingPath()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The property "paths[items]" is required');

        new App([
            'fileUrl' => 'https://cdn.example.com',
            'paths'   => [
                'files' => $this->filesPath,
                'inbox' => $this->inboxPath
            ]
        ]);
    }

    /**
     * @covers ::__construct
     * @covers ::setPaths
     */
    public function testConstructInvalidPath()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The path "/tmp/does-not-exist" does not exist or is not writable');

        new App([
            'fileUrl' => 'https://cdn.example.com',
            'paths'   => [
                'files' => $this->filesPath,
                'inbox' => $this->inboxPath,
                'items' => '/tmp/does-not-exist'
            ]
        ]);
    }

    /**
     * @covers ::render
     */
    public function testRender()
    {
        $app = $this->instanceWithRoutes(false);

        // simple route
        $response = $app->render('test-simple', 'GET');
        $this->assertEquals('test-simple', $response);

        // route with auth but without passed user
        $response = $app->render('test-auth', 'POST');
        $this->assertEquals('Authentication required', $response->body());
        $this->assertEquals('text/plain', $response->type());
        $this->assertEquals(401, $response->code());
        $this->assertEquals('Basic', $response->header('WWW-Authenticate'));

        // route with auth with valid user
        $_SERVER['PHP_AUTH_USER'] = 'test-user';
        $_SERVER['PHP_AUTH_PW']   = '12345678';
        $app = $this->instanceWithRoutes(false);
        $response = $app->render('test-auth', 'POST');
        $this->assertEquals('test-auth', $response);

        // route with auth without permission
        $response = $app->render('test-no-permission', 'POST');
        $this->assertEquals('Authentication required', $response->body());
        $this->assertEquals('text/plain', $response->type());
        $this->assertEquals(401, $response->code());
        $this->assertEquals('Basic', $response->header('WWW-Authenticate'));

        // route with Exception
        $response = $app->render('test-throw', 'GET');
        $this->assertEquals('Internal server error', $response->body());
        $this->assertEquals('text/plain', $response->type());
        $this->assertEquals(500, $response->code());

        // route with Exception and debugging
        $app = $this->instanceWithRoutes(true);
        $response = $app->render('test-throw', 'GET');
        $this->assertStringStartsWith('Exception: Some error occured', $response->body());
        $this->assertEquals('text/plain', $response->type());
        $this->assertEquals(500, $response->code());
    }

    /**
     * @covers ::debug
     */
    public function testDebug()
    {
        $this->assertEquals(false, $this->app->debug());
    }

    /**
     * @covers ::fileUrl
     */
    public function testFileUrl()
    {
        $this->assertEquals('https://cdn.example.com/', $this->app->fileUrl());
        $this->assertEquals('https://cdn.example.com/file.txt', $this->app->fileUrl('file.txt'));
    }

    /**
     * @covers ::filePath
     */
    public function testFilePath()
    {
        $this->assertEquals($this->filesPath . '/', $this->app->filePath());
        $this->assertEquals($this->filesPath . '/file.txt', $this->app->filePath('file.txt'));
    }

    /**
     * @covers ::inbox
     */
    public function testInbox()
    {
        $appProp = new ReflectionProperty(Inbox::class, 'app');
        $appProp->setAccessible(true);
        $filesPathProp = new ReflectionProperty(Inbox::class, 'filesPath');
        $filesPathProp->setAccessible(true);
        $inboxPathProp = new ReflectionProperty(Inbox::class, 'inboxPath');
        $inboxPathProp->setAccessible(true);

        $inbox = $this->app->inbox();
        $this->assertEquals($this->app, $appProp->getValue($inbox));
        $this->assertEquals($this->filesPath, $filesPathProp->getValue($inbox));
        $this->assertEquals($this->inboxPath, $inboxPathProp->getValue($inbox));

        // should be cached
        $this->assertTrue($this->app->inbox() === $inbox);
    }

    /**
     * @covers ::items
     */
    public function testItems()
    {
        $appProp = new ReflectionProperty(Items::class, 'app');
        $appProp->setAccessible(true);
        $pathProp = new ReflectionProperty(Items::class, 'path');
        $pathProp->setAccessible(true);

        $items = $this->app->items();
        $this->assertEquals($this->app, $appProp->getValue($items));
        $this->assertEquals($this->itemsPath, $pathProp->getValue($items));

        // should be cached
        $this->assertTrue($this->app->items() === $items);
    }

    /**
     * @covers ::user
     */
    public function testUser()
    {
        $app = $this->instanceWithRoutes(false);

        // current user without login
        $this->assertEquals('anonymous', $app->user()->username());

        // current user with login
        $_SERVER['PHP_AUTH_USER'] = 'test-user';
        $_SERVER['PHP_AUTH_PW']   = '12345678';
        $app = $this->instanceWithRoutes(false);
        $this->assertEquals('test-user', $app->user()->username());

        // specified user
        $this->assertEquals('anonymous', $app->user('anonymous')->username());
        $this->assertEquals('admin', $app->user('admin')->username());
        $this->assertNull($app->user('does-not-exist'));
    }

    /**
     * @covers ::request
     */
    public function testRequest()
    {
        $request = $this->app->request();

        // should be cached
        $this->assertTrue($this->app->request() === $request);
    }

    /**
     * @covers       ::requestPath
     * @dataProvider providerRequestPath
     */
    public function testRequestPath(string $scriptName, string $uri, string $expected)
    {
        $_SERVER['SCRIPT_NAME'] = $scriptName;
        $_SERVER['REQUEST_URI'] = $uri;
        $requestPath = $this->app->requestPath();
        $this->assertEquals($expected, $requestPath);

        // should be cached
        $_SERVER['REQUEST_URI'] = 'something-different';
        $this->assertTrue($this->app->requestPath() === $requestPath);
    }

    public function providerRequestPath()
    {
        return [
            ['/index.php',        '/',            ''],
            ['/index.php',        '/test',        'test'],
            ['/subdir/index.php', '/subdir/test', 'test'],
        ];
    }

    /**
     * @covers ::requestPath
     * @covers ::setRequestPath
     */
    public function testRequestPathCustom()
    {
        $app = new App([
            'fileUrl'     => 'https://cdn.example.com',
            'requestPath' => '/amazing-path//',
            'paths'       => [
                'files' => $this->filesPath,
                'inbox' => $this->inboxPath,
                'items' => $this->itemsPath
            ]
        ]);

        $this->assertEquals('amazing-path', $app->requestPath());
    }

    /**
     * @covers ::snippet
     */
    public function testSnippet()
    {
        // simple example
        $result = $this->app->snippet('templates/_test', ['var' => 'test']);
        $this->assertEquals($this->app->fileUrl() . ':test', $result);

        // non-existing snippet
        $result = $this->app->snippet('templates/__does-not-exist', ['var' => 'test']);
        $this->assertEquals('', $result);
    }

    /**
     * @covers ::template
     */
    public function testTemplate()
    {
        // simple example
        $result = $this->app->template('_test', ['var' => 'test']);
        $this->assertEquals(200, $result->code());
        $this->assertEquals('text/html', $result->type());
        $this->assertEquals($this->app->fileUrl() . ':test', $result->body());

        // non-existing template
        $result = $this->app->template('__does-not-exist', ['var' => 'test']);
        $this->assertEquals(200, $result->code());
        $this->assertEquals('text/html', $result->type());
        $this->assertEquals('', $result->body());
    }

    /**
     * Returns a fully populated app instance for testing $app->render() and $app->user()
     *
     * @param  bool $debug Value for debugging option
     * @return App
     */
    protected function instanceWithRoutes(bool $debug): App
    {
        return new App([
            'debug'   => $debug,
            'fileUrl' => 'https://cdn.example.com',
            'paths'   => [
                'files' => $this->filesPath,
                'inbox' => $this->inboxPath,
                'items' => $this->itemsPath,
            ],
            'routes' => [
                [
                    'pattern' => 'test-simple',
                    'method'  => 'GET',
                    'action'  => function () {
                        return 'test-simple';
                    }
                ],
                [
                    'pattern' => 'test-auth',
                    'method'  => 'POST',
                    'auth'    => 'test',
                    'action'  => function () {
                        return 'test-auth';
                    }
                ],
                [
                    'pattern' => 'test-no-permission',
                    'method'  => 'POST',
                    'auth'    => 'some-other-permission',
                    'action'  => function () {
                        return 'test-no-permission';
                    }
                ],
                [
                    'pattern' => 'test-throw',
                    'method'  => 'GET',
                    'action'  => function () {
                        throw new Exception('Some error occured');
                    }
                ]
            ],
            'users' => [
                'admin' => [
                    'password'    => '$2y$10$M6Ji9eZ8mYy5Y7StubwEcOWr20fV80nyDBGOc1R9FPW3fA8sG2oDm', // 12345678
                    'permissions' => true
                ],
                'test-user' => [
                    'password'    => '$2y$10$M6Ji9eZ8mYy5Y7StubwEcOWr20fV80nyDBGOc1R9FPW3fA8sG2oDm', // 12345678
                    'permissions' => ['test']
                ]
            ]
        ]);
    }
}
