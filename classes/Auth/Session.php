<?php

declare(strict_types=1);

namespace Auth;

/**
 * The PHP session, started the same way everywhere.
 *
 * The session holds one thing, the CSRF token (Security\CSRFProtectaroo), so
 * whoever can choose a visitor's session id knows their token. The cookie
 * name's `__Host-` prefix is what stops that: a browser accepts such a cookie
 * only over HTTPS, with Path=/ and without a Domain attribute, so a sibling
 * subdomain cannot set one for this host and the cookie is sent to this host
 * alone. PHP's default name, PHPSESSID, has no such protection.
 *
 * PHP 8.6 already defaults to strict ids, HttpOnly and SameSite=Lax. They are
 * repeated here because a host's php.ini can still say otherwise, and Secure
 * is not a default at all.
 *
 * Every session_start() in the app goes through start(): prepend.php,
 * /login/register.php (which runs without prepend.php) and the fresh session
 * IsLoggedIn::logout() opens. A site served over plain HTTP gets no session
 * and so no working forms; that is intended.
 */
final class Session
{
    public const string NAME = '__Host-session';

    /**
     * Options for session_start(), which applies them as session.* settings.
     *
     * @return array{
     *     name: string,
     *     cookie_secure: '1',
     *     cookie_httponly: '1',
     *     cookie_samesite: 'Lax',
     *     cookie_path: '/',
     *     cookie_domain: '',
     *     use_strict_mode: '1',
     *     use_only_cookies: '1'
     * }
     */
    public static function options(): array
    {
        return [
            'name' => self::NAME,
            'cookie_secure' => '1',
            'cookie_httponly' => '1',
            'cookie_samesite' => 'Lax',
            'cookie_path' => '/',
            'cookie_domain' => '',
            'use_strict_mode' => '1',
            'use_only_cookies' => '1',
        ];
    }

    public static function start(): void
    {
        session_start(self::options());
    }
}
