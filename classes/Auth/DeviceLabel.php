<?php

declare(strict_types=1);

namespace Auth;

/**
 * A human name for a User-Agent string, e.g. "iPhone · Safari".
 *
 * Good enough for a user to recognise their own devices on /profile/devices/,
 * not a browser-detection library: nothing makes decisions from this label.
 * Both lists are first-match-wins, so order matters. Every Chromium browser
 * also says "Chrome", every Chrome also says "Safari", an iPad says "like Mac
 * OS X" and Android and ChromeOS both say "Linux".
 */
class DeviceLabel
{
    /** @var array<string, string> needle => system */
    private const SYSTEMS = [
        'iPhone' => 'iPhone',
        'iPad' => 'iPad',
        'Android' => 'Android',
        'CrOS' => 'ChromeOS',
        'Windows' => 'Windows',
        'Macintosh' => 'Mac',
        'Linux' => 'Linux',
    ];

    /** @var array<string, string> needle => browser */
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'SamsungBrowser/' => 'Samsung Internet',
        'FxiOS/' => 'Firefox',
        'Firefox/' => 'Firefox',
        'CriOS/' => 'Chrome',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    public static function fromUserAgent(string $user_agent): string
    {
        $parts = array_filter([
            self::firstMatch($user_agent, self::SYSTEMS),
            self::firstMatch($user_agent, self::BROWSERS),
        ]);
        return $parts === [] ? 'Unknown device' : implode(' · ', $parts);
    }

    /**
     * @param array<string, string> $needles
     */
    private static function firstMatch(string $haystack, array $needles): string
    {
        foreach ($needles as $needle => $name) {
            if (str_contains($haystack, $needle)) {
                return $name;
            }
        }
        return '';
    }
}
