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
 * @var \Auth\IsLoggedIn $is_logged_in
 */

$debugLevel = filter_var($_GET['debug'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
if ($debugLevel > 0) {
    echo "<pre>Debug Level: $debugLevel</pre>";
}

# A Config.php written before $site_title existed should still render a page.
// @phpstan-ignore nullCoalesce.property (an older Config.php may not declare it)
$site_title = $config->site_title ?? 'Site';

if ($is_logged_in->isLoggedIn()) {
    // Logged in - show main site homepage
    $page = new \View\Template(config: $config);
    $page->setTemplate("layout/base.tpl.php");
    $page->set("page_title", $site_title);
    $page->set("username", $is_logged_in->getLoggedInUsername());
    $page->set("is_admin", $is_logged_in->isAdmin());
    $page->set("site_version", SENTIMENTAL_VERSION);

    // Get the inner content
    $inner_page = new \View\Template(config: $config);
    $inner_page->setTemplate("index.tpl.php");
    $inner_page->set("site_title", $site_title);
    $inner_page->set("username", $is_logged_in->getLoggedInUsername());
    $inner_page->set("site_version", SENTIMENTAL_VERSION);
    $page->set("page_content", $inner_page->grabTheGoods());

    $page->echoToScreen();
    exit;
} else {
    // Logged out - same layout and menu, with a way in
    $inner_page = new \View\Template(config: $config);
    $inner_page->setTemplate("welcome.tpl.php");
    $inner_page->set("site_title", $site_title);

    $page = new \View\Template(config: $config);
    $page->setTemplate("layout/base.tpl.php");
    $page->set("page_title", $site_title);
    $page->set("username", "");
    $page->set("is_admin", false);
    $page->set("page_content", $inner_page->grabTheGoods());
    $page->echoToScreen();
    exit;
}
