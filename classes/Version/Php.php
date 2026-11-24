<?php

declare(strict_types=1);

namespace Version;

/**
 * The oldest PHP a site may run on.
 *
 * PHP 8.6 made sessions strict, HttpOnly and SameSite=Lax by default, and
 * the template is reviewed against that behaviour, so a site on anything
 * older stops instead of serving pages nobody checked there. Both entry
 * points (prepend.php and /login/register.php) call requireFloor() first.
 *
 * This is the runtime floor. composer.json and the gates may trail it while
 * no runner image exists for the newest PHP (see CLAUDE.md).
 */
final class Php
{
    public const int FLOOR_ID = 80600;

    public const string FLOOR = '8.6';

    /**
     * @param int $version_id A PHP_VERSION_ID; the running PHP's by default.
     */
    public static function meetsFloor(int $version_id = PHP_VERSION_ID): bool
    {
        return $version_id >= self::FLOOR_ID;
    }

    /**
     * Ends the request with a 503 on a PHP below the floor. The running
     * version goes to the error log, not to the browser.
     */
    public static function requireFloor(): void
    {
        if (self::meetsFloor()) {
            return;
        }
        error_log('This site needs PHP ' . self::FLOOR . ' or later; this is PHP ' . PHP_VERSION . '.');
        http_response_code(503);
        echo '<h1>Site unavailable</h1><p>This site needs PHP ' . self::FLOOR . ' or later.</p>';
        exit;
    }
}
