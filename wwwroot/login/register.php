<?php

// This is only run if users table is empty
// We do *not* include prepend.php because
// it would cause a circular dependency

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/classes/Mlaphp/Autoloader.php';
// create autoloader instance and register the method with SPL
$autoloader = new \Mlaphp\Autoloader();
spl_autoload_register(array($autoloader, 'load'));

$mla_request = new \Mlaphp\Request();
$config = new \Config();

try {
    $config = new \Config();
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

$creating_admin_user = !$dbExistaroo->firstUserExistBool();

// First-admin bootstrap is gated by a filesystem token: a fresh install would
// otherwise hand the admin account to whoever browses the site first (scanners
// find new vhosts fast, and the app funnels every URL here while the users table
// is empty). The token file lives in the project root — OUTSIDE the web root —
// so only someone with server access can read it.
//
// This page does NOT create the token. You generate it on your own machine, read
// it there, and rsync it up with the rest of the site (see README, First install).
// Nothing here can be registered until you do. Once the first admin exists this
// value is never consulted again, so a later rsync that restores the file is inert.
//
// The token gates the FIRST account only. Whether strangers may sign up afterwards
// is $config->allow_registration. A Config.php written before that property existed
// keeps this page open, which is how every site here behaved until now.
$bootstrap_token_path = $config->app_path . '/bootstrap_token.txt';
$bootstrap_token_missing = $creating_admin_user && !file_exists($bootstrap_token_path);

$allow_registration = $config->allow_registration ?? true;
$registration_closed = !$creating_admin_user && !$allow_registration;

if ($registration_closed) {
    http_response_code(403);
    echo "<h1>Registration closed</h1><p>This site is not accepting new accounts.</p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // handle form submission...
    $mla_database = \Database\Base::getPDO($config);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['pass'] ?? '';
    $password_confirm = $_POST['pass_verify'] ?? '';

    // Validate input
    $errors = [];
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    if ($password !== $password_confirm) {
        $errors[] = "Passwords do not match.";
    }

    // Bootstrap path: the posted setup token must match the server-side file.
    // hash_equals for a constant-time compare (the token is a credential).
    if ($creating_admin_user) {
        $setup_token = trim((string) ($_POST['setup_token'] ?? ''));
        $expected    = trim((string) @file_get_contents($bootstrap_token_path));
        if ($expected === '') {
            // Deliberately vague: this page is public while the users table is
            // empty. The operator knows where the token goes; a scanner must not
            // learn the username or the path. See README, First install.
            $errors[] = "This site has no setup token, so registration is closed. "
                . "The site owner must deploy one. See the project README.";
        } elseif ($setup_token === '' || !hash_equals($expected, $setup_token)) {
            $errors[] = "Setup token missing or incorrect.";
        }
    }

    // If errors, redisplay form with errors
    if (!empty($errors)) {
        echo "<h1>Registration Errors</h1><ul>";
        foreach ($errors as $e) {
            echo "<li>" . htmlspecialchars($e) . "</li>";
        }
        echo "</ul><a href=\"/\">Go back</a>";
        exit;
    }

    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $role = $creating_admin_user ? "admin" : "user";
        $stmt = $mla_database->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $hash, $role]);

        // Bootstrap complete — the token is single-use by design.
        if ($creating_admin_user) {
            @unlink($bootstrap_token_path);
        }

        echo "<p>User created!  Please <a href='/login'>log in</a> with your new credentials.</p>";

        // Only the admin, standing here once, ever sees this. Say it now: from the
        // next request onward this page's behaviour depends on a value they set.
        if ($creating_admin_user) {
            $state = $allow_registration ? "open" : "closed";
            echo "<p>By the way, registration is <strong>{$state}</strong> to new users. "
               . "Change <code>\$allow_registration</code> in <code>classes/Config.php</code>.</p>";
        }
    } catch (\PDOException $e) {
        if ($e->getCode() == '23000') { // Duplicate key error
            echo "<h1>Error</h1><p>User already exists. Try a different username.</p>";
        } else {
            echo "<h1>Unexpected Error</h1><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        }
    }

    exit;
} else {
    $page = new \Template(config: $config);
    $page->setTemplate("login/register.tpl.php");
    $page->set('creating_admin_user', $creating_admin_user);
    $page->set('bootstrap_token_missing', $bootstrap_token_missing);
    $page->echoToScreen();
    exit;
}
