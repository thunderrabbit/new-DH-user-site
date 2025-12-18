<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

if ($is_logged_in->isLoggedIn() && $is_logged_in->isAdmin()) {
    $page = new \Template(config: $config);
    $page->setTemplate("admin/migrate_tables.tpl.php");
    $pending = $dbExistaroo->getPendingMigrations();
    $page->set(name: "pending_migrations", value: $pending);
    $page->set(name: "has_pending_migrations", value: !empty($pending));
    $inner = $page->grabTheGoods();

    $layout = new \Template(config: $config);
    $layout->setTemplate("layout/admin_base.tpl.php");
    $layout->set("page_title", "Migrations");
    $layout->set("page_content", $inner);
    $layout->echoToScreen();
    exit;
} else {
    header(header: "Location: /login/");
    exit;
}
