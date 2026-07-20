<?php

// Unit test bootstrap - load project classes without prepend.php
// (prepend.php starts a session and connects to the DB; unit tests must not)

$projectRoot = dirname(__DIR__, 2);

spl_autoload_register(function ($class) use ($projectRoot) {
    $class = ltrim($class, '\\');
    $path = str_replace(['\\', '_'], DIRECTORY_SEPARATOR, $class);
    $file = $projectRoot . '/classes/' . $path . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
    // Return silently if file not found (let other autoloaders handle it)
});
