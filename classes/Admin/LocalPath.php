<?php

declare(strict_types=1);

namespace Admin;

/**
 * Whether a string is a path on this site, the rule a reminder's link is
 * stored and drawn by (mg #851). It starts with one slash, never two, and has
 * no backslash, scheme or anything else a browser could read as another host.
 */
final class LocalPath
{
    /** The longest path taken, in bytes. */
    public const int MAX = 255;

    public static function isLocal(string $path): bool
    {
        return strlen($path) <= self::MAX
            && preg_match('#\A/(?![/\\\\])[A-Za-z0-9/._~%?=&+-]*\z#', $path) === 1;
    }
}
