<?php

namespace LukasBestle\Shareable;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class UserTest extends PHPUnitTestCase
{
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
     * @expectedException        Exception
     * @expectedExceptionMessage Invalid password hash for user "test-user", expected one created with password_hash()
     */
    public function testConstructInvalid1()
    {
        new User([
            'username'    => 'test-user',
            'password'    => '$2y$10$this-is-invalid',
            'permissions' => []
        ]);
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Invalid permissions for user "test-user", expected array or boolean
     */
    public function testConstructInvalid2()
    {
        new User([
            'username'    => 'test-user',
            'password'    => '$2y$10$M6Ji9eZ8mYy5Y7StubwEcOWr20fV80nyDBGOc1R9FPW3fA8sG2oDm',
            'permissions' => 'dunno'
        ]);
    }

    public function testVerifyPassword()
    {
        $user = new User([
            'username' => 'test-user',
            'password' => '$2y$10$M6Ji9eZ8mYy5Y7StubwEcOWr20fV80nyDBGOc1R9FPW3fA8sG2oDm', // 12345678
        ]);
        $this->assertTrue($user->verifyPassword('12345678'));
        $this->assertFalse($user->verifyPassword('87654321'));

        $user = new User([
            'username' => 'test-user'
        ]);
        $this->assertFalse($user->verifyPassword('12345678'));
        $this->assertFalse($user->verifyPassword('87654321'));
    }

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
     * @expectedException        Exception
     * @expectedExceptionMessage Invalid param $permission, expected string or array
     */
    public function testHasPermissionInvalid()
    {
        $user = new User([
            'username'    => 'test-user',
            'permissions' => false
        ]);
        $user->hasPermission(true);
    }
}
