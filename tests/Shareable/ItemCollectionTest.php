<?php

namespace LukasBestle\Shareable;

/**
 * @coversDefaultClass LukasBestle\Shareable\ItemCollection
 */
class ItemCollectionTest extends TestCase
{
    /**
     * @covers ::__construct
     */
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
