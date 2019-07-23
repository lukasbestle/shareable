<?php

namespace LukasBestle\Shareable;

use Exception;
use ReflectionMethod;
use ReflectionProperty;
use SplFileInfo;
use Kirby\Cms\App as Kirby;
use Kirby\Toolkit\F;

/**
 * Mock for PHP's is_uploaded_file() function to make tests work
 */
function is_uploaded_file(string $filename): bool
{
    return file_exists($filename);
}

/**
 * Mock for PHP's move_uploaded_file() function to make tests work
 */
function move_uploaded_file(string $filename, string $destination): bool
{
    // faked error
    if (F::name($destination) === 'error') {
        return false;
    }

    return copy($filename, $destination);
}

/**
 * @coversDefaultClass LukasBestle\Shareable\Inbox
 */
class InboxTest extends TestCase
{
    protected $inbox;

    public function setUp()
    {
        parent::setUp();

        $this->inbox = $this->app->inbox();
    }

    public function tearDown()
    {
        parent::tearDown();

        unset($_FILES);
    }

    /**
     * @covers ::__construct
     */
    public function testConstruct()
    {
        $this->assertEquals(2, $this->inbox->count());
        $this->assertEquals([
            'new-file.txt',
            'valid.txt'
        ], $this->inbox->keys());
        $this->assertInstanceOf(SplFileInfo::class, $this->inbox->get('valid.txt'));
    }

    /**
     * @covers ::delete
     */
    public function testDelete()
    {
        // existing file
        $this->assertFileExists($this->inboxPath . '/new-file.txt');
        $response = $this->inbox->delete('new-file.txt');
        $this->assertEquals('/_admin/inbox', $response->header('Location'));
        $this->assertEquals(302, $response->code());
        $this->assertFileNotExists($this->inboxPath . '/new-file.txt');

        // non-existing file
        $response = $this->inbox->delete('does-not-exist.txt');
        $this->assertEquals(404, $response->code());
        $this->assertEquals('text/plain', $response->type());
        $this->assertEquals('Not found', $response->body());
    }

    /**
     * @covers ::upload
     */
    public function testUpload()
    {
        $_FILES = [
            'files' => [
                'name'     => 'valid.txt',
                'tmp_name' => dirname(__DIR__) . '/fixtures/uploads/some-unique-id.txt',
                'error'    => 0
            ]
        ];

        // normal API call
        $response = $this->inbox->upload();
        $this->assertFileExists($this->inboxPath . '/valid-1.txt');
        $this->assertEquals('uploaded-file', file_get_contents($this->inboxPath . '/valid-1.txt'));
        $this->assertEquals(201, $response->code());
        $this->assertEquals('text/plain', $response->type());
        $this->assertEquals('Success', $response->body());

        // redirection
        $_GET['response'] = 'redirect';
        new Kirby(); // create new Request object
        $response = $this->inbox->upload();
        $this->assertFileExists($this->inboxPath . '/valid-2.txt');
        $this->assertEquals('uploaded-file', file_get_contents($this->inboxPath . '/valid-2.txt'));
        $this->assertEquals('/_admin/inbox', $response->header('Location'));
        $this->assertEquals(302, $response->code());
    }

    /**
     * @covers ::upload
     */
    public function testUploadMultiple()
    {
        $_FILES = [
            'files' => [
                'name'     => ['valid.txt', 'valid.txt'],
                'tmp_name' => [
                    dirname(__DIR__) . '/fixtures/uploads/some-unique-id.txt',
                    dirname(__DIR__) . '/fixtures/uploads/some-unique-id.txt'
                ],
                'error'    => [0, 0]
            ]
        ];

        $this->inbox->upload();
        $this->assertFileExists($this->inboxPath . '/valid-1.txt');
        $this->assertEquals('uploaded-file', file_get_contents($this->inboxPath . '/valid-1.txt'));
        $this->assertFileExists($this->inboxPath . '/valid-2.txt');
        $this->assertEquals('uploaded-file', file_get_contents($this->inboxPath . '/valid-2.txt'));
    }

