<?php

declare(strict_types=1);

namespace Admin;

/**
 * What became of a "Remind me" form (mg #851): added, or the one thing wrong
 * with it. An admin typed it, so each failure may name its field.
 */
enum RemindResult
{
    case Added;
    case BadDate;
    case BadMessage;
    case BadLink;
    case Failed;

    /** What the page says. */
    public function says(): string
    {
        return match ($this) {
            self::Added => 'Reminder set.',
            self::BadDate => 'Give a real date, as YYYY-MM-DD.',
            self::BadMessage => 'Give a message of 1 to ' . Notifications::MESSAGE_MAX . ' characters.',
            self::BadLink => 'The link must be a path on this site, starting with one /, or left empty.',
            self::Failed => 'The reminder could not be saved.',
        };
    }
}
