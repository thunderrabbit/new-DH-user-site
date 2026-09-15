<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

/**
 * $_SESSION is just an array to PHP until session_start() is called, so the
 * suite can hand the class a session without ever starting one (the suite
 * must stay session-free, see _bootstrap.php).
 */
class CSRFProtectarooTest extends Unit
{
    protected function _before(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    protected function _after(): void
    {
        unset($_SESSION, $_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_X_CSRF_TOKEN']);
        $_POST = [];
    }

    private function newCsrf(): \Security\CSRFProtectaroo
    {
        return new \Security\CSRFProtectaroo(new \Mlaphp\Request());
    }

    public function testTokenIsMintedOnceAndStoredInTheSession(): void
    {
        $csrf = $this->newCsrf();
        $first = $csrf->getToken();
        $this->assertSame(64, strlen($first), '32 random bytes as hex');
        $this->assertSame($first, $csrf->getToken(), 'same session, same token');
        $this->assertSame($first, $_SESSION['csrf_token']);
    }

    public function testTokenSurvivesValidation(): void
    {
        $csrf = $this->newCsrf();
        $token = $csrf->getToken();
        $this->assertTrue($csrf->validateToken($token));
        $this->assertTrue($csrf->validateToken($token), 'not single-use: a second tab must still work');
    }

    public function testWrongMissingOrEmptyTokenIsRejected(): void
    {
        $csrf = $this->newCsrf();
        $csrf->getToken();
        $this->assertFalse($csrf->validateToken('nope'));
        $this->assertFalse($csrf->validateToken(null));
        $this->assertFalse($csrf->validateToken(''));
    }

    public function testSessionWithoutTokenFailsClosed(): void
    {
        $csrf = $this->newCsrf();
        $this->assertFalse($csrf->validateToken(''), 'empty must not equal empty');
        $this->assertFalse($csrf->validateToken('anything'));
    }

    public function testNoSessionThrowsInsteadOfSilentlySkipping(): void
    {
        unset($_SESSION);
        $csrf = $this->newCsrf();
        $this->expectException(\DomainException::class);
        $csrf->getToken();
    }

    public function testSubmittedTokenPrefersPostFieldThenHeader(): void
    {
        $_POST['csrf_token'] = 'from-post';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'from-header';
        $this->assertSame('from-post', $this->newCsrf()->submittedToken());

        $_POST = [];
        $this->assertSame('from-header', $this->newCsrf()->submittedToken());

        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $this->assertNull($this->newCsrf()->submittedToken());
    }

    public function testArrayInPostFieldIsTreatedAsAbsent(): void
    {
        $_POST['csrf_token'] = ['x'];
        $csrf = $this->newCsrf();
        $this->assertNull($csrf->submittedToken());
        $this->assertFalse($csrf->validateToken($csrf->submittedToken()));
    }

    public function testSafeMethodsNeedNoToken(): void
    {
        foreach (['GET', 'HEAD', 'OPTIONS', 'get'] as $method) {
            $_SERVER['REQUEST_METHOD'] = $method;
            $this->assertTrue($this->newCsrf()->validateRequest(), $method);
        }
    }

    public function testUnsafeMethodsNeedTheSessionToken(): void
    {
        $token = $this->newCsrf()->getToken();
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $_SERVER['REQUEST_METHOD'] = $method;
            $_POST = [];
            $this->assertFalse($this->newCsrf()->validateRequest(), "$method without token");

            $_POST['csrf_token'] = $token;
            $this->assertTrue($this->newCsrf()->validateRequest(), "$method with token");
        }
    }

    public function testFetchCallersCanUseTheHeader(): void
    {
        $token = $this->newCsrf()->getToken();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        $this->assertTrue($this->newCsrf()->validateRequest());
    }

    public function testFieldIsAHiddenInputCarryingTheToken(): void
    {
        $csrf = $this->newCsrf();
        $field = $csrf->field();
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString('value="' . $csrf->getToken() . '"', $field);
    }
}
