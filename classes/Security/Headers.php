<?php

declare(strict_types=1);

namespace Security;

/**
 * Response headers every page sends, so no page has to remember them.
 *
 * Both entry points (prepend.php and /login/register.php) call send() before
 * any output.
 */
final class Headers
{
    /**
     * @return array<string, string> header name => value
     */
    public static function all(): array
    {
        return [
            // A file is what its Content-Type says, never what a browser
            // guesses from its bytes.
            'X-Content-Type-Options' => 'nosniff',

            // No other site may put these pages in a frame, where a visitor
            // can be made to click "Sign out" or "Completed" without seeing
            // it. frame-ancestors is the current way to say so and
            // X-Frame-Options the one older browsers know. base-uri stops an
            // injected <base> from re-pointing every relative link and form.
            // There is no script-src: templates carry inline scripts, and a
            // policy that allowed those would promise more than it delivers.
            'Content-Security-Policy' => "frame-ancestors 'none'; base-uri 'self'",
            'X-Frame-Options' => 'DENY',

            // The URL of a logged-in page stays on this site.
            'Referrer-Policy' => 'same-origin',

            // HTTPS only, for a year. The cookies already refuse plain HTTP;
            // this makes the browser stop trying it. Not includeSubDomains: a
            // site must not decide that for hosts it does not run.
            'Strict-Transport-Security' => 'max-age=31536000',
        ];
    }

    public static function send(): void
    {
        foreach (self::all() as $name => $value) {
            header($name . ': ' . $value);
        }
    }
}
