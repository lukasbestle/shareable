<?php

namespace LukasBestle\Shareable;

class ItemCollectionTest extends TestCase
{
    public function testConstruct()
    {
        $collection = new ItemCollection($this->app, $this->itemsPath);
        $this->assertEquals(7, $collection->count());
        $this->assertEquals([
            'expired',
            'no-activity',
            'no-expiry',
            'not-started',
            'only-expiry',
            'only-timeout',
            'valid'
        ], $collection->keys());
        $this->assertInstanceOf(Item::class, $collection->get('valid'));
    }
}
