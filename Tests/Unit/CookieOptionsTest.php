<?php

namespace Tests\Unit;

use Codeception\Test\Unit;

class CookieOptionsTest extends Unit
{
    public function testSecureAndHttponlyAreAlwaysOn()
    {
        $options = \Auth\CookieOptions::build('example.com', 1234567890);
        $this->assertTrue($options['secure'], 'cookie must not travel over plain HTTP');
        $this->assertTrue($options['httponly'], 'cookie must be unreadable from document.cookie');
    }

    public function testSameSiteIsStrict()
    {
        $options = \Auth\CookieOptions::build('example.com', 1234567890);
        $this->assertEquals('Strict', $options['samesite']);
    }

    public function testExpiresAndDomainArePassedThrough()
    {
        $options = \Auth\CookieOptions::build('example.com', 1234567890);
        $this->assertEquals(1234567890, $options['expires']);
        $this->assertEquals('example.com', $options['domain']);
        $this->assertEquals('/', $options['path']);
    }

    public function testPastExpiryIsAllowedForKillingTheCookie()
    {
        $options = \Auth\CookieOptions::build('example.com', -1);
        $this->assertEquals(-1, $options['expires']);
        $this->assertTrue($options['secure']);
        $this->assertTrue($options['httponly']);
    }
}
