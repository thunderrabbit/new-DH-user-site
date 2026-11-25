<?php

// Copy to site_config.php in the same directory (the project root, above
// wwwroot/), fill it in, and chmod 600 it. It is gitignored: each server
// has its own. Config\Settings documents every key and checks them all
// when the site starts; a missing, empty or misspelled one answers 503 and
// says which in the error log.
//
// Set only what this site needs. Every key left out takes the default in
// Config\Settings, so a new one reaches this server without editing here.
// app_path is not a key: it is always the directory this file is in.

return [
    // Required.
    'dbHost' => 'mysql.example.com',
    'dbName' => '',
    'dbUser' => '',
    'dbPass' => '',
    'domain_name' => 'example.com',  // must equal the browser's HTTP_HOST
    'cookie_name' => 'example_auth',  // the login cookie; the browser sees __Host-<this>

    // Optional, shown with their defaults.
    // 'site_title' => 'Site',
    // 'allow_registration' => true,  // may strangers sign up? The first admin always can.
    // 'display_errors' => false,  // never true on a site strangers can reach
    // 'cookie_lifetime' => 60 * 60 * 24 * 30,  // seconds, 60 up to 400 days
];
