<?php

declare(strict_types=1);

namespace Admin;

/**
 * One row of the notifications table, as /admin/notifications/ lists it
 * (mg #851). The message and link are whatever a migration or the "Remind me"
 * form wrote: escape both, and draw the link only when it is a path on this
 * site.
 */
final readonly class Notification
{
    /** @param string $show_from UTC, Y-m-d H:i:s */
    public function __construct(
        public int $notification_id,
        public string $message,
        public ?string $link,
        public string $show_from,
    ) {
    }
}
