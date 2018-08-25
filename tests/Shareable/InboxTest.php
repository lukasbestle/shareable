<?php

namespace LukasBestle\Shareable;

use ReflectionMethod;
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

    public function testConstruct()
    {
        $this->assertEquals(2, $this->inbox->count());
        $this->assertEquals([
            'new-file.txt',
            'valid.txt'
        ], $this->inbox->keys());
        $this->assertInstanceOf(SplFileInfo::class, $this->inbox->get('valid.txt'));
    }

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
     * @expectedException        Exception
     * @expectedExceptionMessage No file was uploaded
     */
    public function testUploadNoFiles()
    {
        $this->inbox->upload();
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage File upload error: "1"
     */
    public function testUploadError()
    {
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
     * @expectedException        Exception
     * @expectedExceptionMessage File "valid.txt" was not properly uploaded
     */
    public function testUploadNotUploadedFile()
    {
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
     * @expectedException        Exception
     * @expectedExceptionMessage Could not move uploaded file "error.txt"
     */
    public function testUploadMovingError()
    {
        $_FILES = [
            'files' => [
                'name'     => 'error.txt',
                'tmp_name' => dirname(__DIR__) . '/fixtures/uploads/some-unique-id.txt',
                'error'    => 0
            ]
        ];

        $this->inbox->upload();
    }

    public function testPublish()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'created' => '2018-01-02',
            'expires' => '',
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
            'expires'   => false,
            'filename'  => 'new-file.txt',
            'timeout'   => 172800,
            'user'      => 'anonymous'
        ], $this->app->items()->get('new-id')->toArray());
        $this->assertFileExists($this->filesPath . '/new-file.txt');
        $this->assertEquals('new-file', file_get_contents($this->filesPath . '/new-file.txt'));
        $this->assertEquals(201, $response->code());
        $this->assertEquals('text/plain', $response->type());
        $this->assertEquals('Success', $response->body());

        // non-existing file
        $response = $this->inbox->publish('does-not-exist.txt');
        $this->assertEquals(404, $response->code());
        $this->assertEquals('text/plain', $response->type());
        $this->assertEquals('Not found', $response->body());
    }

    public function testPublishRedirect()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'created'  => '2018-01-02',
            'expires'  => '',
            'id'       => 'new-id',
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
            'filename'  => 'valid-1.txt',
            'timeout'   => 172800,
            'user'      => 'anonymous'
        ], $this->app->items()->get('new-id')->toArray());
        $this->assertFileExists($this->filesPath . '/valid-1.txt');
        $this->assertEquals('valid-inbox', file_get_contents($this->filesPath . '/valid-1.txt'));
        $this->assertEquals('/_admin/items', $response->header('Location'));
        $this->assertEquals(302, $response->code());
    }

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
            'filename'  => 'new-file.txt',
            'timeout'   => 3600,
            'user'      => 'anonymous'
        ], $this->app->items()->get('new-id')->toArray());
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Could not convert value "some gibberish" for prop "created" to timestamp
     */
    public function testPublishInvalidCreated()
    {
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
     * @expectedException        Exception
     * @expectedExceptionMessage Could not convert value "some gibberish" for prop "timeout" to integer
     */
    public function testPublishInvalidTimeout()
    {
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

    public function testFindFilepath()
    {
        $method = new ReflectionMethod(Inbox::class, 'findFilepath');
        $method->setAccessible(true);

        $this->assertEquals('new-file.txt', $method->invoke(null, $this->filesPath, 'new-file.txt'));
        $this->assertEquals('valid-1.txt', $method->invoke(null, $this->filesPath, 'valid.txt'));
        $this->assertEquals('only-2.txt', $method->invoke(null, $this->filesPath, 'only.txt'));
        $this->assertEquals('only-1-1.txt', $method->invoke(null, $this->filesPath, 'only-1.txt'));
        $this->assertEquals('new-file.abc.txt', $method->invoke(null, $this->filesPath, 'new-file.abc.txt'));
        $this->assertEquals('orphaned.abc-1.txt', $method->invoke(null, $this->filesPath, 'orphaned.abc.txt'));
    }
}
