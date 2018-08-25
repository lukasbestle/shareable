<?php

namespace LukasBestle\Shareable;

use Exception;
use ReflectionProperty;

class AppTest extends TestCase
{
    /**
     * @expectedException        Exception
     * @expectedExceptionMessage The property "paths[items]" is required
     */
    public function testConstructMissingPath()
    {
        new App([
            'fileUrl' => 'https://cdn.example.com',
            'paths'   => [
                'files' => $this->filesPath,
                'inbox' => $this->inboxPath,
            ]
        ]);
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage The path "/tmp/does-not-exist" does not exist or is not writable
     */
    public function testConstructInvalidPath()
    {
        new App([
            'fileUrl' => 'https://cdn.example.com',
            'paths'   => [
                'files' => $this->filesPath,
                'inbox' => $this->inboxPath,
                'items' => '/tmp/does-not-exist'
            ]
        ]);
    }

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

    public function testDebug()
    {
        $this->assertEquals(false, $this->app->debug());
    }

    public function testFileUrl()
    {
        $this->assertEquals('https://cdn.example.com/', $this->app->fileUrl());
        $this->assertEquals('https://cdn.example.com/file.txt', $this->app->fileUrl('file.txt'));
    }

    public function testFilePath()
    {
        $this->assertEquals($this->filesPath . '/', $this->app->filePath());
        $this->assertEquals($this->filesPath . '/file.txt', $this->app->filePath('file.txt'));
    }

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

    public function testRequest()
    {
        $request = $this->app->request();

        // should be cached
        $this->assertTrue($this->app->request() === $request);
    }

    /**
     * @dataProvider providerRequestPath
     */
    public function testRequestPath(string $scriptName, string $uri, string $expected)
    {
        $_SERVER['SCRIPT_NAME'] = $scriptName;
        $_SERVER['REQUEST_URI'] = $uri;
        $requestPath = $this->app->requestPath();
        $this->assertEquals($expected, $requestPath);

        // should be cached
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

    public function testSnippet()
    {
        // simple example
        $result = $this->app->snippet('templates/_test', ['var' => 'test']);
        $this->assertEquals($this->app->fileUrl() . ':test', $result);

        // non-existing snippet
        $result = $this->app->snippet('templates/__does-not-exist', ['var' => 'test']);
        $this->assertEquals('', $result);
    }

    public function testTemplates()
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
