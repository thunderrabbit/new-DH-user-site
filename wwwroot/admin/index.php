<?php

declare(strict_types=1);

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

/**
 * Set up by prepend.php.
 *
 * @var \Config\Config $config
 * @var \Security\CSRFProtectaroo $csrfProtect
 * @var int $admin_count
 * @var \PDO $mla_database
 * @var \Database\DBExistaroo $dbExistaroo
 * @var \Auth\IsLoggedIn $is_logged_in
 */

# A Config.php written before $site_title existed should still render a page.
// @phpstan-ignore nullCoalesce.property (an older Config.php may not declare it)
$site_title = $config->site_title ?? 'Site';

if ($is_logged_in->isLoggedIn() && $is_logged_in->isAdmin()) {
    $page = new \View\Template(config: $config, csrf: $csrfProtect, admin_count: $admin_count);
    $page->setTemplate("admin/index.tpl.php");
    $page->set(name: "site_title", value: $site_title);
    $page->set(name: "site_version", value: SENTIMENTAL_VERSION);
    // @phpstan-ignore nullCoalesce.property (an older Config.php may not declare it)
    $page->set(name: "allow_registration", value: $config->allow_registration ?? true);
    $page->set(name: "username", value: $is_logged_in->getLoggedInUsername());

    // Due notifications (mg #851), so a chip higher than the migration list says why.
    $page->set(name: "due_notifications", value: new \Admin\Notifications($mla_database)->dueCount());

    $pending = $dbExistaroo->getPendingMigrations();
    $page->set(name: "has_pending_migrations", value: !empty($pending));
    $inner = $page->grabTheGoods();

    $layout = new \View\Template(config: $config, csrf: $csrfProtect, admin_count: $admin_count);
    $layout->setTemplate("layout/base.tpl.php");
    $layout->set("page_title", $site_title . " Admin");
    $layout->set("username", $is_logged_in->getLoggedInUsername());
    $layout->set("is_admin", true);
    $layout->set("page_content", $inner);
    $layout->echoToScreen();
    exit;
} else {
    header(header: "Location: /login/");
    exit;
}
