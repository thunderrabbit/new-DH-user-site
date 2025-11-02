<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

if ($is_logged_in->isLoggedIn()) {
    // We logged in.. yay!
    header(header: "Location: /");
    exit;
} else {
    if(!$is_logged_in->isLoggedIn()){
        // Generate CSRF token for the login form
        $csrfToken = $csrfProtect->getToken("login_form");

        $page = new \Template(config: $config);
        $page->setTemplate("login/index.tpl.php");
        $page->set("csrf_token", $csrfToken);
        $page->echoToScreen();
        exit;
    }
}
