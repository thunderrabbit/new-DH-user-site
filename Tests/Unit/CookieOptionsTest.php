<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

class CookieOptionsTest extends Unit
{
    public function testSecureAndHttponlyAreAlwaysOn(): void
    {
        $options = \Auth\CookieOptions::build('example.com', 1234567890);
        $this->assertTrue($options['secure'], 'cookie must not travel over plain HTTP');
        $this->assertTrue($options['httponly'], 'cookie must be unreadable from document.cookie');
    }

    public function testSameSiteIsLaxSoInboundLinksStayLoggedIn(): void
    {
        $options = \Auth\CookieOptions::build('example.com', 1234567890);
        $this->assertEquals('Lax', $options['samesite'], 'Strict drops the cookie on links from other sites');
    }

    public function testExpiresAndDomainArePassedThrough(): void
    {
        $options = \Auth\CookieOptions::build('example.com', 1234567890);
        $this->assertEquals(1234567890, $options['expires']);
        $this->assertEquals('example.com', $options['domain']);
        $this->assertEquals('/', $options['path']);
    }

    public function testPastExpiryIsAllowedForKillingTheCookie(): void
    {
        $options = \Auth\CookieOptions::build('example.com', -1);
        $this->assertEquals(-1, $options['expires']);
        $this->assertTrue($options['secure']);
        $this->assertTrue($options['httponly']);
    }
}
