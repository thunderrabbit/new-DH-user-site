<?php

declare(strict_types=1);

namespace Auth;

/**
 * Can the PHP serving this site hash passwords with argon2id?
 *
 * Asked on /admin/ because that runs under the web server's PHP. The shell's
 * `php` over ssh is a different build and proves nothing about this one.
 *
 * It matters for Auth\PasswordPolicy: bcrypt reads only 72 bytes, argon2id
 * has no such limit, and the template can change over only when every site's
 * PHP has it.
 */
final class Argon2id
{
    private const string ALGORITHM = 'argon2id';

    /**
     * @param list<string>|null $algorithms password_algos() by default.
     */
    public static function isListed(?array $algorithms = null): bool
    {
        return in_array(self::ALGORITHM, $algorithms ?? password_algos(), true);
    }

    /**
     * Makes one real hash, because being listed is not the same as working:
     * the default cost wants 64 MB, which a shared host may not hand over.
     *
     * @return int|null Milliseconds the hash took, or null when it failed.
     */
    public static function trialMilliseconds(): ?int
    {
        $trial = 'a trial, not a password';
        $started = hrtime(true);
        try {
            $hash = password_hash($trial, self::ALGORITHM);
        } catch (\Throwable) {
            return null;
        }
        // A wall-clock reading for a person to look at; no test can say what
        // it should be.
        // @infection-ignore-all
        $milliseconds = intdiv(hrtime(true) - $started, 1_000_000);

        return password_verify($trial, $hash) ? $milliseconds : null;
    }

    /**
     * One line for the admin dashboard.
     *
     * @param int|null $trial_ms trialMilliseconds()'s answer; ignored when not listed.
     */
    public static function summary(bool $is_listed, ?int $trial_ms): string
    {
        if (!$is_listed) {
            return 'bcrypt only; this PHP has no argon2id';
        }
        if ($trial_ms === null) {
            return 'argon2id is listed, but a test hash failed';
        }
        return "argon2id available (a test hash took {$trial_ms} ms)";
    }

    /**
     * summary() for the PHP that is running.
     */
    public static function report(): string
    {
        $is_listed = self::isListed();
        return self::summary($is_listed, $is_listed ? self::trialMilliseconds() : null);
    }
}
