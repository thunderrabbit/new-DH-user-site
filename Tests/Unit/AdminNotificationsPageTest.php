<?php

declare(strict_types=1);

namespace Tests\Unit;

use Admin\Notification;
use Codeception\Test\Unit;

/**
 * /admin/notifications/ (mg #858): whatever a migration wrote is escaped,
 * and a link is drawn only when it is a path on this site.
 */
class AdminNotificationsPageTest extends Unit
{
    protected function _after(): void
    {
        unset($_SESSION);
    }

    private const array EMPTY_FORM = ['date' => '', 'message' => '', 'link' => ''];

    /**
     * @param list<Notification> $due
     * @param list<Notification> $upcoming
     * @param array{date: string, message: string, link: string} $form
     */
    private function page(
        array $due,
        string $message = '',
        array $upcoming = [],
        array $form = self::EMPTY_FORM,
    ): string {
        $config = new \Config\Config();
        $config->app_path = dirname(__DIR__, 2);  // project root: real templates/
        // $_SESSION is just an array until session_start(), so this stays session-free.
        $_SESSION = ['csrf_token' => 'page-token'];
        $page = new \View\Template(
            config: $config,
            csrf: new \Security\CSRFProtectaroo(new \Mlaphp\Request()),
            admin_count: 0,
        );
        $page->setTemplate('admin/notifications.tpl.php');
        $page->set('due', $due);
        $page->set('message', $message);
        $page->set('upcoming', $upcoming);
        $page->set('form', $form);
        return $page->grabTheGoods();
    }

    public function testMessageIsEscapedAndOnlyASitePathIsLinked(): void
    {
        $html = $this->page([
            new Notification(1, '<b>hi</b>', 'javascript:alert(1)', '2027-04-01 00:00:00'),
            new Notification(2, 'Revisit', '/profile/', '2027-04-01 00:00:00'),
            new Notification(3, 'Elsewhere', '//evil.example', '2027-04-01 00:00:00'),
        ]);

        $this->assertStringContainsString('&lt;b&gt;hi&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>hi</b>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringContainsString('href="/profile/"', $html);
    }

    public function testEachRowHasACompletedButtonCarryingItsIdAndTheToken(): void
    {
        $html = $this->page([new Notification(42, 'Revisit', null, '2027-04-01 00:00:00')]);

        $this->assertStringContainsString('name="notification_id" value="42"', $html);
        $this->assertStringContainsString('name="do" value="complete"', $html);
        $this->assertStringContainsString('page-token', $html);
        $this->assertStringContainsString('>Completed</button>', $html);
        $this->assertStringContainsString('2027-04-01', $html);
    }

    public function testNothingDueSaysSoAndDrawsNoButton(): void
    {
        $html = $this->page([]);

        $this->assertStringContainsString('Nothing is due.', $html);
        $this->assertStringNotContainsString('>Completed</button>', $html);
    }

    public function testThePageSaysMigrationsAreNotCompletedHere(): void
    {
        $html = $this->page([]);

        $this->assertStringContainsString('href="/admin/migrate_tables.php"', $html);
        $this->assertStringContainsString("can't be completed", $html);
    }

    public function testTheResultOfTheLastPressIsShownEscaped(): void
    {
        $this->assertStringContainsString(
            '<strong>That one was already completed.</strong>',
            $this->page([], 'That one was already completed.'),
        );
    }

    public function testTheRemindMeFormPostsItsThreeFieldsWithTheToken(): void
    {
        $html = $this->page([]);

        $this->assertStringContainsString('name="do" value="remind"', $html);
        $this->assertStringContainsString('type="date" name="date"', $html);
        $this->assertStringContainsString('<textarea name="message"', $html);
        $this->assertStringContainsString('name="link"', $html);
        $this->assertStringContainsString('>Remind me</button>', $html);
        $this->assertSame(1, substr_count($html, 'page-token'));
    }

    public function testARefusedFormComesBackWithWhatWasTypedEscaped(): void
    {
        $html = $this->page(
            [],
            'Give a real date, as YYYY-MM-DD.',
            [],
            ['date' => '"><i>', 'message' => '</textarea><b>x</b>', 'link' => '"onfocus="x'],
        );

        $this->assertStringContainsString('<strong>Give a real date, as YYYY-MM-DD.</strong>', $html);
        $this->assertStringContainsString('value="&quot;&gt;&lt;i&gt;"', $html);
        $this->assertStringContainsString('&lt;/textarea&gt;&lt;b&gt;x&lt;/b&gt;</textarea>', $html);
        $this->assertStringContainsString('value="&quot;onfocus=&quot;x"', $html);
    }

    public function testUpcomingOnesAreListedByDayWithNoCompletedButton(): void
    {
        $html = $this->page([], '', [
            new Notification(7, '<b>later</b>', '/profile/', '2027-04-01 00:00:00'),
            new Notification(8, 'Elsewhere', '//evil.example', '2027-05-01 00:00:00'),
        ]);

        $this->assertStringContainsString('2027-04-01 UTC:', $html);
        $this->assertStringContainsString('&lt;b&gt;later&lt;/b&gt;', $html);
        $this->assertStringContainsString('href="/profile/"', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString('>Completed</button>', $html);
        $this->assertStringNotContainsString('Nothing is waiting.', $html);
    }

    public function testNothingUpcomingSaysSo(): void
    {
        $this->assertStringContainsString('Nothing is waiting.', $this->page([]));
    }
}
