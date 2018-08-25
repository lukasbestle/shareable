<?php

namespace LukasBestle\Shareable;

use Kirby\Cms\App as Kirby;
use Kirby\Toolkit\Dir;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase
{
    protected $app;
    protected $filesPath;
    protected $inboxPath;
    protected $itemsPath;

    public function setUp()
    {
        $this->edgecasesPath = dirname(__DIR__) . '/tmp/edgecases';
        $this->filesPath     = dirname(__DIR__) . '/tmp/files';
        $this->inboxPath     = dirname(__DIR__) . '/tmp/inbox';
        $this->itemsPath     = dirname(__DIR__) . '/tmp/items';

        // copy to the tmp directory as a working copy
        Dir::copy(dirname(__DIR__) . '/fixtures/edgecases', $this->edgecasesPath);
        Dir::copy(dirname(__DIR__) . '/fixtures/files', $this->filesPath);
        Dir::copy(dirname(__DIR__) . '/fixtures/inbox', $this->inboxPath);
        Dir::copy(dirname(__DIR__) . '/fixtures/items', $this->itemsPath);

        $this->app = new App([
            'fileUrl' => 'https://cdn.example.com',
            'paths' => [
                'files' => $this->filesPath,
                'inbox' => $this->inboxPath,
                'items' => $this->itemsPath,
            ]
        ]);
    }

    public function tearDown()
    {
        Dir::remove($this->edgecasesPath);
        Dir::remove($this->filesPath);
        Dir::remove($this->inboxPath);
        Dir::remove($this->itemsPath);

        $_SERVER['PHP_AUTH_USER']  = null;
        $_SERVER['PHP_AUTH_PW']    = null;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $_POST = [];
        new Kirby(); // create new Request object
    }
}
