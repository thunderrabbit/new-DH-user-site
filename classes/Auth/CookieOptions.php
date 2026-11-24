<?php

declare(strict_types=1);

namespace Auth;

class CookieOptions
{
    /**
     * Options for setcookie()'s array form, with the security flags always on.
     *
     * PHP defaults `secure` and `httponly` to false when the keys are absent,
     * so leaving them out ships a 30-day auth token that document.cookie can
     * read and that travels in the clear over plain HTTP. Building the array
     * here keeps both call sites (set and kill) on the same flags.
     *
     * SameSite is Lax, not Strict. Strict withholds the cookie on a top-level
     * link from another site, so a user following a link from robnugen.com
     * arrives logged out. Lax still withholds it on cross-site POSTs, and the
     * CSRF token check in prepend.php is the real defence; SameSite is the
     * backstop. Do not loosen to None without a reason written here.
     *
     * There is no `domain` key, on purpose. A cookie with a Domain attribute
     * goes to every subdomain of that domain, and any of them may overwrite
     * it; without one it belongs to the exact host that set it.
     *
     * @return array{expires: int, path: string, secure: bool, httponly: bool, samesite: 'Lax'}
     */
    public static function build(int $expires): array
    {
        return [
            'expires' => $expires,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ];
    }
}
