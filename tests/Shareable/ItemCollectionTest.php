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
        $this->assertEquals(9, $collection->count());
        $this->assertEquals([
            'expired',
            'expired-with-dir',
            'no-activity',
            'no-expiry',
            'not-started',
            'only-expiry',
            'only-timeout',
            'valid',
            'valid-with-dir'
        ], $collection->keys());
        $this->assertInstanceOf(Item::class, $collection->get('valid'));
    }
}
