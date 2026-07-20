<?php

namespace Config;

class Config
{
    public $site_title = '';  // shown in <title> and the front-page heading

    // May strangers create their own role='user' account at /login/register.php?
    // The first (admin) account is always allowed, gated by bootstrap_token.txt.
    public $allow_registration = true;
    public $domain_name = '';  // used for cookies
    public $cookie_name = '';  // used for cookies
    public $cookie_lifetime = 60 * 60 * 24 * 30; // 30 days
    public $app_path = '/home/user/sub.domain.com';

    public $dbHost = "eich";
    public $dbUser = "";
    public $dbPass = "";
    public $dbName = "";
}
