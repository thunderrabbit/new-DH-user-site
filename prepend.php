<?php

declare(strict_types=1);

const SENTIMENTAL_VERSION = "Every device you sign in on";

require_once __DIR__ . '/classes/Mlaphp/Autoloader.php';
// create autoloader instance and register the method with SPL
$autoloader = new \Mlaphp\Autoloader();
spl_autoload_register($autoloader->load(...));

// Nothing below was reviewed on a PHP older than this, so stop there.
\Version\Php::requireFloor();

// Errors go to the log until Config says otherwise, below.
\Security\ErrorOutput::hide();

// Before any output, so every answer carries them, the error pages included.
\Security\Headers::send();

\Auth\Session::start();

$mla_request = new \Mlaphp\Request();
$csrfProtect = new \Security\CSRFProtectaroo($mla_request);

// Every state-changing request must carry the session's token. Checked here,
// before any page code or DB work, so a handler cannot forget it. A cross-site
// POST fails because the attacking page cannot read our session's token.
if (!$csrfProtect->validateRequest()) {
    http_response_code(403);
    echo "<h1>Request rejected</h1>"
        . "<p>The form's security token was missing or has expired. "
        . "Go back, reload the page, and try again.</p>";
    exit;
}

try {
    $config = new \Config\Config();
    // @phpstan-ignore catch.neverThrown (the autoloader throws when Config.php is missing)
} catch (\Exception $e) {
    // The message names the missing file's path, so it is logged, not shown.
    error_log("Couldn't create Config: " . $e->getMessage());
    http_response_code(503);
    echo "<h1>Site unavailable</h1><p>This site is not configured yet.</p>";
    exit;
}

// @phpstan-ignore nullCoalesce.property (an older Config.php may not declare it)
$show_errors = $config->display_errors ?? false;
if ($show_errors) {
    \Security\ErrorOutput::show();
}

$mla_database = \Database\Base::getPDO($config);
// Check if the database exists and is accessible
$dbExistaroo = new \Database\DBExistaroo(
    config: $config,
    pdo: $mla_database,
    schema_path: new \Database\SchemaPath($config->app_path),
);

$errors = $dbExistaroo->checkaroo();

$uri_path = $_SERVER['REQUEST_URI'] ?? '';

if (
    !empty($errors)
    && $errors[0] == "YallGotAnyMoreOfThemUsers"
    && $uri_path != "/login/register.php"
) {
    header(header: "Location: /login/register.php");
    exit;
}


if (!empty($errors)) {
    // These name the database and the configured domain. The operator reads
    // them in the error log, or on screen with $display_errors on.
    foreach ($errors as $error) {
        error_log("Setup problem: " . $error);
    }
    http_response_code(503);
    echo "<h1>Site unavailable</h1>";
    if ($show_errors) {
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>This site is not set up correctly. The details are in its error log.</p>";
    }
    exit;
}

$is_logged_in = new \Auth\IsLoggedIn(
    $mla_database,
    $config,
    new \Auth\RandomToken(),
    new \Auth\LoginThrottle($mla_database),
    new \Database\CookieRepository($mla_database),
);
// Read-only: who does the remember-me cookie say this is? Credentials are
// checked by /login/index.php alone, never here.
$is_logged_in->resumeFromCookie($mla_request);

// What waits for an admin, for the menu's Admin link: pending migrations plus
// due notifications (mg #851). The pending list is asked again here on
// purpose: the one checkaroo() built is from before its auto-apply, so it is
// stale on exactly the requests that change it. Only an admin is counted, so
// no query runs for anyone else.
$admin_count = 0;
if ($is_logged_in->isLoggedIn() && $is_logged_in->isAdmin()) {
    $admin_count = count($dbExistaroo->getPendingMigrations())
        + new \Admin\Notifications($mla_database)->dueCount();
}
