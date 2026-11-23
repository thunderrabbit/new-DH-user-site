<?php

declare(strict_types=1);

namespace Admin;

/**
 * The notifications table (mg #851): things an admin must act on, other than
 * pending migrations, counted beside the menu's Admin link once due.
 *
 * Every time is UTC, written from PHP: the database clock is not UTC. A site
 * whose table is missing, or any database error, counts nothing and never
 * stops a page.
 */
final readonly class Notifications
{
    private \DateTimeImmutable $now;

    /** @param ?\DateTimeImmutable $now null means now; read in UTC either way */
    public function __construct(
        private \PDO $pdo,
        ?\DateTimeImmutable $now = null,
    ) {
        $this->now = ($now ?? new \DateTimeImmutable())->setTimezone(new \DateTimeZone('UTC'));
    }

    /** Due (show_from at or before now) and not yet completed. */
    public function dueCount(): int
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM notifications WHERE show_from <= ? AND completed_at IS NULL"
            );
            $stmt->execute([$this->now->format('Y-m-d H:i:s')]);
            $count = $stmt->fetchColumn();
        } catch (\PDOException) {
            return 0;
        }
        return is_numeric($count) ? (int) $count : 0;
    }
}
