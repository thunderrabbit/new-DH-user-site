<?php

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
     */
    public static function build(string $domain_name, int $expires): array
    {
        return [
            'expires' => $expires,
            'path' => '/',
            'domain' => $domain_name,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict' // None || Lax  || Strict
        ];
    }
}
