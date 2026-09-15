<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

/**
 * Runs against an in-memory SQLite, not the site's MySQL: no server, no
 * credentials, no network, gone when the process exits. That keeps the
 * suite hermetic, which is what the "DB-free" rule in _bootstrap.php is for.
 * LoginThrottle keeps its SQL portable for exactly this reason.
 */
class LoginThrottleTest extends Unit
{
    private \PDO $pdo;
    private \DateTimeImmutable $t0;

    protected function _before(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            "CREATE TABLE login_attempts (
                login_attempt_id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                ip_address BLOB,
                attempted_at TEXT NOT NULL
            )"
        );
        $this->t0 = new \DateTimeImmutable('2026-08-21 12:00:00');
    }

    private function at(int $seconds_after_t0): \Auth\LoginThrottle
    {
        return new \Auth\LoginThrottle($this->pdo, $this->t0->modify("+{$seconds_after_t0} seconds"));
    }

    private function failTimes(
        \Auth\LoginThrottle $t,
        int $times,
        string $user = 'rob',
        string $ip = '203.0.113.7',
    ): void {
        for ($i = 0; $i < $times; $i++) {
            $t->recordFailure($user, $ip);
        }
    }

    private function countRows(string $sql): int
    {
        $stmt = $this->pdo->query($sql);
        $count = $stmt === false ? false : $stmt->fetchColumn();
        if (!is_int($count)) {
            $this->fail("COUNT query failed: $sql");
        }
        return $count;
    }

    public function testFreshUsernameIsNotThrottled(): void
    {
        $this->assertFalse($this->at(0)->isThrottled('rob', '203.0.113.7'));
    }

    public function testUsernameThrottlesAtTheLimit(): void
    {
        $t = $this->at(0);
        $this->failTimes($t, \Auth\LoginThrottle::MAX_FAILURES_PER_USERNAME - 1);
        $this->assertFalse($t->isThrottled('rob', '203.0.113.7'), 'one short of the limit');
        $this->failTimes($t, 1);
        $this->assertTrue($t->isThrottled('rob', '203.0.113.7'), 'at the limit');
    }

    public function testUsernameLimitSpansIpsAndCase(): void
    {
        $t = $this->at(0);
        foreach (['198.51.100.1', '198.51.100.2', '198.51.100.3', '198.51.100.4', '198.51.100.5'] as $ip) {
            $t->recordFailure('Rob', $ip);
        }
        $this->assertTrue($t->isThrottled('ROB', '198.51.100.99'), 'lookup is case-insensitive, so is the count');
    }

    public function testIpThrottlesAcrossManyUsernames(): void
    {
        $t = $this->at(0);
        for ($i = 0; $i < \Auth\LoginThrottle::MAX_FAILURES_PER_IP; $i++) {
            $t->recordFailure("guess$i", '203.0.113.7');
        }
        $this->assertTrue($t->isThrottled('someone-new', '203.0.113.7'));
        $this->assertFalse($t->isThrottled('someone-new', '203.0.113.8'), 'a different IP is unaffected');
    }

    public function testUnknownIpCountsOnlyAgainstTheUsername(): void
    {
        $t = $this->at(0);
        for ($i = 0; $i < \Auth\LoginThrottle::MAX_FAILURES_PER_IP; $i++) {
            $t->recordFailure("guess$i", '');
        }
        $this->assertFalse($t->isThrottled('someone-new', ''), 'no IP means no per-IP bucket to fill');
    }

    public function testFailuresExpireAfterTheWindow(): void
    {
        $this->failTimes($this->at(0), \Auth\LoginThrottle::MAX_FAILURES_PER_USERNAME);
        $this->assertTrue($this->at(\Auth\LoginThrottle::WINDOW_SECONDS - 1)->isThrottled('rob', '203.0.113.7'));
        $this->assertFalse($this->at(\Auth\LoginThrottle::WINDOW_SECONDS + 1)->isThrottled('rob', '203.0.113.7'));
    }

    public function testRecordingAFailurePurgesExpiredRows(): void
    {
        $this->failTimes($this->at(0), 3);
        $this->at(\Auth\LoginThrottle::WINDOW_SECONDS + 10)->recordFailure('other', '203.0.113.9');
        $this->assertSame(1, $this->countRows("SELECT COUNT(*) FROM login_attempts"));
    }

    public function testSuccessClearsThatUsernameOnly(): void
    {
        $t = $this->at(0);
        $this->failTimes($t, 3, 'rob');
        $this->failTimes($t, 3, 'alice');
        $t->clearFailures('ROB');
        $this->assertSame(
            3,
            $this->countRows("SELECT COUNT(*) FROM login_attempts"),
            "alice's rows survive"
        );
        $this->assertSame(
            0,
            $this->countRows("SELECT COUNT(*) FROM login_attempts WHERE username = 'rob'")
        );
    }

    public function testMissingTableMeansNoThrottleAndNoCrash(): void
    {
        $this->pdo->exec("DROP TABLE login_attempts");
        $t = $this->at(0);
        $t->recordFailure('rob', '203.0.113.7');
        $t->clearFailures('rob');
        $this->assertFalse($t->isThrottled('rob', '203.0.113.7'));
    }
}
