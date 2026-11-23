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

    public function testReadsNowInUtc(): void
    {
        $this->createTable();
        $this->insert('2027-04-01 00:00:00');
        // 2027-04-01 09:00 in Tokyo is 00:00 UTC.
        $tokyo = new \DateTimeImmutable('2027-04-01 09:00:00', new \DateTimeZone('Asia/Tokyo'));

        $this->assertSame(1, new \Admin\Notifications($this->pdo, $tokyo)->dueCount());
        $this->assertSame(0, new \Admin\Notifications($this->pdo, $tokyo->modify('-1 second'))->dueCount());
    }

    public function testMissingTableCountsNothing(): void
    {
        $this->assertSame(0, $this->notifications()->dueCount());
    }

    /**
     * @param list<\Admin\Notification> $list
     * @return list<string>
     */
    private function messages(array $list): array
    {
        return array_map(static fn(\Admin\Notification $n): string => $n->message, $list);
    }

    private function insertRow(string $message, ?string $link, string $show_from, ?string $completed_at = null): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications (message, link, show_from, completed_at, created_at)
             VALUES (?, ?, ?, ?, '2026-11-16 00:00:00')"
        );
        $stmt->execute([$message, $link, $show_from, $completed_at]);
    }

    public function testListDueIsWhatDueCountCountsOldestFirst(): void
    {
        $this->createTable();
        $this->insertRow('later', '/b/', '2027-03-31 00:00:00');
        $this->insertRow('earlier', null, '2027-03-01 00:00:00');
        $this->insertRow('done', null, '2027-03-02 00:00:00', '2027-03-03 00:00:00');
        $this->insertRow('future', null, '2027-04-02 00:00:00');

        $due = $this->notifications()->listDue();

        $this->assertCount($this->notifications()->dueCount(), $due);
        $this->assertSame(['earlier', 'later'], $this->messages($due));
        $this->assertNull($due[0]->link);
        $this->assertSame('/b/', $due[1]->link);
        $this->assertSame('2027-03-31 00:00:00', $due[1]->show_from);
        $this->assertGreaterThan(0, $due[0]->notification_id);
    }

    public function testListUpcomingIsOnlyWhatIsNotYetDueSoonestFirst(): void
    {
        $this->createTable();
        $this->insertRow('now', null, '2027-04-01 00:00:00');
        $this->insertRow('next year', null, '2028-04-01 00:00:00');
        $this->insertRow('tomorrow', null, '2027-04-02 00:00:00');
        $this->insertRow('done', null, '2027-05-01 00:00:00', '2027-03-03 00:00:00');

        $upcoming = $this->notifications()->listUpcoming();

        $this->assertSame(['tomorrow', 'next year'], $this->messages($upcoming));
    }

    public function testListsAreEmptyWithoutTheTable(): void
    {
        $this->assertSame([], $this->notifications()->listDue());
        $this->assertSame([], $this->notifications()->listUpcoming());
    }

    public function testCompleteMarksADueRowOnceAndNamesTheAdmin(): void
    {
        $this->createTable();
        $this->insertRow('due', null, '2027-03-31 00:00:00');
        $n = $this->notifications();

        $this->assertTrue($n->complete(1, 7));
        $this->assertFalse($n->complete(1, 8));
        $this->assertSame(0, $n->dueCount());
        $stmt = $this->pdo->query("SELECT completed_at, completed_by FROM notifications");
        $this->assertNotFalse($stmt);
        $this->assertSame(
            ['completed_at' => '2027-04-01 00:00:00', 'completed_by' => 7],
            $stmt->fetch(\PDO::FETCH_ASSOC),
        );
    }

    public function testCompleteRefusesARowNotYetDueAndAnUnknownId(): void
    {
        $this->createTable();
        $this->insertRow('future', null, '2027-04-02 00:00:00');
        $n = $this->notifications();

        $this->assertFalse($n->complete(1, 7));
        $this->assertFalse($n->complete(99, 7));
        $this->assertCount(1, $n->listUpcoming());
    }

    public function testCompleteWithoutTheTableIsFalse(): void
    {
        $this->assertFalse($this->notifications()->complete(1, 7));
    }

    public function testRemindStoresFromMidnightUtcWithAnOptionalLink(): void
    {
        $this->createTable();
        $n = $this->notifications();

        $this->assertSame(\Admin\RemindResult::Added, $n->remind('2027-04-02', '  Look again  ', ' /tqiq/ '));
        $this->assertSame(\Admin\RemindResult::Added, $n->remind('2027-03-01', 'No link', ''));

        $upcoming = $n->listUpcoming();
        $this->assertCount(1, $upcoming);
        $this->assertSame('Look again', $upcoming[0]->message);
        $this->assertSame('/tqiq/', $upcoming[0]->link);
        $this->assertSame('2027-04-02 00:00:00', $upcoming[0]->show_from);
        $due = $n->listDue();
        $this->assertCount(1, $due);
        $this->assertNull($due[0]->link);
        $this->assertSame('2027-04-01 00:00:00', $this->scalar("SELECT created_at FROM notifications LIMIT 1"));
    }

    /** One value, as a string: SQLite hands every column back as one here. */
    private function scalar(string $sql): string
    {
        $stmt = $this->pdo->query($sql);
        $value = $stmt === false ? false : $stmt->fetchColumn();
        return is_scalar($value) ? (string) $value : '';
    }

    public function testRemindRefusesABadDate(): void
    {
        $this->createTable();
        $n = $this->notifications();

        $bad_dates = ['', 'soon', '2027-02-30', '2027-4-1', '2027-04-01 00:00', ' 2027-04-01', null, ['2027-04-01']];
        foreach ($bad_dates as $bad) {
            $this->assertSame(\Admin\RemindResult::BadDate, $n->remind($bad, 'm', ''));
        }
        $this->assertSame('0', $this->scalar("SELECT COUNT(*) FROM notifications"));
    }

    public function testRemindRefusesAnEmptyOrTooLongMessage(): void
    {
        $this->createTable();
        $n = $this->notifications();
        $max = \Admin\Notifications::MESSAGE_MAX;

        $this->assertSame(\Admin\RemindResult::BadMessage, $n->remind('2027-04-02', "  \n ", ''));
        $this->assertSame(\Admin\RemindResult::BadMessage, $n->remind('2027-04-02', null, ''));
        $this->assertSame(\Admin\RemindResult::BadMessage, $n->remind('2027-04-02', str_repeat('a', $max + 1), ''));
        $this->assertSame(\Admin\RemindResult::Added, $n->remind('2027-04-02', str_repeat('a', $max), ''));
        $this->assertSame(\Admin\RemindResult::Added, $n->remind('2027-04-02', str_repeat('é', $max), ''));
    }

    public function testRemindRefusesALinkThatIsNotAPathOnThisSite(): void
    {
        $this->createTable();
        $n = $this->notifications();

        foreach (['https://example.com/', '//example.com/', 'tqiq/', '/a b'] as $bad) {
            $this->assertSame(\Admin\RemindResult::BadLink, $n->remind('2027-04-02', 'm', $bad));
        }
        $this->assertSame('0', $this->scalar("SELECT COUNT(*) FROM notifications"));
    }

    public function testRemindWithoutTheTableFails(): void
    {
        $this->assertSame(\Admin\RemindResult::Failed, $this->notifications()->remind('2027-04-02', 'm', ''));
    }

    public function testEachResultSaysSomething(): void
    {
        foreach (\Admin\RemindResult::cases() as $case) {
            $this->assertNotSame('', $case->says());
        }
        $this->assertStringContainsString('1000', \Admin\RemindResult::BadMessage->says());
    }
}
