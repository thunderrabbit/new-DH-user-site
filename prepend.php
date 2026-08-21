<?php

const SENTIMENTAL_VERSION = "Cookies go stale";

# write errors to screen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/classes/Mlaphp/Autoloader.php';
// create autoloader instance and register the method with SPL
$autoloader = new \Mlaphp\Autoloader();
spl_autoload_register(array($autoloader, 'load'));

$mla_request = new \Mlaphp\Request();
$csrfProtect = new \Security\CSRFProtectaroo($mla_request);

/**
 * The hidden CSRF input for any POST form. Templates write <?= csrf_field() ?>
 * inside the <form>; no page-level set() is needed.
 */
function csrf_field(): string
{
    global $csrfProtect;
    return $csrfProtect->field();
}

/**
 * The bare token, for fetch() callers that send it as the X-CSRF-Token header
 * (see templates/admin/migrate_tables.tpl.php). Always json_encode() it into JS.
 */
function csrf_token(): string
{
    global $csrfProtect;
    return $csrfProtect->getToken();
}

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

function print_rob($object, $exit = true)
{
    echo "<pre>";
    if (is_object($object) && method_exists($object, "toArray")) {
        echo "ResultSet => " . print_r($object->toArray(), true);
    } else {
        print_r($object);
    }
    echo "</pre>";
    if ($exit) {
        exit;
    }
}

try {
    $config = new \Config\Config();
} catch (\Exception $e) {
    echo "Couldn't create Config cause " . $e->getMessage();
    exit;
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
    echo "<h1>Database Errors</h1>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
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
