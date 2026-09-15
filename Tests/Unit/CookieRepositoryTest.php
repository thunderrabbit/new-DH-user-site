<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

/**
 * In-memory SQLite, same reasoning as LoginThrottleTest: hermetic, no server.
 */
class CookieRepositoryTest extends Unit
{
    private const DAY = 86400;
    private const IP = "\xCB\x00\x71\x07";   // 203.0.113.7 as inet_pton()
    private const UA = 'd41d8cd98f00b204e9800998ecf8427e';

    private \PDO $pdo;
    private \DateTimeImmutable $t0;

    protected function _before(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            "CREATE TABLE cookies (
                cookie_id INTEGER PRIMARY KEY AUTOINCREMENT,
                cookie TEXT NOT NULL UNIQUE,
                user_id INTEGER NOT NULL,
                created_at TEXT,
                last_access TEXT,
                expires_at TEXT NOT NULL,
                ip_address BLOB,
                user_agent_md5 TEXT
            )"
        );
        $this->t0 = new \DateTimeImmutable('2026-08-21 12:00:00');
    }

    private function at(int $seconds_after_t0): \Database\CookieRepository
    {
        return new \Database\CookieRepository($this->pdo, $this->t0->modify("+{$seconds_after_t0} seconds"));
    }

    private function rowCount(): int
    {
        return $this->countRows("SELECT COUNT(*) FROM cookies");
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

    public function testIssuedCookieIsFoundForTheSameIpAndBrowser(): void
    {
        $repo = $this->at(0);
        $repo->issue(7, 'hash-a', self::IP, self::UA, 30 * self::DAY);
        $this->assertSame(7, $repo->findUserId('hash-a', self::IP, self::UA));
    }

    public function testDifferentIpOrBrowserOrUnknownHashIsNobody(): void
    {
        $repo = $this->at(0);
        $repo->issue(7, 'hash-a', self::IP, self::UA, 30 * self::DAY);
        $this->assertSame(0, $repo->findUserId('hash-a', "\x0A\x00\x00\x01", self::UA), 'other IP');
        $this->assertSame(0, $repo->findUserId('hash-a', self::IP, str_repeat('0', 32)), 'other browser');
        $this->assertSame(0, $repo->findUserId('hash-zzz', self::IP, self::UA), 'unknown');
    }

    public function testCookieStopsWorkingWhenItExpires(): void
    {
        $this->at(0)->issue(7, 'hash-a', self::IP, self::UA, 30 * self::DAY);
        $this->assertSame(7, $this->at(30 * self::DAY - 1)->findUserId('hash-a', self::IP, self::UA));
        $this->assertSame(0, $this->at(30 * self::DAY)->findUserId('hash-a', self::IP, self::UA), 'at expiry');
        $this->assertSame(0, $this->at(31 * self::DAY)->findUserId('hash-a', self::IP, self::UA), 'after');
    }

    public function testIssuingPurgesExpiredRows(): void
    {
        $this->at(0)->issue(7, 'old', self::IP, self::UA, self::DAY);
        $this->at(0)->issue(7, 'fresh', self::IP, self::UA, 30 * self::DAY);
        $this->at(2 * self::DAY)->issue(8, 'newest', self::IP, self::UA, 30 * self::DAY);
        $this->assertSame(2, $this->rowCount(), 'old is gone, fresh and newest remain');
    }

    public function testRevokeRemovesOneCookie(): void
    {
        $repo = $this->at(0);
        $repo->issue(7, 'hash-a', self::IP, self::UA, self::DAY);
        $repo->issue(7, 'hash-b', self::IP, self::UA, self::DAY);
        $repo->revoke('hash-a');
        $this->assertSame(0, $repo->findUserId('hash-a', self::IP, self::UA));
        $this->assertSame(7, $repo->findUserId('hash-b', self::IP, self::UA));
    }

    public function testRevokeAllKeepsTheCookieInHand(): void
    {
        $repo = $this->at(0);
        $repo->issue(7, 'phone', self::IP, self::UA, self::DAY);
        $repo->issue(7, 'laptop', self::IP, self::UA, self::DAY);
        $repo->issue(7, 'tablet', self::IP, self::UA, self::DAY);
        $repo->issue(9, 'someone-else', self::IP, self::UA, self::DAY);

        $this->assertSame(2, $repo->revokeAllForUser(7, 'laptop'));
        $this->assertSame(7, $repo->findUserId('laptop', self::IP, self::UA), 'current browser stays in');
        $this->assertSame(0, $repo->findUserId('phone', self::IP, self::UA));
        $this->assertSame(9, $repo->findUserId('someone-else', self::IP, self::UA), 'other users untouched');
    }

    public function testRevokeAllWithoutAKeepSignsOutEverywhere(): void
    {
        $repo = $this->at(0);
        $repo->issue(7, 'phone', self::IP, self::UA, self::DAY);
        $repo->issue(7, 'laptop', self::IP, self::UA, self::DAY);
        $this->assertSame(2, $repo->revokeAllForUser(7));
        $this->assertSame(0, $this->rowCount());
    }
}
