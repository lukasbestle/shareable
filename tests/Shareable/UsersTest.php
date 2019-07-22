<?php

namespace LukasBestle\Shareable;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * @coversDefaultClass LukasBestle\Shareable\Users
 */
class UsersTest extends PHPUnitTestCase
{
    /**
     * @covers ::__construct
     */
    public function testConstruct()
    {
        $users = new Users([]);
        $this->assertEquals(1, $users->count());
        $this->assertEquals('anonymous', $users->first()->username());
        $this->assertFalse($users->first()->hasPermission('*'));

        $users = new Users([
            'anonymous' => [
                'username'    => 'some-other-username-that-will-be-overridden',
                'permissions' => ['upload']
            ],
            'admin' => []
        ]);
        $this->assertEquals(2, $users->count());
        $this->assertEquals('anonymous', $users->first()->username());
        $this->assertEquals('admin', $users->last()->username());
        $this->assertTrue($users->get('anonymous')->hasPermission('upload'));
        $this->assertFalse($users->get('anonymous')->hasPermission('publish'));
    }

    /**
     * @covers ::current
     */
    public function testCurrent()
    {
        $users = new Users([
            'admin' => [
                'password' => '$2y$10$M6Ji9eZ8mYy5Y7StubwEcOWr20fV80nyDBGOc1R9FPW3fA8sG2oDm' // 12345678
            ]
        ]);
        $_SERVER['PHP_AUTH_USER'] = 'admin';
        $_SERVER['PHP_AUTH_PW']   = '12345678';
        $this->assertEquals('admin', $users->current()->username());

        // should be cached
        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
        $this->assertEquals('admin', $users->current()->username());

        $users = new Users([
            'admin' => [
                'password' => '$2y$10$M6Ji9eZ8mYy5Y7StubwEcOWr20fV80nyDBGOc1R9FPW3fA8sG2oDm' // 12345678
            ]
        ]);
        $_SERVER['PHP_AUTH_USER'] = 'admin';
        $_SERVER['PHP_AUTH_PW']   = '87654321';
        $this->assertEquals('anonymous', $users->current()->username());

        $users = new Users([
            'admin' => [
                'password' => '$2y$10$M6Ji9eZ8mYy5Y7StubwEcOWr20fV80nyDBGOc1R9FPW3fA8sG2oDm' // 12345678
            ]
        ]);
        $_SERVER['PHP_AUTH_USER'] = 'some-user';
        $_SERVER['PHP_AUTH_PW']   = '12345678';
        $this->assertEquals('anonymous', $users->current()->username());

        $users = new Users([
            'admin' => [
                'password' => '$2y$10$M6Ji9eZ8mYy5Y7StubwEcOWr20fV80nyDBGOc1R9FPW3fA8sG2oDm' // 12345678
            ]
        ]);
        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
        $this->assertEquals('anonymous', $users->current()->username());
    }
}
