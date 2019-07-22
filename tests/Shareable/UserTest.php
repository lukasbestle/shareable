<?php

namespace LukasBestle\Shareable;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

use Exception;

/**
 * @coversDefaultClass LukasBestle\Shareable\User
 */
class UserTest extends PHPUnitTestCase
{
    /**
     * @covers ::__construct
     * @covers ::setUsername
     * @covers ::setPassword
     * @covers ::setPermissions
     * @covers ::username
     */
    public function testConstruct()
    {
        $user = new User([
            'username'    => 'test-user',
            'password'    => '$2y$10$M6Ji9eZ8mYy5Y7StubwEcOWr20fV80nyDBGOc1R9FPW3fA8sG2oDm', // 12345678
            'permissions' => []
        ]);
        $this->assertEquals('test-user', $user->username());
    }

    /**
     * @covers ::__construct
     * @covers ::setPassword
     */
    public function testConstructInvalid1()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid password hash for user "test-user", expected one created with password_hash()');

        new User([
            'username'    => 'test-user',
            'password'    => '$2y$10$this-is-invalid',
            'permissions' => []
        ]);
    }

    /**
     * @covers ::__construct
     * @covers ::setPermissions
     */
    public function testConstructInvalid2()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid permissions for user "test-user", expected array or boolean');

        new User([
            'username'    => 'test-user',
            'password'    => '$2y$10$M6Ji9eZ8mYy5Y7StubwEcOWr20fV80nyDBGOc1R9FPW3fA8sG2oDm',
            'permissions' => 'dunno'
        ]);
    }

    /**
     * @covers ::verifyPassword
     */
    public function testVerifyPassword()
    {
        $user = new User([
            'username' => 'test-user',
            'password' => '$2y$10$M6Ji9eZ8mYy5Y7StubwEcOWr20fV80nyDBGOc1R9FPW3fA8sG2oDm', // 12345678
        ]);
        $this->assertTrue($user->verifyPassword('12345678'));
        $this->assertFalse($user->verifyPassword('87654321'));
        $this->assertFalse($user->verifyPassword(''));

        $user = new User([
            'username' => 'test-user'
        ]);
        $this->assertFalse($user->verifyPassword('12345678'));
        $this->assertFalse($user->verifyPassword('87654321'));
        $this->assertFalse($user->verifyPassword(''));
    }

    /**
     * @covers ::hasPermission
     */
    public function testHasPermission()
    {
        $user = new User([
            'username'    => 'test-user',
            'permissions' => []
        ]);
        $this->assertFalse($user->hasPermission('upload'));
        $this->assertFalse($user->hasPermission('*'));
        $this->assertFalse($user->hasPermission(['upload', 'publish']));

        $user = new User([
            'username'    => 'test-user',
            'permissions' => ['publish']
        ]);
        $this->assertTrue($user->hasPermission('publish'));
        $this->assertFalse($user->hasPermission('upload'));
        $this->assertTrue($user->hasPermission('*'));
        $this->assertTrue($user->hasPermission(['upload', 'publish']));
        $this->assertFalse($user->hasPermission(['upload', 'delete']));

        $user = new User([
            'username'    => 'test-user',
            'permissions' => true
        ]);
        $this->assertTrue($user->hasPermission('upload'));
        $this->assertTrue($user->hasPermission('*'));
        $this->assertTrue($user->hasPermission(['upload', 'publish']));

        $user = new User([
            'username'    => 'test-user',
            'permissions' => false
        ]);
        $this->assertFalse($user->hasPermission('upload'));
        $this->assertFalse($user->hasPermission('*'));
        $this->assertFalse($user->hasPermission(['upload', 'publish']));
    }

    /**
     * @covers ::hasPermission
     */
    public function testHasPermissionInvalid()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid param $permission, expected string or array');

        $user = new User([
            'username'    => 'test-user',
            'permissions' => false
        ]);
        $user->hasPermission(true);
    }
}
