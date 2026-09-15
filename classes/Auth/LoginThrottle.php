<?php

declare(strict_types=1);

namespace Auth;

/**
 * Counts failed password logins and says when to stop checking passwords.
 *
 * Two independent limits inside one sliding window:
 *   - per username, so one account cannot be brute-forced from many IPs
 *   - per IP, so one machine cannot spray many usernames
 * The per-username limit is the one an attacker can abuse to lock a real user
 * out, which is why it is a short window and not a permanent lock.
 *
 * The SQL is deliberately portable (timestamps computed in PHP, no NOW() or
 * DATE_SUB) so the Unit suite can run it against an in-memory SQLite.
 *
 * A missing `login_attempts` table is treated as "no throttle": the table was
 * added after sites were already deployed, and bricking login on every one of
 * them until an admin applies a migration would be worse than a window with
 * no throttle. The pending migration is visible in /admin/migrate_tables.php.
 */
class LoginThrottle
{
    public const MAX_FAILURES_PER_USERNAME = 5;
    public const MAX_FAILURES_PER_IP = 20;
    public const WINDOW_SECONDS = 15 * 60;

    /** MySQL: table does not exist. */
    private const SQLSTATE_NO_TABLE = '42S02';

    private \DateTimeImmutable $now;

    public function __construct(
        private \PDO $di_pdo,
        ?\DateTimeImmutable $now = null,
    ) {
        $this->now = $now ?? new \DateTimeImmutable();
    }

    public function isThrottled(string $username, string $ip_address): bool
    {
        $since = $this->windowStart();

        $by_username = $this->countSince(
            "SELECT COUNT(*) FROM `login_attempts` WHERE `username` = ? AND `attempted_at` > ?",
            [self::normalizeUsername($username), $since]
        );
        if ($by_username >= self::MAX_FAILURES_PER_USERNAME) {
            return true;
        }

        $ip_bin = IPBin::ipToBinary($ip_address);
        if ($ip_bin === '') {
            return false;
        }
        $by_ip = $this->countSince(
            "SELECT COUNT(*) FROM `login_attempts` WHERE `ip_address` = ? AND `attempted_at` > ?",
            [$ip_bin, $since]
        );
        return $by_ip >= self::MAX_FAILURES_PER_IP;
    }

    public function recordFailure(string $username, string $ip_address): void
    {
        $ip_bin = IPBin::ipToBinary($ip_address);
        $this->run(
            "INSERT INTO `login_attempts` (`username`, `ip_address`, `attempted_at`) VALUES (?, ?, ?)",
            [self::normalizeUsername($username), $ip_bin === '' ? null : $ip_bin, $this->format($this->now)]
        );
        // Rows older than the window never count again. Purging on the failure
        // path keeps the table bounded without a cron entry.
        $this->run("DELETE FROM `login_attempts` WHERE `attempted_at` <= ?", [$this->windowStart()]);
    }

    /**
     * A correct password resets that username's count. Other usernames tried
     * from the same IP keep counting against the IP.
     */
    public function clearFailures(string $username): void
    {
        $this->run(
            "DELETE FROM `login_attempts` WHERE `username` = ?",
            [self::normalizeUsername($username)]
        );
    }

    private static function normalizeUsername(string $username): string
    {
        // Login lookup is LOWER(username) = LOWER(?), so the count must be too.
        return mb_substr(mb_strtolower(trim($username)), 0, 255);
    }

    private function windowStart(): string
    {
        return $this->format($this->now->modify('-' . self::WINDOW_SECONDS . ' seconds'));
    }

    private function format(\DateTimeImmutable $when): string
    {
        return $when->format('Y-m-d H:i:s');
    }

    /**
     * @param list<string|int|null> $params
     */
    private function countSince(string $sql, array $params): int
    {
        try {
            $stmt = $this->di_pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            if ($this->isMissingTable($e)) {
                return 0;
            }
            throw $e;
        }
    }

    /**
     * @param list<string|int|null> $params
     */
    private function run(string $sql, array $params): void
    {
        try {
            $stmt = $this->di_pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            if (!$this->isMissingTable($e)) {
                throw $e;
            }
        }
    }

    private function isMissingTable(\PDOException $e): bool
    {
        // MySQL reports SQLSTATE 42S02; SQLite has no SQLSTATE for it and says
        // "no such table" with HY000, which the Unit suite relies on.
        return $e->getCode() === self::SQLSTATE_NO_TABLE
            || str_contains($e->getMessage(), 'no such table');
    }
}
