<?php

const SENTIMENTAL_VERSION = "Flexiblate the Database";

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

$is_logged_in = new \Auth\IsLoggedIn($mla_database, $config);
$is_logged_in->checkLogin($mla_request);
