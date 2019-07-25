<?php

namespace LukasBestle\Shareable;

/**
 * @coversDefaultClass LukasBestle\Shareable\Items
 */
class ItemsTest extends TestCase
{
    protected $items;

    public function setUp()
    {
        parent::setUp();

        $this->items = $this->app->items();
    }

    /**
     * @covers ::collection
     */
    public function testCollection()
    {
        $collection = new ItemCollection($this->app, $this->itemsPath);

        $returnedCollection = $this->items->collection();
        $this->assertEquals($collection, $returnedCollection);
        $this->assertTrue($returnedCollection === $this->items->collection());
    }

    /**
     * @covers ::exists
     */
    public function testExists()
    {
        $this->assertTrue($this->items->exists('valid'));
        $this->assertFalse($this->items->exists('invalid-extension'));
        $this->assertFalse($this->items->exists('does-not-exist-at-all'));
    }

    /**
     * @covers ::get
     */
    public function testGet()
    {
        $this->assertNull($this->items->get('does-not-exist-at-all'));
        $this->assertInstanceOf(Item::class, $this->items->get('valid'));
    }

    /**
     * @covers ::create
     */
    public function testCreate()
    {
        $this->assertFileNotExists($this->itemsPath . '/tmp.json');
        $item = $this->items->create([
            'id'       => 'tmp',
            'filename' => 'some-file'
        ]);
        $this->assertEquals('some-file', $item->filename());
        $this->assertFileExists($this->itemsPath . '/tmp.json');

        unlink($this->itemsPath . '/tmp.json');
    }

    /**
     * @covers ::cleanUp
     */
    public function testCleanUp()
    {
        $this->assertFileExists($this->itemsPath . '/.i-am-invisible');
        $this->assertFileExists($this->itemsPath . '/definitely_invalid.json');
        $this->assertFileExists($this->itemsPath . '/expired.json');
        $this->assertFileExists($this->itemsPath . '/expired-with-dir.json');
        $this->assertFileExists($this->itemsPath . '/invalid-extension.txt');
        $this->assertFileExists($this->itemsPath . '/no-activity.json');
        $this->assertFileExists($this->itemsPath . '/no-expiry.json');
        $this->assertFileExists($this->itemsPath . '/not-started.json');
        $this->assertFileExists($this->itemsPath . '/only-expiry.json');
        $this->assertFileExists($this->itemsPath . '/only-timeout.json');
        $this->assertFileExists($this->itemsPath . '/valid.json');
        $this->assertFileExists($this->itemsPath . '/valid-with-dir.json');
        $this->assertFileExists($this->filesPath . '/.i-am-invisible');
        $this->assertFileExists($this->filesPath . '/another-id/file.txt');
        $this->assertFileExists($this->filesPath . '/another-id/another-file.txt');
        $this->assertFileExists($this->filesPath . '/existing-id/file.txt');
        $this->assertFileExists($this->filesPath . '/orphaned/orphaned.txt');
        $this->assertFileExists($this->filesPath . '/expired.txt');
        $this->assertFileExists($this->filesPath . '/no-activity.txt');
        $this->assertFileExists($this->filesPath . '/no-expiry.txt');
        $this->assertFileExists($this->filesPath . '/only.txt');
        $this->assertFileExists($this->filesPath . '/orphaned.abc.txt');
        $this->assertFileExists($this->filesPath . '/valid.txt');

        $warnings = $this->items->cleanUp();
        $this->assertEqualsCanonicalizing([
            'File "' . $this->filesPath . '/missing-file.txt" for item "not-started" does not exist',
            'File "' . $this->filesPath . '/another-id/another-file.txt" is orphaned',
            'File "' . $this->filesPath . '/orphaned/orphaned.txt" is orphaned',
            'File "' . $this->filesPath . '/orphaned.abc.txt" is orphaned'
        ], explode("\n", trim($warnings)));

        $this->assertFileExists($this->itemsPath . '/.i-am-invisible');
        $this->assertFileExists($this->itemsPath . '/definitely_invalid.json');
        $this->assertFileNotExists($this->itemsPath . '/expired.json');
        $this->assertFileNotExists($this->itemsPath . '/expired-with-dir.json');
        $this->assertFileExists($this->itemsPath . '/invalid-extension.txt');
        $this->assertFileExists($this->itemsPath . '/no-activity.json');
        $this->assertFileExists($this->itemsPath . '/no-expiry.json');
        $this->assertFileExists($this->itemsPath . '/not-started.json');
        $this->assertFileNotExists($this->itemsPath . '/only-expiry.json');
        $this->assertFileNotExists($this->itemsPath . '/only-timeout.json');
        $this->assertFileExists($this->itemsPath . '/valid.json');
        $this->assertFileExists($this->itemsPath . '/valid-with-dir.json');
        $this->assertFileExists($this->filesPath . '/.i-am-invisible');
        $this->assertFileExists($this->filesPath . '/another-id/file.txt');
        $this->assertFileExists($this->filesPath . '/another-id/another-file.txt');
        $this->assertFileNotExists($this->filesPath . '/existing-id/file.txt');
        $this->assertDirectoryNotExists($this->filesPath . '/existing-id');
        $this->assertFileExists($this->filesPath . '/orphaned/orphaned.txt');
        $this->assertFileNotExists($this->filesPath . '/expired.txt');
        $this->assertFileExists($this->filesPath . '/no-activity.txt');
        $this->assertFileExists($this->filesPath . '/no-expiry.txt');
        $this->assertFileNotExists($this->filesPath . '/only.txt');
        $this->assertFileExists($this->filesPath . '/orphaned.abc.txt');
        $this->assertFileExists($this->filesPath . '/valid.txt');
    }
}
