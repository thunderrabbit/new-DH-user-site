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
        // For PHPStan, which will not cast mixed: COUNT(*) is always numeric,
        // so no test can reach the fallback.
        // @infection-ignore-all
        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * The rows dueCount() counts, oldest show_from first.
     *
     * @return list<Notification>
     */
    public function listDue(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT notification_id, message, link, show_from FROM notifications
                 WHERE show_from <= ? AND completed_at IS NULL
                 ORDER BY show_from, notification_id"
            );
            $stmt->execute([$this->now->format('Y-m-d H:i:s')]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            return [];
        }
        return self::fromRows($rows);
    }

    /**
     * Not yet due and not completed, soonest first: what a "Remind me" form
     * has waiting, so a typo can be seen before its day comes.
     *
     * @return list<Notification>
     */
    public function listUpcoming(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT notification_id, message, link, show_from FROM notifications
                 WHERE show_from > ? AND completed_at IS NULL
                 ORDER BY show_from, notification_id"
            );
            $stmt->execute([$this->now->format('Y-m-d H:i:s')]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            return [];
        }
        return self::fromRows($rows);
    }

    /**
     * @param array<mixed> $rows
     * @return list<Notification>
     */
    private static function fromRows(array $rows): array
    {
        $found = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = $row['notification_id'] ?? null;
            $message = $row['message'] ?? null;
            $link = $row['link'] ?? null;
            $show_from = $row['show_from'] ?? null;
            // For PHPStan, which will not cast mixed: these columns are NOT NULL,
            // so no row a test can build fails the guard.
            // @infection-ignore-all
            if (is_numeric($id) && is_string($message) && is_string($show_from)) {
                $found[] = new Notification((int) $id, $message, is_string($link) ? $link : null, $show_from);
            }
        }
        return $found;
    }
}
