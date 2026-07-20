<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

$debugLevel = intval(value: $_GET['debug']) ?? 0;
if ($debugLevel > 0) {
    echo "<pre>Debug Level: $debugLevel</pre>";
}

# A Config.php written before $site_title existed should still render a page.
$site_title = $config->site_title ?? 'Site';

if ($is_logged_in->isLoggedIn()) {
    // Logged in - show main site homepage
    $page = new \Template(config: $config);
    $page->setTemplate("layout/admin_base.tpl.php");
    $page->set("page_title", $site_title);
    $page->set("username", $is_logged_in->getLoggedInUsername());
    $page->set("site_version", SENTIMENTAL_VERSION);

    // Get the inner content
    $inner_page = new \Template(config: $config);
    $inner_page->setTemplate("index.tpl.php");
    $inner_page->set("site_title", $site_title);
    $inner_page->set("username", $is_logged_in->getLoggedInUsername());
    $inner_page->set("site_version", SENTIMENTAL_VERSION);
    $page->set("page_content", $inner_page->grabTheGoods());

    $page->echoToScreen();
    exit;
} else {
    echo "<h1>" . htmlspecialchars($site_title) . "</h1>";
    echo "<p><a href='/login/'>Click here to log in</a></p>";
    exit;
}
