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
    /** The longest message the "Remind me" form takes, in characters. */
    public const int MESSAGE_MAX = 1000;

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

    /**
     * Mark a due notification completed by this admin. True only if this call
     * did it: a second click, an unknown id, a row not yet due and a missing
     * table are all false.
     */
    public function complete(int $notification_id, int $user_id): bool
    {
        $now = $this->now->format('Y-m-d H:i:s');
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE notifications SET completed_at = ?, completed_by = ?
                 WHERE notification_id = ? AND completed_at IS NULL AND show_from <= ?"
            );
            $stmt->execute([$now, $user_id, $notification_id, $now]);
        } catch (\PDOException) {
            return false;
        }
        return $stmt->rowCount() === 1;
    }

    /**
     * Add a notification from the "Remind me" form: shown from 00:00 UTC of
     * the given day (a past day is due at once). The link is optional, and
     * kept only if it is a path on this site, the same rule the page draws
     * links by, so nothing is stored that would never be shown.
     */
    public function remind(mixed $date, mixed $message, mixed $link): RemindResult
    {
        $utc = new \DateTimeZone('UTC');
        $day = is_string($date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $utc) : false;
        if ($day === false || $day->format('Y-m-d') !== $date) {
            return RemindResult::BadDate;
        }
        $message = is_string($message) ? trim($message) : '';
        if ($message === '' || mb_strlen($message) > self::MESSAGE_MAX) {
            return RemindResult::BadMessage;
        }
        $link = is_string($link) ? trim($link) : '';
        if ($link !== '' && !LocalPath::isLocal($link)) {
            return RemindResult::BadLink;
        }
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO notifications (message, link, show_from, created_at) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([
                $message,
                $link === '' ? null : $link,
                $day->format('Y-m-d H:i:s'),
                $this->now->format('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException) {
            return RemindResult::Failed;
        }
        return RemindResult::Added;
    }
}
