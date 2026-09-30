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

if (!$is_logged_in->isLoggedIn()) {
    header("Location: /login/");
    exit;
}

$error_message = '';
$success_message = '';

// prepend.php has already rejected any POST without the CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'revoke') {
        $cookie_id = $_POST['cookie_id'] ?? '';
        if (is_string($cookie_id) && ctype_digit($cookie_id) && $is_logged_in->revokeDevice((int) $cookie_id)) {
            $success_message = "Signed that device out.";
        } else {
            $error_message = "That device was already signed out.";
        }
    } elseif ($action === 'revoke_others') {
        $revoked = $is_logged_in->revokeOtherSessions();
        $success_message = "Signed out {$revoked} other " . ($revoked === 1 ? "device" : "devices") . ".";
    }
}

$page = new \View\Template(config: $config);
$page->setTemplate("profile/devices.tpl.php");
$page->set("devices", $is_logged_in->devices());
$page->set("error_message", $error_message);
$page->set("success_message", $success_message);

$inner = $page->grabTheGoods();

$layout = new \View\Template(config: $config);
$layout->setTemplate("layout/base.tpl.php");
$layout->set("username", $is_logged_in->getLoggedInUsername());
$layout->set("is_admin", $is_logged_in->isAdmin());
$layout->set("page_title", "Signed-in Devices");
$layout->set("page_content", $inner);
$layout->echoToScreen();
