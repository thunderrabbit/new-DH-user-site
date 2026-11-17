<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

/**
 * Admin\Notifications over an in-memory SQLite (no server, gone with the
 * process), as LoginThrottleTest does.
 */
class AdminNotificationsTest extends Unit
{
    private \PDO $pdo;

    protected function _before(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    private function createTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE notifications (
                notification_id INTEGER PRIMARY KEY AUTOINCREMENT,
                message TEXT NOT NULL,
                link TEXT,
                show_from TEXT NOT NULL,
                completed_at TEXT,
                completed_by INTEGER,
                created_at TEXT NOT NULL
            )"
        );
    }

    private function insert(string $show_from, ?string $completed_at = null): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications (message, show_from, completed_at, created_at)
             VALUES ('m', ?, ?, '2026-11-16 00:00:00')"
        );
        $stmt->execute([$show_from, $completed_at]);
    }

    /** One value, as a string: SQLite hands every column back as one here. */
    private function scalar(string $sql): string
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $value = $stmt->fetchColumn();
        return is_scalar($value) ? (string) $value : '';
    }

    private function notifications(): \Admin\Notifications
    {
        $now = new \DateTimeImmutable('2027-04-01 00:00:00', new \DateTimeZone('UTC'));
        return new \Admin\Notifications($this->pdo, $now);
    }

    public function testCountsDueUncompletedRows(): void
    {
        $this->createTable();
        $this->insert('2027-03-31 23:59:59');
        $this->insert('2027-04-01 00:00:00');
        $this->insert('2027-04-01 00:00:01');
        $this->insert('2027-03-01 00:00:00', '2027-03-02 00:00:00');

        $this->assertSame(2, $this->notifications()->dueCount());
    }

    public function testMissingTableCountsZero(): void
    {
        $this->assertSame(0, $this->notifications()->dueCount());
    }

    public function testListDueMatchesDueCount(): void
    {
        $this->createTable();
        $this->insert('2027-04-01 00:00:00');
        $this->insert('2027-03-31 23:59:59');
        $this->insert('2027-04-01 00:00:01');
        $this->insert('2027-03-01 00:00:00', '2027-03-02 00:00:00');

        $due = $this->notifications()->listDue();

        $this->assertSame(
            ['2027-03-31 23:59:59', '2027-04-01 00:00:00'],
            array_map(static fn(\Admin\Notification $n): string => $n->show_from, $due),
        );
        $this->assertSame(2, $due[0]->notification_id);
        $this->assertSame('m', $due[0]->message);
        $this->assertNull($due[0]->link);
    }

    public function testListDueCarriesTheLink(): void
    {
        $this->createTable();
        $this->pdo->exec(
            "INSERT INTO notifications (message, link, show_from, created_at)
             VALUES ('Revisit', '/profile/', '2027-04-01 00:00:00', '2026-11-16 00:00:00')"
        );

        $this->assertSame('/profile/', $this->notifications()->listDue()[0]->link);
    }

    public function testMissingTableListsNothing(): void
    {
        $this->assertSame([], $this->notifications()->listDue());
    }

    public function testCompleteRecordsWhoAndWhen(): void
    {
        $this->createTable();
        $this->insert('2027-03-31 23:59:59');
        $this->insert('2027-04-01 00:00:00');

        $this->assertTrue($this->notifications()->complete(1, 7));

        $row = $this->pdo->query(
            "SELECT completed_at, completed_by FROM notifications WHERE notification_id = 1"
        );
        $this->assertNotFalse($row);
        $this->assertEquals(
            ['completed_at' => '2027-04-01 00:00:00', 'completed_by' => 7],
            $row->fetch(\PDO::FETCH_ASSOC),
        );
        $this->assertSame(1, $this->notifications()->dueCount());
    }

    public function testSecondCompleteReturnsFalse(): void
    {
        $this->createTable();
        $this->insert('2027-03-31 23:59:59');

        $this->assertTrue($this->notifications()->complete(1, 7));
        $this->assertFalse($this->notifications()->complete(1, 8));

        $by = $this->pdo->query("SELECT completed_by FROM notifications WHERE notification_id = 1");
        $this->assertNotFalse($by);
        $this->assertEquals(7, $by->fetchColumn());
    }

    public function testCompleteUnknownIdReturnsFalse(): void
    {
        $this->createTable();

        $this->assertFalse($this->notifications()->complete(99, 7));
    }

    public function testCompleteWithoutTheTableReturnsFalse(): void
    {
        $this->assertFalse($this->notifications()->complete(1, 7));
    }

    public function testANotificationNotYetDueCannotBeCompleted(): void
    {
        $this->createTable();
        $this->insert('2027-04-01 00:00:01');

        $this->assertFalse($this->notifications()->complete(1, 7));
    }

    public function testListUpcomingIsWhatIsNeitherDueNorCompleted(): void
    {
        $this->createTable();
        $this->insert('2027-06-01 00:00:00');
        $this->insert('2027-04-01 00:00:00');
        $this->insert('2027-04-01 00:00:01');
        $this->insert('2027-05-01 00:00:00', '2027-03-02 00:00:00');

        $this->assertSame(
            ['2027-04-01 00:00:01', '2027-06-01 00:00:00'],
            array_map(
                static fn(\Admin\Notification $n): string => $n->show_from,
                $this->notifications()->listUpcoming(),
            ),
        );
    }

    public function testMissingTableListsNothingUpcoming(): void
    {
        $this->assertSame([], $this->notifications()->listUpcoming());
    }

    public function testRemindAddsAnUpcomingRowFromMidnightUtc(): void
    {
        $this->createTable();

        $result = $this->notifications()->remind('2027-05-02', "  Look again \n", ' /profile/ ');

        $this->assertSame(\Admin\RemindResult::Added, $result);
        $upcoming = $this->notifications()->listUpcoming();
        $this->assertCount(1, $upcoming);
        $this->assertSame('Look again', $upcoming[0]->message);
        $this->assertSame('/profile/', $upcoming[0]->link);
        $this->assertSame('2027-05-02 00:00:00', $upcoming[0]->show_from);
        $this->assertSame(
            '2027-04-01 00:00:00',
            $this->scalar("SELECT created_at FROM notifications"),
        );
    }

    public function testRemindWithNoLinkStoresNull(): void
    {
        $this->createTable();

        $this->assertSame(\Admin\RemindResult::Added, $this->notifications()->remind('2027-05-02', 'm', ''));
        $this->assertNull($this->notifications()->listUpcoming()[0]->link);
    }

    public function testRemindForAPastDayIsDueAtOnce(): void
    {
        $this->createTable();

        $this->assertSame(\Admin\RemindResult::Added, $this->notifications()->remind('2027-04-01', 'm', null));
        $this->assertSame(1, $this->notifications()->dueCount());
    }

    public function testRemindRefusesADateThatIsNotOne(): void
    {
        $this->createTable();
        $n = $this->notifications();

        foreach (['', '2027-02-30', '2027-5-2', '02/05/2027', '2027-05-02 10:00:00', ['2027-05-02'], null] as $bad) {
            $this->assertSame(\Admin\RemindResult::BadDate, $n->remind($bad, 'm', ''));
        }
        $this->assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM notifications"));
    }

    public function testRemindRefusesAnEmptyOrOverlongMessage(): void
    {
        $this->createTable();
        $n = $this->notifications();

        $this->assertSame(\Admin\RemindResult::BadMessage, $n->remind('2027-05-02', "  \n ", ''));
        $this->assertSame(\Admin\RemindResult::BadMessage, $n->remind('2027-05-02', ['m'], ''));
        $this->assertSame(\Admin\RemindResult::BadMessage, $n->remind('2027-05-02', str_repeat('é', 1001), ''));
        $this->assertSame(\Admin\RemindResult::Added, $n->remind('2027-05-02', str_repeat('é', 1000), ''));
    }

    public function testRemindRefusesALinkThatIsNotAPathOnThisSite(): void
    {
        $this->createTable();
        $n = $this->notifications();

        $not_paths = [
            'https://evil.example/',
            '//evil.example',
            'javascript:alert(1)',
            'profile/',
            '/' . str_repeat('a', 255),
        ];
        foreach ($not_paths as $bad) {
            $this->assertSame(\Admin\RemindResult::BadLink, $n->remind('2027-05-02', 'm', $bad));
        }
        $this->assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM notifications"));
    }

    public function testRemindWithoutTheTableFails(): void
    {
        $this->assertSame(\Admin\RemindResult::Failed, $this->notifications()->remind('2027-05-02', 'm', ''));
    }

    public function testOnlyAPathOnThisSiteIsASitePath(): void
    {
        foreach (['/', '/profile/', '/admin/?done=completed&x=1'] as $path) {
            $this->assertTrue(\Admin\Notifications::isSitePath($path), $path);
        }
        foreach (
            [
                '', 'profile/', '//evil.example/', '/\\evil.example', 'https://evil.example/',
                '/x<script>', "/x\r\nSet-Cookie: a=b", '/x y', 'javascript:alert(1)', "/x\n",
            ] as $not
        ) {
            $this->assertFalse(\Admin\Notifications::isSitePath($not), var_export($not, true));
        }
    }
}
