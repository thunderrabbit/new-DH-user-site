<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

/**
 * The count beside the menu's Admin link (mg #858): admins only, never at 0.
 */
class MenuChipTest extends Unit
{
    protected function _after(): void
    {
        unset($_SESSION);
    }

    private function menu(string $username, bool $is_admin, int $admin_count): string
    {
        $config = new \Config\Config();
        $config->app_path = dirname(__DIR__, 2);  // project root: real templates/
        // $_SESSION is just an array until session_start(), so this stays session-free.
        $_SESSION = ['csrf_token' => 'menu-token'];
        $layout = new \View\Template(
            config: $config,
            csrf: new \Security\CSRFProtectaroo(new \Mlaphp\Request()),
            admin_count: $admin_count,
        );
        $layout->setTemplate('layout/base.tpl.php');
        $layout->set('username', $username);
        $layout->set('is_admin', $is_admin);
        $layout->set('page_content', 'hello');
        return $layout->grabTheGoods();
    }

    public function testAdminWithCountSeesChip(): void
    {
        $html = $this->menu('rob', true, 3);

        $this->assertStringContainsString('<span class="AdminCount"', $html);
        $this->assertStringContainsString('>3<', $html);
    }

    public function testChipIsInsideTheAdminLinkAndNamedForScreenReaders(): void
    {
        $html = $this->menu('rob', true, 3);

        $this->assertMatchesRegularExpression(
            '#<a href="/admin/" aria-label="Admin, 3 waiting for an admin">Admin\s*'
            . '<span class="AdminCount"[^>]*>3</span>\s*</a>#',
            $html,
        );
    }

    public function testZeroLeavesThePlainAdminLink(): void
    {
        $this->assertStringContainsString('<a href="/admin/">Admin</a>', $this->menu('rob', true, 0));
    }

    public function testZeroDrawsNoChip(): void
    {
        $this->assertStringNotContainsString('AdminCount', $this->menu('rob', true, 0));
    }

    public function testNonAdminNeverSeesChip(): void
    {
        $this->assertStringNotContainsString('AdminCount', $this->menu('someone', false, 3));
    }

    public function testLoggedOutNeverSeesChip(): void
    {
        $this->assertStringNotContainsString('AdminCount', $this->menu('', true, 3));
    }
}
