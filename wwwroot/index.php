<?php

declare(strict_types=1);

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

/**
 * Set up by prepend.php.
 *
 * @var \Config\Settings $config
 * @var \Security\CSRFProtectaroo $csrfProtect
 * @var int $admin_count
 * @var \Auth\IsLoggedIn $is_logged_in
 */

# A Config.php written before $site_title existed should still render a page.
$site_title = $config->site_title;

if ($is_logged_in->isLoggedIn()) {
    // Logged in - show main site homepage
    $page = new \View\Template(config: $config, csrf: $csrfProtect, admin_count: $admin_count);
    $page->setTemplate("layout/base.tpl.php");
    $page->set("page_title", $site_title);
    $page->set("username", $is_logged_in->getLoggedInUsername());
    $page->set("is_admin", $is_logged_in->isAdmin());
    $page->set("site_version", SENTIMENTAL_VERSION);

    // Get the inner content
    $inner_page = new \View\Template(config: $config, csrf: $csrfProtect, admin_count: $admin_count);
    $inner_page->setTemplate("index.tpl.php");
    $inner_page->set("site_title", $site_title);
    $inner_page->set("username", $is_logged_in->getLoggedInUsername());
    $inner_page->set("is_admin", $is_logged_in->isAdmin());
    $inner_page->set("site_version", SENTIMENTAL_VERSION);
    $page->set("page_content", $inner_page->grabTheGoods());

    $page->echoToScreen();
    exit;
} else {
    // Logged out - same layout and menu, with a way in
    $inner_page = new \View\Template(config: $config, csrf: $csrfProtect, admin_count: $admin_count);
    $inner_page->setTemplate("welcome.tpl.php");
    $inner_page->set("site_title", $site_title);

    $page = new \View\Template(config: $config, csrf: $csrfProtect, admin_count: $admin_count);
    $page->setTemplate("layout/base.tpl.php");
    $page->set("page_title", $site_title);
    $page->set("username", "");
    $page->set("is_admin", false);
    $page->set("page_content", $inner_page->grabTheGoods());
    $page->echoToScreen();
    exit;
}
