<?php

namespace LukasBestle\Shareable;

use FilesystemIterator;
use ReflectionMethod;
use ReflectionProperty;
use Kirby\Toolkit\F;

class ItemTest extends TestCase
{
    public function testInit()
    {
        $item = new Item($this->app, $this->itemsPath . '/valid.json');
        $this->assertEquals(543, $item->downloads());
        $this->assertEquals('valid.txt', $item->filename());
        $this->assertEquals('admin', $item->user());
        $this->assertEquals([
            'activity'  => 9999999999,
            'created'   => 1234567890,
            'downloads' => 543,
            'expires'   => 9999999999,
            'filename'  => 'valid.txt',
            'timeout'   => 2678400,
            'user'      => 'admin'
        ], $item->toArray());
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage The item name "definitely_invalid" is invalid
     */
    public function testInitInvalid()
    {
        new Item($this->app, $this->itemsPath . '/definitely_invalid.json');
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Item "does-not-exist" does not exist
     */
    public function testInitNotExisting()
    {
        new Item($this->app, $this->itemsPath . '/does-not-exist.json');
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Could not read item "broken-json"
     */
    public function testInitBroken()
    {
        new Item($this->app, $this->edgecasesPath . '/broken-json.json');
    }

    public function testCreated()
    {
        $item = new Item($this->app, $this->itemsPath . '/valid.json');
        $this->assertEquals(1234567890, $item->created());
        $this->assertEquals('2009-02-13', $item->created('Y-m-d'));
    }

    public function testExpires()
    {
        $item = new Item($this->app, $this->itemsPath . '/expired.json');
        $this->assertEquals(1239999999, $item->expires());
        $this->assertEquals('2009-04-17', $item->expires('Y-m-d'));

        $item = new Item($this->app, $this->itemsPath . '/no-expiry.json');
        $this->assertEquals(false, $item->expires());
        $this->assertEquals('–', $item->expires('Y-m-d'));
    }

    public function testInvalidityDate()
    {
        $item = new Item($this->app, $this->itemsPath . '/expired.json');
        $this->assertEquals(1239999999, $item->invalidityDate());
        $this->assertEquals('2009-04-17', $item->invalidityDate('Y-m-d'));

        $item = new Item($this->app, $this->itemsPath . '/no-expiry.json');
        $this->assertEquals(false, $item->invalidityDate());
        $this->assertEquals('–', $item->invalidityDate('Y-m-d'));

        $item = new Item($this->app, $this->itemsPath . '/only-expiry.json');
        $this->assertEquals(1239999999, $item->invalidityDate());
        $this->assertEquals('2009-04-17', $item->invalidityDate('Y-m-d'));

        $item = new Item($this->app, $this->itemsPath . '/only-timeout.json');
        $this->assertEquals(1242591999, $item->invalidityDate());
        $this->assertEquals('2009-05-17', $item->invalidityDate('Y-m-d'));
    }

    public function testTimeout()
    {
        $item = new Item($this->app, $this->itemsPath . '/expired.json');
        $this->assertEquals(1242591999, $item->timeout());
        $this->assertEquals('2009-05-17', $item->timeout('Y-m-d'));

        $item = new Item($this->app, $this->itemsPath . '/no-activity.json');
        $this->assertEquals(1237246290, $item->timeout());
        $this->assertEquals('2009-03-16', $item->timeout('Y-m-d'));

        $item = new Item($this->app, $this->itemsPath . '/no-expiry.json');
        $this->assertEquals(false, $item->timeout());
        $this->assertEquals('–', $item->timeout('Y-m-d'));
    }

    public function testHandleRedirect()
    {
        $item = new Item($this->app, $this->itemsPath . '/valid.json');
        $this->assertEquals(543, $item->downloads());
        $this->assertEquals(9999999999, $item->toArray()['activity']);

        $expectedTime = time();
        $response = $item->handleRedirect();
        $this->assertEquals('https://cdn.example.com/valid.txt', $response->header('Location'));
        $this->assertEquals(302, $response->code());
        $this->assertEquals(544, $item->downloads());
        $this->assertEquals($expectedTime, $item->toArray()['activity']);

        // reload from filesystem, should have been saved
        $item = new Item($this->app, $this->itemsPath . '/valid.json');
        $this->assertEquals(544, $item->downloads());
        $this->assertEquals($expectedTime, $item->toArray()['activity']);
    }

    public function testHandleRedirectInvalid()
    {
        $item = new Item($this->app, $this->itemsPath . '/expired.json');
        $response = $item->handleRedirect();
        $this->assertEquals(404, $response->code());
        $this->assertEquals('text/plain', $response->type());
        $this->assertEquals('Not found', $response->body());
    }

    public function testHandleMeta()
    {
        $item = new Item($this->app, $this->itemsPath . '/valid.json');
        $response = $item->handleMeta();
        $this->assertEquals(200, $response->code());
        $this->assertEquals('application/json', $response->type());
        $this->assertEquals(json_encode($item->toArray(), JSON_PRETTY_PRINT), $response->body());
    }

    public function testHandleDeletion()
    {
        // setup
        copy($this->itemsPath . '/valid.json', $this->itemsPath . '/valid.json');
        $this->assertFileExists($this->filesPath . '/valid.txt');
        $this->assertFileExists($this->itemsPath . '/valid.json');

        $item = new Item($this->app, $this->itemsPath . '/valid.json');
        $response = $item->handleDeletion();
        $this->assertEquals(200, $response->code());
        $this->assertEquals('text/plain', $response->type());
        $this->assertEquals('Success', $response->body());

        $this->assertFileNotExists($this->filesPath . '/valid.txt');
        $this->assertFileNotExists($this->itemsPath . '/valid.json');
    }

    public function testIsValid()
    {
        // deleted item
        copy($this->itemsPath . '/valid.json', $this->itemsPath . '/valid.json');
        $item = new Item($this->app, $this->itemsPath . '/valid.json');
        $this->assertTrue($item->isValid());
        $this->assertFalse($item->isExpired());
        $item->delete();
        $this->assertFalse($item->isValid());
        $this->assertFalse($item->isExpired());

        // not started item
        $item = new Item($this->app, $this->itemsPath . '/not-started.json');
        $this->assertFalse($item->isValid());
        $this->assertFalse($item->isExpired());

        // expired item
        $item = new Item($this->app, $this->itemsPath . '/expired.json');
        $this->assertFalse($item->isValid());
        $this->assertTrue($item->isExpired());

        // item without expiry
        $item = new Item($this->app, $this->itemsPath . '/no-expiry.json');
        $this->assertTrue($item->isValid());
        $this->assertFalse($item->isExpired());
    }

    public function testDelete()
    {
        // setup
        copy($this->itemsPath . '/valid.json', $this->itemsPath . '/valid.json');
        $this->assertFileExists($this->filesPath . '/valid.txt');
        $this->assertFileExists($this->itemsPath . '/valid.json');

        $item = new Item($this->app, $this->itemsPath . '/valid.json');
        $item->delete();

        $this->assertFileNotExists($this->filesPath . '/valid.txt');
        $this->assertFileNotExists($this->itemsPath . '/valid.json');
    }

    public function testIsValidId()
    {
        $this->assertTrue(Item::isValidId('abc01234567.-=ABC'));
        $this->assertFalse(Item::isValidId('aeiöü'));
        $this->assertFalse(Item::isValidId('abcde_fghij'));
        $this->assertFalse(Item::isValidId('abc/def'));
    }

    public function testCreate()
    {
        $pathProperty = new ReflectionProperty(Item::class, 'path');
        $pathProperty->setAccessible(true);

        // simple invokation
        $expectedTime = time();
        $item = Item::create($this->app, $this->itemsPath, 'test-item');
        $this->assertEquals($expectedTime, $item->created());
        $this->assertEquals(0, $item->downloads());
        $this->assertFalse($item->expires());
        $this->assertEquals('test-item', $item->filename());
        $this->assertEquals('anonymous', $item->user());
        $this->assertFalse($item->timeout());
        $path = $pathProperty->getValue($item);
        $this->assertFileExists($path);
        $this->assertEquals(6, strlen(F::name($path)));

        // custom props
        $item = Item::create($this->app, $this->itemsPath, [
            'created'  => 7899999999,
            'expires'  => 9999999999,
            'filename' => 'another-test-item',
            'timeout'  => 12345,
            'user'     => 'admin'
        ]);
        $this->assertEquals(7899999999, $item->created());
        $this->assertEquals(0, $item->downloads());
        $this->assertEquals(9999999999, $item->expires());
        $this->assertEquals('another-test-item', $item->filename());
        $this->assertEquals('admin', $item->user());
        $this->assertEquals(7899999999 + 12345, $item->timeout());
        $path = $pathProperty->getValue($item);
        $this->assertFileExists($path);
        $this->assertEquals(6, strlen(F::name($path)));

        // custom ID
        $this->assertFileNotExists($this->itemsPath . '/testtest.json');
        $expectedTime = time();
        $item = Item::create($this->app, $this->itemsPath, [
            'filename' => 'another-test-item',
            'id'       => 'testtest'
        ]);
        $this->assertEquals($expectedTime, $item->created());
        $this->assertEquals(0, $item->downloads());
        $this->assertFalse($item->expires());
        $this->assertEquals('another-test-item', $item->filename());
        $this->assertEquals('anonymous', $item->user());
        $this->assertFalse($item->timeout());
        $this->assertFileExists($this->itemsPath . '/testtest.json');
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage $props must be a string or array
     */
    public function testCreateInvalidProps()
    {
        $item = Item::create($this->app, $this->itemsPath, 12345);
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage The property "filename" is required
     */
    public function testCreateMissingFilename()
    {
        $item = Item::create($this->app, $this->itemsPath, []);
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Expiry time "1234567890" cannot be before creation time "9999999999"
     */
    public function testCreateInvalidExpiryRelation()
    {
        $item = Item::create($this->app, $this->itemsPath, [
            'created' => 9999999999,
            'expires' => 1234567890
        ]);
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Invalid expiry date "at some point", expected int or false
     */
    public function testCreateInvalidExpiresValue()
    {
        $item = Item::create($this->app, $this->itemsPath, [
            'expires'  => 'at some point',
            'filename' => 'test-item'
        ]);
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Invalid timeout "at some point", expected int or false
     */
    public function testCreateInvalidTimeoutValue()
    {
        $item = Item::create($this->app, $this->itemsPath, [
            'filename' => 'test-item',
            'timeout'  => 'at some point'
        ]);
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Item ID "definitely_invalid" contains invalid characters
     */
    public function testCreateInvalidId()
    {
        $item = Item::create($this->app, $this->itemsPath, [
            'id'       => 'definitely_invalid',
            'filename' => 'test-item'
        ]);
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Item "testtest" already exists
     */
    public function testCreateAlreadyExists()
    {
        touch($this->itemsPath . '/testtest.json');
        $item = Item::create($this->app, $this->itemsPath, [
            'filename' => 'another-test-item',
            'id'       => 'testtest'
        ]);
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage The deleted item "
     */
    public function testSaveDeleted()
    {
        // setup
        copy($this->itemsPath . '/valid.json', $this->itemsPath . '/valid.json');

        $item = new Item($this->app, $this->itemsPath . '/valid.json');
        $item->delete();

        $saveMethod = new ReflectionMethod(Item::class, 'save');
        $saveMethod->setAccessible(true);
        $saveMethod->invoke($item);
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Could not write to item file "
     */
    public function testSaveNotWritable()
    {
        // setup
        copy($this->itemsPath . '/valid.json', $this->itemsPath . '/valid.json');

        $item = new Item($this->app, $this->itemsPath . '/valid.json');

        chmod($this->itemsPath . '/valid.json', 000);

        $saveMethod = new ReflectionMethod(Item::class, 'save');
        $saveMethod->setAccessible(true);
        $saveMethod->invoke($item);
    }
}
