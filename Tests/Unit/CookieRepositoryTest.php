<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

/**
 * In-memory SQLite, same reasoning as LoginThrottleTest: hermetic, no server.
 */
class CookieRepositoryTest extends Unit
{
    private const int DAY = 86400;
    private const string IP = "\xCB\x00\x71\x07";   // 203.0.113.7 as inet_pton()
    private const string UA = 'd41d8cd98f00b204e9800998ecf8427e';

    private const string MAC = 'Mozilla/5.0 (Macintosh) Safari/605';
    private const string PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Safari/604.1';

    private \PDO $pdo;
    private \DateTimeImmutable $t0;

    protected function _before(): void
    {
        $this->pdo = $this->database(with_user_agent_column: true);
        $this->t0 = new \DateTimeImmutable('2026-08-21 12:00:00');
    }

    /**
     * The cookies table, with or without 02_devices/alter_cookies_user_agent.sql applied.
     */
    private function database(bool $with_user_agent_column): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->createCookies($pdo, $with_user_agent_column);
        return $pdo;
    }

    private function createCookies(\PDO $pdo, bool $with_user_agent_column): void
    {
        $pdo->exec(
            "CREATE TABLE cookies (
                cookie_id INTEGER PRIMARY KEY AUTOINCREMENT,
                cookie TEXT NOT NULL UNIQUE,
                user_id INTEGER NOT NULL,
                created_at TEXT,
                last_access TEXT,
                expires_at TEXT NOT NULL,
                ip_address BLOB,
                user_agent_md5 TEXT"
            . ($with_user_agent_column ? ", user_agent TEXT" : "")
            . ")"
        );
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

    public function testIssuedCookieIsFoundForTheSameBrowser(): void
    {
        $repo = $this->at(0);
        $repo->issue(7, 'hash-a', self::IP, self::UA, 30 * self::DAY);
        $this->assertSame(7, $repo->findUserId('hash-a', self::UA));
    }

    public function testCookieKeepsWorkingFromAnotherIp(): void
    {
        $repo = $this->at(0);
        $repo->issue(7, 'hash-a', "\x0A\x00\x00\x01", self::UA, 30 * self::DAY);
        $this->assertSame(7, $repo->findUserId('hash-a', self::UA), 'the lookup never sees the IP');
    }

    public function testDifferentBrowserOrUnknownHashIsNobody(): void
    {
        $repo = $this->at(0);
        $repo->issue(7, 'hash-a', self::IP, self::UA, 30 * self::DAY);
        $this->assertSame(0, $repo->findUserId('hash-a', str_repeat('0', 32)), 'other browser');
        $this->assertSame(0, $repo->findUserId('hash-zzz', self::UA), 'unknown');
    }

    public function testCookieStopsWorkingWhenItExpires(): void
    {
        $this->at(0)->issue(7, 'hash-a', self::IP, self::UA, 30 * self::DAY);
        $this->assertSame(7, $this->at(30 * self::DAY - 1)->findUserId('hash-a', self::UA));
        $this->assertSame(0, $this->at(30 * self::DAY)->findUserId('hash-a', self::UA), 'at expiry');
        $this->assertSame(0, $this->at(31 * self::DAY)->findUserId('hash-a', self::UA), 'after');
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
        $this->assertSame(0, $repo->findUserId('hash-a', self::UA));
        $this->assertSame(7, $repo->findUserId('hash-b', self::UA));
    }

    public function testRevokeAllKeepsTheCookieInHand(): void
    {
        $repo = $this->at(0);
        $repo->issue(7, 'phone', self::IP, self::UA, self::DAY);
        $repo->issue(7, 'laptop', self::IP, self::UA, self::DAY);
        $repo->issue(7, 'tablet', self::IP, self::UA, self::DAY);
        $repo->issue(9, 'someone-else', self::IP, self::UA, self::DAY);

        $this->assertSame(2, $repo->revokeAllForUser(7, 'laptop'));
        $this->assertSame(7, $repo->findUserId('laptop', self::UA), 'current browser stays in');
        $this->assertSame(0, $repo->findUserId('phone', self::UA));
        $this->assertSame(9, $repo->findUserId('someone-else', self::UA), 'other users untouched');
    }

    public function testRevokeAllWithoutAKeepSignsOutEverywhere(): void
    {
        $repo = $this->at(0);
        $repo->issue(7, 'phone', self::IP, self::UA, self::DAY);
        $repo->issue(7, 'laptop', self::IP, self::UA, self::DAY);
        $this->assertSame(2, $repo->revokeAllForUser(7));
        $this->assertSame(0, $this->rowCount());
    }

    public function testIssueRecordsTheReadableUserAgent(): void
    {
        $this->at(0)->issue(7, 'hash-a', self::IP, self::UA, self::DAY, self::PHONE);
        $stmt = $this->pdo->query("SELECT user_agent FROM cookies");
        $this->assertSame(self::PHONE, $stmt === false ? false : $stmt->fetchColumn());
    }

    public function testIssueCutsAnOverlongUserAgentToTheColumnWidth(): void
    {
        $this->at(0)->issue(7, 'hash-a', self::IP, self::UA, self::DAY, str_repeat('x', 300));
        $stmt = $this->pdo->query("SELECT user_agent FROM cookies");
        $stored = $stmt === false ? false : $stmt->fetchColumn();
        $this->assertSame(255, is_string($stored) ? strlen($stored) : -1);
    }

    public function testLoginStillWorksBeforeTheUserAgentColumnExists(): void
    {
        $repo = new \Database\CookieRepository($this->database(with_user_agent_column: false), $this->t0);
        $repo->issue(7, 'hash-a', self::IP, self::UA, self::DAY, self::PHONE);
        $this->assertSame(7, $repo->findUserId('hash-a', self::UA));
    }

    /**
     * @param list<\Auth\Device> $devices
     * @return list<string>
     */
    private function labels(array $devices): array
    {
        return array_map(fn(\Auth\Device $d): string => $d->label(), $devices);
    }

    public function testListShowsOnlyThisUsersLiveDevicesMostRecentlyUsedFirst(): void
    {
        $this->at(0)->issue(7, 'phone', self::IP, self::UA, 30 * self::DAY, self::PHONE);
        $this->at(0)->issue(7, 'expiring', self::IP, self::UA, self::DAY, 'Firefox/128.0');
        $this->at(60)->issue(7, 'laptop', self::IP, self::UA, 30 * self::DAY, self::MAC);
        $this->at(0)->issue(9, 'someone-else', self::IP, self::UA, 30 * self::DAY, self::PHONE);
        $this->at(2 * self::DAY)->touch('phone');

        $devices = $this->at(2 * self::DAY)->listForUser(7, null);

        $this->assertSame(['iPhone · Safari', 'Mac · Safari'], $this->labels($devices));
        $this->assertSame('203.0.113.7', $devices[0]->ip_address);
        $this->assertSame('2026-08-23 12:00:00', $devices[0]->last_access);
        $this->assertSame('2026-09-20 12:00:00', $devices[0]->expires_at);
    }

    public function testListMarksTheCookieInHand(): void
    {
        $repo = $this->at(0);
        $repo->issue(7, 'phone', self::IP, self::UA, self::DAY, self::PHONE);
        $repo->issue(7, 'laptop', self::IP, self::UA, self::DAY, self::MAC);

        $current = [];
        foreach ($repo->listForUser(7, 'laptop') as $device) {
            $current[$device->label()] = $device->is_current;
        }
        $this->assertSame(['Mac · Safari' => true, 'iPhone · Safari' => false], $current);
    }

    public function testListCarriesWhenTheDeviceSignedIn(): void
    {
        $this->at(0)->issue(7, 'phone', self::IP, self::UA, self::DAY, self::PHONE);
        $this->pdo->exec("UPDATE cookies SET created_at = '2026-08-20 09:30:00'");

        $this->assertSame('2026-08-20 09:30:00', $this->at(0)->listForUser(7, null)[0]->created_at);
    }

    public function testNumbersArrivingAsStringsAreStillNumbers(): void
    {
        // Drivers differ: MySQL without native prepares hands integers back
        // as numeric strings. Under strict_types a string where an int is
        // declared is a TypeError, on the login path.
        $this->pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);
        $repo = $this->at(0);
        $repo->issue(7, 'phone', self::IP, self::UA, self::DAY, self::PHONE);

        $this->assertSame(7, $repo->findUserId('phone', self::UA));
        $device = $repo->listForUser(7, 'phone')[0];
        $this->assertSame(1, $device->cookie_id);
        $this->assertTrue($device->is_current);
    }

    public function testIssueFallsBackOnMysqlsUnknownColumnError(): void
    {
        // Production is MySQL, which reports the missing column by SQLSTATE
        // and in its own words; SQLite's wording is covered above.
        $pdo = new FailingOncePdo(FailingOncePdo::sqlState(
            '42S22',
            "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'user_agent' in 'field list'"
        ));
        $this->createCookies($pdo, with_user_agent_column: false);
        $repo = new \Database\CookieRepository($pdo, $this->t0);

        $repo->issue(7, 'hash-a', self::IP, self::UA, self::DAY, self::PHONE);

        $this->assertSame(7, $repo->findUserId('hash-a', self::UA));
    }

    public function testIssueDoesNotRetryAfterAnyOtherDatabaseError(): void
    {
        // Only the missing column earns the fallback INSERT. Anything else
        // must surface, not be papered over by a second attempt that works.
        $pdo = new FailingOncePdo(new \PDOException('SQLSTATE[HY000]: General error: 10 disk I/O error'));
        $this->createCookies($pdo, with_user_agent_column: true);
        $repo = new \Database\CookieRepository($pdo, $this->t0);

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('disk I/O error');
        $repo->issue(7, 'hash-a', self::IP, self::UA, self::DAY, self::PHONE);
    }

    public function testListDoesNotRetryAfterAnyOtherDatabaseError(): void
    {
        $pdo = new FailingOncePdo(new \PDOException('SQLSTATE[HY000]: General error: 10 disk I/O error'));
        $this->createCookies($pdo, with_user_agent_column: true);

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('disk I/O error');
        new \Database\CookieRepository($pdo, $this->t0)->listForUser(7, null);
    }

    public function testListBeforeTheUserAgentColumnExistsShowsUnknownDevices(): void
    {
        $repo = new \Database\CookieRepository($this->database(with_user_agent_column: false), $this->t0);
        $repo->issue(7, 'hash-a', self::IP, self::UA, self::DAY, self::PHONE);
        $this->assertSame(['Unknown device'], $this->labels($repo->listForUser(7, null)));
    }

    public function testRevokeDeviceOnlyTakesTheOwnersCookie(): void
    {
        $repo = $this->at(0);
        $repo->issue(7, 'phone', self::IP, self::UA, self::DAY, self::PHONE);
        $phone_id = $repo->listForUser(7, null)[0]->cookie_id;

        $this->assertFalse($repo->revokeDevice(9, $phone_id), 'not user 9\'s device');
        $this->assertSame(7, $repo->findUserId('phone', self::UA));

        $this->assertTrue($repo->revokeDevice(7, $phone_id));
        $this->assertSame(0, $repo->findUserId('phone', self::UA));
    }

    public function testTouchRecordsUseAtMostHourly(): void
    {
        $this->at(0)->issue(7, 'phone', self::IP, self::UA, 30 * self::DAY, self::PHONE);

        $this->at(59 * 60)->touch('phone');
        $this->assertSame('2026-08-21 12:00:00', $this->at(0)->listForUser(7, null)[0]->last_access, 'too soon');

        $this->at(61 * 60)->touch('phone');
        $this->assertSame('2026-08-21 13:01:00', $this->at(0)->listForUser(7, null)[0]->last_access);
    }
}
