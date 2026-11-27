<?php

declare(strict_types=1);

namespace Tests\Unit;

use Config\Settings;

/**
 * A valid Config\Settings for tests that only care where the templates are.
 */
final class TestSettings
{
    public static function at(string $app_path): Settings
    {
        return new Settings(
            app_path: $app_path,
            dbHost: 'localhost',
            dbName: 'test',
            dbUser: 'test',
            dbPass: 'test',
            domain_name: 'example.com',
            cookie_name: 'test_auth',
        );
    }
}
