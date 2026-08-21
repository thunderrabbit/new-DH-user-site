<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

if ($is_logged_in->isLoggedIn()) {
    // Already in (remember-me cookie, or the POST below on a previous request).
    header(header: "Location: /");
    exit;
}

// This page is the ONLY place credentials are checked. prepend.php already
// rejected any POST without a CSRF token before we got here.
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $mla_request->post['username'] ?? '';
    $password = $mla_request->post['pass'] ?? '';

    if (
        is_string($username) && trim($username) !== ''
        && is_string($password) && $password !== ''
        && $is_logged_in->attemptPasswordLogin(trim($username), $password)
    ) {
        header(header: "Location: /");
        exit;
    }
    // One message for every failure. Saying which half was wrong tells a
    // guesser which usernames exist.
    $login_error = "Username or password incorrect.";
}

$page = new \View\Template(config: $config);
$page->setTemplate("login/index.tpl.php");
$page->set("login_error", $login_error);
$page->echoToScreen();
