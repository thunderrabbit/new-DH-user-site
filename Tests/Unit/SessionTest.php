<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

/**
 * The options only: starting a session is not a Unit-suite job.
 */
class SessionTest extends Unit
{
    public function testTheCookieNameCarriesTheHostPrefix(): void
    {
        $this->assertStringStartsWith('__Host-', \Auth\Session::options()['name']);
    }

    public function testTheCookieMeetsWhatTheHostPrefixDemands(): void
    {
        // A browser drops a __Host- cookie that is not Secure, has a Domain,
        // or has a Path other than "/", and the visitor would get no session.
        $options = \Auth\Session::options();
        $this->assertSame('1', $options['cookie_secure']);
        $this->assertSame('', $options['cookie_domain']);
        $this->assertSame('/', $options['cookie_path']);
    }

    public function testScriptsCannotReadTheCookie(): void
    {
        $this->assertSame('1', \Auth\Session::options()['cookie_httponly']);
    }

    public function testSameSiteIsLaxLikeTheLoginCookie(): void
    {
        $this->assertSame('Lax', \Auth\Session::options()['cookie_samesite']);
    }

    public function testASessionIdTheServerDidNotIssueIsRefused(): void
    {
        $options = \Auth\Session::options();
        $this->assertSame('1', $options['use_strict_mode']);
        $this->assertSame('1', $options['use_only_cookies']);
    }
}