    /**
     * @covers ::upload
     */
    public function testUploadNoFiles()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No file was uploaded');

        $this->inbox->upload();
    }

    /**
     * @covers ::upload
     */
    public function testUploadError()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('File upload error: "1"');

        $_FILES = [
            'files' => [
                'name'     => ['valid.txt', 'valid.txt'],
                'tmp_name' => [
                    dirname(__DIR__) . '/fixtures/uploads/some-unique-id.txt',
                    dirname(__DIR__) . '/fixtures/uploads/some-unique-id.txt'
                ],
                'error'    => [0, 1]
            ]
        ];

        $this->inbox->upload();
    }

    /**
     * @covers ::upload
     */
    public function testUploadNotUploadedFile()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('File "valid.txt" was not properly uploaded');

        $_FILES = [
            'files' => [
                'name'     => 'valid.txt',
                'tmp_name' => dirname(__DIR__) . '/fixtures/uploads/does-not-exist.txt',
                'error'    => 0
            ]
        ];

        $this->inbox->upload();
    }

    /**
     * @covers ::upload
     */
    public function testUploadMovingError()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not move uploaded file "error.txt"');

        $_FILES = [
            'files' => [
                'name'     => 'error.txt',
                'tmp_name' => dirname(__DIR__) . '/fixtures/uploads/some-unique-id.txt',
                'error'    => 0
            ]
        ];

        $this->inbox->upload();
    }

    /**
     * @covers ::publish
     */
    public function testPublish()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'created' => '2018-01-02',
            'expires' => '+1 day',
            'id'      => 'new-id',
            'timeout' => '+2 days',
            'user'    => 'admin'
        ];

        // existing file
        $response = $this->inbox->publish('new-file.txt');
        $this->assertEquals([
            'activity'  => null,
            'created'   => strtotime('2018-01-02'),
            'downloads' => 0,
            'expires'   => strtotime('2018-01-03'),
            'filename'  => 'new-id/new-file.txt',
            'timeout'   => 172800,
            'user'      => 'anonymous'
        ], $this->app->items()->get('new-id')->toArray());
        $this->assertFileExists($this->filesPath . '/new-id/new-file.txt');
        $this->assertEquals('new-file', file_get_contents($this->filesPath . '/new-id/new-file.txt'));
        $this->assertEquals(201, $response->code());
        $this->assertEquals('text/plain', $response->type());
        $this->assertEquals('Success', $response->body());

        // non-existing file
        $response = $this->inbox->publish('does-not-exist.txt');
        $this->assertEquals(404, $response->code());
        $this->assertEquals('text/plain', $response->type());
        $this->assertEquals('Not found', $response->body());
    }

    /**
     * @covers ::publish
     */
    public function testPublishRedirect()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'created'  => '2018-01-02',
            'expires'  => '',
            'id'       => 'existing-id',
            'timeout'  => '+2 days',
            'user'    => 'admin',

            'response' => 'redirect'
        ];

        new Kirby(); // create new Request object
        $response = $this->inbox->publish('valid.txt');
        $this->assertEquals([
            'activity'  => null,
            'created'   => strtotime('2018-01-02'),
            'downloads' => 0,
            'expires'   => false,
            'filename'  => 'existing-id-1/valid.txt',
            'timeout'   => 172800,
            'user'      => 'anonymous'
        ], $this->app->items()->get('existing-id')->toArray());
        $this->assertFileExists($this->filesPath . '/existing-id-1/valid.txt');
        $this->assertEquals('valid-inbox', file_get_contents($this->filesPath . '/existing-id-1/valid.txt'));
        $this->assertEquals('/_admin/items', $response->header('Location'));
        $this->assertEquals(302, $response->code());
    }

    /**
     * @covers ::publish
     */
    public function testPublishNumericTimeout()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'created' => '2018-01-02',
            'expires' => '',
            'id'      => 'new-id',
            'timeout' => '3600',
            'user'    => 'admin'
        ];

        $response = $this->inbox->publish('new-file.txt');
        $this->assertEquals([
            'activity'  => null,
            'created'   => strtotime('2018-01-02'),
            'downloads' => 0,
            'expires'   => false,
            'filename'  => 'new-id/new-file.txt',
            'timeout'   => 3600,
            'user'      => 'anonymous'
        ], $this->app->items()->get('new-id')->toArray());
    }

    /**
     * @covers ::publish
     */
    public function testPublishImmediateTimeout1()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'created'             => '2018-01-02',
            'expires'             => '',
            'id'                  => 'new-id',
            'timeout'             => 3600,
            'timeout-immediately' => 'true',
            'user'                => 'admin'
        ];

        $response = $this->inbox->publish('new-file.txt');
        $this->assertEquals([
            'activity'  => strtotime('2018-01-02'),
            'created'   => strtotime('2018-01-02'),
            'downloads' => 0,
            'expires'   => false,
            'filename'  => 'new-id/new-file.txt',
            'timeout'   => 3600,
            'user'      => 'anonymous'
        ], $this->app->items()->get('new-id')->toArray());
    }

    /**
     * @covers ::publish
     */
    public function testPublishImmediateTimeout2()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'created'             => '',
            'expires'             => '',
            'id'                  => 'new-id',
            'timeout'             => 3600,
            'timeout-immediately' => 'true',
            'user'                => 'admin'
        ];

        $response = $this->inbox->publish('new-file.txt');
        $this->assertEquals([
            'activity'  => time(),
            'created'   => time(),
            'downloads' => 0,
            'expires'   => false,
            'filename'  => 'new-id/new-file.txt',
            'timeout'   => 3600,
            'user'      => 'anonymous'
        ], $this->app->items()->get('new-id')->toArray());
    }

    /**
     * @covers ::publish
     */
    public function testPublishImmediateTimeout3()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'created'             => '2018-01-02',
            'expires'             => '',
            'id'                  => 'new-id',
            'timeout'             => 3600,
            'timeout-immediately' => 'gibberish',
            'user'                => 'admin'
        ];

        $response = $this->inbox->publish('new-file.txt');
        $this->assertEquals([
            'activity'  => null,
            'created'   => strtotime('2018-01-02'),
            'downloads' => 0,
            'expires'   => false,
            'filename'  => 'new-id/new-file.txt',
            'timeout'   => 3600,
            'user'      => 'anonymous'
        ], $this->app->items()->get('new-id')->toArray());
    }

    /**
     * @covers ::publish
     */
    public function testPublishInvalidCreated()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not convert value "some gibberish" for field "Created" to timestamp');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'created' => 'some gibberish',
            'expires' => '',
            'id'      => 'new-id',
            'timeout' => '+2 days',
            'user'    => 'admin'
        ];

        $this->inbox->publish('valid.txt');
    }

    /**
     * @covers ::publish
     */
    public function testPublishInvalidTimeout()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not convert value "some gibberish" for field "Timeout" to integer');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'created' => '2018-01-02',
            'expires' => '',
            'id'      => 'new-id',
            'timeout' => 'some gibberish',
            'user'    => 'admin'
        ];

        $this->inbox->publish('valid.txt');
    }

    /**
     * @covers ::publish
     */
    public function testPublishInvalidTimeoutImmediate()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot start timeout immediately if no timeout is set');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'created'             => '2018-01-02',
            'expires'             => '',
            'id'                  => 'new-id',
            'timeout'             => '',
            'timeout-immediately' => 'true',
            'user'                => 'admin'
        ];

        $this->inbox->publish('valid.txt');
    }

    /**
     * @covers ::findFilepath
     */
    public function testFindFilepath()
    {
        $findFilepathMethod = new ReflectionMethod(Inbox::class, 'findFilepath');
        $findFilepathMethod->setAccessible(true);

        $subdirsProp = new ReflectionProperty(App::class, 'subdirs');
        $subdirsProp->setAccessible(true);

        // flat file mode
        $this->assertEquals('new-file.txt', $findFilepathMethod->invoke($this->inbox, $this->filesPath, null, 'new-file.txt'));
        $this->assertEquals('valid-1.txt', $findFilepathMethod->invoke($this->inbox, $this->filesPath, null, 'valid.txt'));
        $this->assertEquals('only-2.txt', $findFilepathMethod->invoke($this->inbox, $this->filesPath, null, 'only.txt'));
        $this->assertEquals('only-1-1.txt', $findFilepathMethod->invoke($this->inbox, $this->filesPath, null, 'only-1.txt'));
        $this->assertEquals('new-file.abc.txt', $findFilepathMethod->invoke($this->inbox, $this->filesPath, null, 'new-file.abc.txt'));
        $this->assertEquals('orphaned.abc-1.txt', $findFilepathMethod->invoke($this->inbox, $this->filesPath, null, 'orphaned.abc.txt'));

        // subdir mode
        $this->assertDirectoryNotExists($this->filesPath . '/id');
        $this->assertEquals('id/new-file.txt', $findFilepathMethod->invoke($this->inbox, $this->filesPath, 'id', 'new-file.txt'));
        $this->assertDirectoryExists($this->filesPath . '/id');

        $this->assertEquals('different-id/valid.txt', $findFilepathMethod->invoke($this->inbox, $this->filesPath, 'different-id', 'valid.txt'));
        $this->assertEquals('different-id-1/only.txt', $findFilepathMethod->invoke($this->inbox, $this->filesPath, 'different-id', 'only.txt'));
        $this->assertEquals('different-id-2/only.txt', $findFilepathMethod->invoke($this->inbox, $this->filesPath, 'different-id', 'only.txt'));
        $this->assertEquals('different-id-1-1/only.txt', $findFilepathMethod->invoke($this->inbox, $this->filesPath, 'different-id-1', 'only.txt'));

        // subdir mode but subdirs disabled in config
        $subdirsProp->setValue($this->app, false);
        $this->assertEquals('new-file.txt', $findFilepathMethod->invoke($this->inbox, $this->filesPath, 'id', 'new-file.txt'));
    }

    /**
     * @covers ::parseTime
     */
    public function testParseTime()
    {
        $method = new ReflectionMethod(Inbox::class, 'parseTime');
        $method->setAccessible(true);

        $this->assertEquals(12345, $method->invoke(null, 'Test Field', 12345, 0));
        $this->assertEquals(12345, $method->invoke(null, 'Test Field', 12345, 100000));
        $this->assertEquals(12345, $method->invoke(null, 'Test Field', '12345', 0));
        $this->assertEquals(12345, $method->invoke(null, 'Test Field', '12345', 100000));
        $this->assertEquals(strtotime('2018-01-01'), $method->invoke(null, 'Test Field', '2018-01-01', 0));
        $this->assertEquals(strtotime('2018-01-01'), $method->invoke(null, 'Test Field', '2018-01-01', 100000));
        $this->assertEquals(3600, $method->invoke(null, 'Test Field', '+1 hour', 0));
        $this->assertEquals(103600, $method->invoke(null, 'Test Field', '+1 hour', 100000));
    }

    /**
     * @covers ::parseTime
     */
    public function testParseTimeInvalid()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not convert value "some-gibberish" for field "Test Field" to timestamp');

        $method = new ReflectionMethod(Inbox::class, 'parseTime');
        $method->setAccessible(true);

        $method->invoke(null, 'Test Field', 'some-gibberish', 0);
    }
}
