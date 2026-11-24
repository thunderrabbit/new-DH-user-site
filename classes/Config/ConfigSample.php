<?php

declare(strict_types=1);

namespace Config;

class Config
{
    public string $site_title = '';  // shown in <title> and the front-page heading

    // May strangers create their own role='user' account at /login/register.php?
    // The first (admin) account is always allowed, gated by bootstrap_token.txt.
    public bool $allow_registration = true;

    // Print PHP errors and uncaught exceptions in the page? They are always
    // logged. Leave false on any site a stranger can reach.
    public bool $display_errors = false;
    public string $domain_name = '';  // must equal the browser's HTTP_HOST
    public string $cookie_name = '';  // the login cookie; the browser sees it as __Host-<this>
    public int $cookie_lifetime = 60 * 60 * 24 * 30; // 30 days
    public string $app_path = '/home/user/sub.domain.com';

    public string $dbHost = "eich";
    public string $dbName = "";
    public string $dbUser = "";
    public string $dbPass = "";
}
