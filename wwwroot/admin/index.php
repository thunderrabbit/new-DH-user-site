<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

# A Config.php written before $site_title existed should still render a page.
$site_title = $config->site_title ?? 'Site';

if ($is_logged_in->isLoggedIn() && $is_logged_in->isAdmin()) {
    $page = new \View\Template(config: $config);
    $page->setTemplate("admin/index.tpl.php");
    $page->set(name: "site_title", value: $site_title);
    $page->set(name: "site_version", value: SENTIMENTAL_VERSION);
    $page->set(name: "allow_registration", value: $config->allow_registration ?? true);
    $page->set(name: "username", value: $is_logged_in->getLoggedInUsername());

    $pending = $dbExistaroo->getPendingMigrations();
    $page->set(name: "has_pending_migrations", value: !empty($pending));
    $inner = $page->grabTheGoods();

    $layout = new \View\Template(config: $config);
    $layout->setTemplate("layout/admin_base.tpl.php");
    $layout->set("page_title", $site_title . " Admin");
    $layout->set("page_content", $inner);
    $layout->echoToScreen();
    exit;
} else {
    header(header: "Location: /login/");
    exit;
}
