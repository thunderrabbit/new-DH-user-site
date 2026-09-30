<?php

declare(strict_types=1);

namespace Auth;

/**
 * One signed-in browser: a live row of `cookies`, as /profile/devices/ shows it.
 *
 * Carries the row id (to revoke it) but never the cookie hash, so nothing that
 * renders a Device can leak a token lookup key into the page.
 */
class Device
{
    public function __construct(
        public readonly int $cookie_id,
        public readonly string $user_agent,
        public readonly string $ip_address,
        public readonly string $created_at,
        public readonly string $last_access,
        public readonly string $expires_at,
        public readonly bool $is_current,
    ) {
    }

    public function label(): string
    {
        return DeviceLabel::fromUserAgent($this->user_agent);
    }
}
