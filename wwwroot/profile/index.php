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
 * @var \PDO $mla_database
 */

// Check if user is logged in
if (!$is_logged_in->isLoggedIn()) {
    header("Location: /login/");
    exit;
}

$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $current_password = is_string($current_password) ? $current_password : '';
    $new_password = $_POST['new_password'] ?? '';
    $new_password = is_string($new_password) ? $new_password : '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $confirm_password = is_string($confirm_password) ? $confirm_password : '';

    // Validate input
    $errors = [];
    if (empty($current_password)) {
        $errors[] = "Current password is required.";
    }
    if (empty($new_password)) {
        $errors[] = "New password is required.";
    }
    if (empty($confirm_password)) {
        $errors[] = "Password confirmation is required.";
    }
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    }

    // If no validation errors, proceed with password change
    if (empty($errors)) {
        try {
            $user_id = $is_logged_in->loggedInID();

            // Use PasswordRepository to handle password change
            $passwordRepository = new \Database\PasswordRepository($mla_database);
            $result = $passwordRepository->changePassword($user_id, $current_password, $new_password);

            if ($result['success']) {
                // A password change is the one remedy for a leaked session, so
                // it must cut every other session loose. This browser stays in.
                $revoked = $is_logged_in->revokeOtherSessions();
                $success_message = $result['message'];
                if ($revoked > 0) {
                    $success_message .= " Signed out {$revoked} other "
                        . ($revoked === 1 ? "session" : "sessions") . ".";
                }
            } else {
                $errors[] = $result['message'];
            }
        } catch (\Exception $e) {
            $errors[] = "An error occurred while changing password: " . $e->getMessage();
        }
    }

    // Set error message if there are errors
    if (!empty($errors)) {
        // HTML escape each error message individually, then join with <br>
        $escaped_errors = array_map('htmlspecialchars', $errors);
        $error_message = implode("<br>", $escaped_errors);
    }
}

// Display the form
$page = new \View\Template(config: $config);
$page->setTemplate("profile/index.tpl.php");
$page->set("username", $is_logged_in->getLoggedInUsername());
$page->set("error_message", $error_message);
$page->set("success_message", $success_message);

$inner = $page->grabTheGoods();

$layout = new \View\Template(config: $config);
$layout->setTemplate("layout/base.tpl.php");
$layout->set("username", $is_logged_in->getLoggedInUsername());
$layout->set("page_title", "Change Password");
$layout->set("page_content", $inner);
$layout->echoToScreen();
