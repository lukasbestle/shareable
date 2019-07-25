<?php

namespace LukasBestle\Shareable;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

use Exception;

/**
 * @coversDefaultClass LukasBestle\Shareable\BijectiveEncoder
 */
class BijectiveEncoderTest extends PHPUnitTestCase
{
    protected $defaultAlphabet;
    protected static $methodCache = [];

    public function setUp()
    {
        // make a backup of the default alphabet
        $this->defaultAlphabet = BijectiveEncoder::$alphabet;
    }

    public function tearDown()
    {
        // restore the default alphabet
        BijectiveEncoder::$alphabet = $this->defaultAlphabet;
    }

    /**
     * @covers       ::encode
     * @covers       ::decode
     * @dataProvider providerBase36
     */
    public function testBase36($integer)
    {
        // for a "normal" base36 alphabet the behavior should be identical
        // to PHP's base_convert() function
        BijectiveEncoder::$alphabet = '0123456789abcdefghijklmnopqrstuvwxyz';

        $expected = base_convert($integer, 10, 36);
        $this->assertEquals($expected, BijectiveEncoder::encode($integer));
        $this->assertEquals($integer, BijectiveEncoder::decode($expected));
    }

    public function providerBase36()
    {
        return [
            [0],
            [1],
            [42],
            [1337],
            [987654],
            [3141592653589793]
        ];
    }

    /**
     * @covers       ::encode
     * @covers       ::decode
     * @dataProvider providerDefault
     */
    public function testDefault($integer, $string)
    {
        $this->assertEquals($string, BijectiveEncoder::encode($integer));
        $this->assertEquals($integer, BijectiveEncoder::decode($string));
    }

    public function providerDefault()
    {
        return [
            [0,    '2'],
            [1,    '3'],
            [10,   'd'],
            [48,   'Z'],
            [49,   '32'],
            [50,   '33'],
            [97,   '3Z'],
            [98,   '42'],
            [490,  'd2'],
            [2400, 'ZZ'],
            [2401, '322']
        ];
    }

    /**
     * @covers ::encode
     */
    public function testEncodeInvalid()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Only positive integers are supported');

        BijectiveEncoder::encode(-1);
    }

    /**
     * @covers ::decode
     */
    public function testDecodeInvalid1()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Char "0" is not in the alphabet');

        // default alphabet doesn't have 0 and 1
        BijectiveEncoder::decode('2c7h01ac');
    }

    /**
     * @covers ::decode
     */
    public function testDecodeInvalid2()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Char "a" is not in the alphabet');

        // alphabet without "a"
        BijectiveEncoder::$alphabet = '0123456789bcdefghijklmnopqrstuvwxyz';
        BijectiveEncoder::decode('2c7h01ac');
    }

    /**
     * @covers ::randomString
     */
    public function testRandomString()
    {
        BijectiveEncoder::$alphabet = '0123456789abcdef';
        $result = BijectiveEncoder::randomString(5);

        $this->assertEquals(5, strlen($result));
        $this->assertStringMatchesFormat('%x', $result);
    }

    /**
     * @covers ::randomString
     */
    public function testRandomStringInvalid1()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('$chars must be at least 1');

        BijectiveEncoder::randomString(0);
    }

    /**
     * @covers ::randomString
     */
    public function testRandomStringInvalid2()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('$chars must be at least 1');

        BijectiveEncoder::randomString(-1);
    }
}
