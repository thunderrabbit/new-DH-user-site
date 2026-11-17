<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

class TemplateTest extends Unit
{
    private function template(): \View\Template
    {
        $config = new \Config\Config();
        $config->app_path = dirname(__DIR__, 2);  // project root: real templates/
        return new \View\Template(
            config: $config,
            csrf: new \Security\CSRFProtectaroo(new \Mlaphp\Request()),
            admin_count: 0,
        );
    }

    public function testAdminCountReachesTemplateAndCannotBeOverridden(): void
    {
        $root = sys_get_temp_dir() . '/template-count-' . bin2hex(random_bytes(4));
        mkdir("$root/templates", 0700, true);
        file_put_contents("$root/templates/count.tpl.php", '<?= $admin_count ?>');
        $_SESSION = [];
        try {
            $config = new \Config\Config();
            $config->app_path = $root;
            $page = new \View\Template(
                config: $config,
                csrf: new \Security\CSRFProtectaroo(new \Mlaphp\Request()),
                admin_count: 3,
            );
            $page->setTemplate("count.tpl.php");
            $page->set('admin_count', 99);

            $this->assertSame('3', $page->grabTheGoods());
        } finally {
            unset($_SESSION);
            unlink("$root/templates/count.tpl.php");
            rmdir("$root/templates");
            rmdir($root);
        }
    }

    public function testHandsTheCsrfProtectorToTheTemplate(): void
    {
        $root = sys_get_temp_dir() . '/template-csrf-' . bin2hex(random_bytes(4));
        mkdir("$root/templates", 0700, true);
        file_put_contents("$root/templates/form.tpl.php", '<?= $csrf->field() ?>');
        $_SESSION = [];
        try {
            $csrf = new \Security\CSRFProtectaroo(new \Mlaphp\Request());
            $config = new \Config\Config();
            $config->app_path = $root;
            $page = new \View\Template($config, csrf: $csrf, admin_count: 0);
            $page->setTemplate("form.tpl.php");
            $page->set('csrf', 'a page variable cannot replace it');

            $this->assertSame($csrf->field(), $page->grabTheGoods());
        } finally {
            unset($_SESSION);
            unlink("$root/templates/form.tpl.php");
            rmdir("$root/templates");
            rmdir($root);
        }
    }

    public function testRendersTemplateWithVars(): void
    {
        $page = $this->template();
        $page->setTemplate("index.tpl.php");
        $page->set('username', 'Rob');
        $page->set('site_title', 'Test Site');

        $html = $page->grabTheGoods();

        $this->assertStringContainsString('Welcome back, Rob!', $html);
        $this->assertStringContainsString('<h1>Test Site</h1>', $html);
    }

    public function testEscapesHtmlInSiteTitle(): void
    {
        $page = $this->template();
        $page->setTemplate("index.tpl.php");
        $page->set('username', 'Rob');
        $page->set('site_title', '<script>alert(1)</script>');

        $html = $page->grabTheGoods();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testConditionalVarShownOnlyWhenSet(): void
    {
        $page = $this->template();
        $page->setTemplate("index.tpl.php");
        $page->set('username', 'Rob');
        $page->set('site_title', 'Test Site');
        $this->assertStringNotContainsString('Version:', $page->grabTheGoods());

        $page = $this->template();
        $page->setTemplate("index.tpl.php");
        $page->set('username', 'Rob');
        $page->set('site_title', 'Test Site');
        $page->set('site_version', '1.2.3');
        $this->assertStringContainsString('Version: 1.2.3', $page->grabTheGoods());
    }

    public function testLayoutNestingViaGrabTheGoods(): void
    {
        // The wwwroot pattern: render an inner template, hand it to the layout.
        $inner = $this->template();
        $inner->setTemplate("index.tpl.php");
        $inner->set('username', 'Rob');
        $inner->set('site_title', 'Test Site');

        $layout = $this->template();
        $layout->setTemplate("layout/base.tpl.php");
        $layout->set('page_title', 'Nested Page');
        $layout->set('page_content', $inner->grabTheGoods());

        $html = $layout->grabTheGoods();

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Nested Page</title>', $html);
        $this->assertStringContainsString('Welcome back, Rob!', $html);
    }

    public function testLayoutPageTitleDefaultsWhenUnset(): void
    {
        $layout = $this->template();
        $layout->setTemplate("layout/base.tpl.php");
        $layout->set('page_content', 'hello');

        $this->assertStringContainsString('<title>Site</title>', $layout->grabTheGoods());
    }

    private function menuFor(string $username, bool $is_admin): string
    {
        $layout = $this->template();
        $layout->setTemplate("layout/base.tpl.php");
        $layout->set('username', $username);
        $layout->set('is_admin', $is_admin);
        $layout->set('page_content', 'hello');
        return $layout->grabTheGoods();
    }

    public function testMenuOffersLoginWhenLoggedOut(): void
    {
        $html = $this->menuFor('', false);

        $this->assertStringContainsString('href="/login/"', $html);
        $this->assertStringNotContainsString('href="/logout/"', $html);
        $this->assertStringNotContainsString('href="/profile/devices/"', $html);
        $this->assertStringNotContainsString('href="/admin/"', $html);
    }

    public function testMenuOffersProfileAndLogoutWhenLoggedIn(): void
    {
        $html = $this->menuFor('Rob', false);

        $this->assertStringContainsString('href="/profile/"', $html);
        $this->assertStringContainsString('href="/profile/devices/"', $html);
        $this->assertStringContainsString('href="/logout/"', $html);
        $this->assertStringNotContainsString('href="/login/"', $html);
        $this->assertStringNotContainsString('href="/admin/"', $html);
    }

    public function testMenuOffersAdminOnlyToAdmins(): void
    {
        $this->assertStringContainsString('href="/admin/"', $this->menuFor('Rob', true));
    }

    public function testMenuDefaultsToLoggedOut(): void
    {
        $layout = $this->template();
        $layout->setTemplate("layout/base.tpl.php");
        $layout->set('page_content', 'hello');

        $this->assertStringContainsString('href="/login/"', $layout->grabTheGoods());
    }

    public function testWelcomeEscapesSiteTitle(): void
    {
        $page = $this->template();
        $page->setTemplate("welcome.tpl.php");
        $page->set('site_title', '<b>Games</b>');

        $html = $page->grabTheGoods();

        $this->assertStringContainsString('<h1>&lt;b&gt;Games&lt;/b&gt;</h1>', $html);
        $this->assertStringContainsString('href="/login/"', $html);
    }
}
