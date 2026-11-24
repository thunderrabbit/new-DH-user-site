<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

class HeadersTest extends Unit
{
    public function testPagesCannotBeFramedByAnotherSite(): void
    {
        $headers = \Security\Headers::all();

        $this->assertStringContainsString("frame-ancestors 'none'", $headers['Content-Security-Policy']);
        $this->assertSame('DENY', $headers['X-Frame-Options']);
    }

    public function testAnInjectedBaseTagCannotRepointThePage(): void
    {
        $this->assertStringContainsString("base-uri 'self'", \Security\Headers::all()['Content-Security-Policy']);
    }

    public function testBrowsersMustNotGuessContentTypes(): void
    {
        $this->assertSame('nosniff', \Security\Headers::all()['X-Content-Type-Options']);
    }

    public function testPageUrlsAreNotSentToOtherSites(): void
    {
        $this->assertSame('same-origin', \Security\Headers::all()['Referrer-Policy']);
    }

    public function testHttpsIsRememberedForAYearForThisHostOnly(): void
    {
        $hsts = \Security\Headers::all()['Strict-Transport-Security'];

        $this->assertSame('max-age=31536000', $hsts);
        $this->assertStringNotContainsString('includeSubDomains', $hsts);
    }
}
