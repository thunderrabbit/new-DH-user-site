<?php

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

    $result = \Auth\LoginResult::BadCredentials;
    if (is_string($username) && trim($username) !== '' && is_string($password) && $password !== '') {
        $result = $is_logged_in->attemptPasswordLogin(trim($username), $password);
    }

    if ($result === \Auth\LoginResult::Success) {
        header(header: "Location: /");
        exit;
    }
    // One message for every bad credential. Saying which half was wrong tells
    // a guesser which usernames exist. Throttling is the one thing worth
    // naming, so a real user knows waiting will help and retyping will not.
    $login_error = match ($result) {
        \Auth\LoginResult::Throttled => "Too many failed logins. Wait "
            . intdiv(\Auth\LoginThrottle::WINDOW_SECONDS, 60) . " minutes and try again.",
        default => "Username or password incorrect.",
    };
}

$page = new \View\Template(config: $config);
$page->setTemplate("login/index.tpl.php");
$page->set("login_error", $login_error);
$page->echoToScreen();
