<?php

class Config {

    public $domain_name = '';  // used for cookies
    public $cookie_name = '';  // used for cookies
    public $cookie_lifetime = 60 * 60 * 24 * 30; // 30 days
    public $app_path = '/home/user/sub.domain.com';

    public $dbHost = "eich";
    public $dbUser = "";
    public $dbPass = "";
    public $dbName = "";

    public $stripe_publishable_key = '';  // pk_test_... from Stripe Dashboard
    public $stripe_secret_key = '';  // sk_test_... from Stripe Dashboard
    public $stripe_webhook_secret = '';  // whsec_... from Stripe Webhooks
    public $stripe_webhook_endpoint = '/webhooks/stripe';
}
