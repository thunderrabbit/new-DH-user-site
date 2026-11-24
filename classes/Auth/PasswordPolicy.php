<?php

declare(strict_types=1);

namespace Auth;

/**
 * What a NEW password must satisfy: at registration and on a password change.
 * Login never asks, so an account whose password predates this still works.
 *
 * The rules are NIST SP 800-63B-4's for a password used as the only factor:
 *   - at least 15 characters, each Unicode code point counting as one;
 *   - no composition rules (no "one digit, one symbol"), so there are none;
 *   - the whole password is verified, never a truncated one.
 *
 * The last is why there is a maximum. password_hash() uses bcrypt, which
 * reads 72 bytes and silently ignores the rest, so a longer password is
 * refused rather than cut. NIST also says a verifier should accept at least
 * 64 characters: 72 bytes covers that for unaccented text, and falls short of
 * it for a long password in a script that takes several bytes a character.
 *
 * Not done: NIST's check of new passwords against a list of known-breached
 * ones. That needs a list on the server or a call to an outside service.
 */
final class PasswordPolicy
{
    public const int MIN_CHARACTERS = 15;

    /** bcrypt's input limit. Not a choice: see the class comment. */
    public const int MAX_BYTES = 72;

    // The messages spell the numbers out, so each is one string a test can
    // pin whole. Change a limit above and its message here together.
    public const string TOO_SHORT = "Password must be at least 15 characters. Several unrelated words work well.";

    public const string TOO_LONG = "Password is too long: the limit is 72 bytes, "
        . "which is 72 plain letters, or fewer accented ones.";

    public const string UNUSABLE = "Password contains a character that cannot be used.";

    /**
     * @return list<string> What is wrong, in words for the person typing;
     *         empty when the password is acceptable.
     */
    public static function problems(#[\SensitiveParameter] string $password): array
    {
        $problems = [];
        if (mb_strlen($password, 'UTF-8') < self::MIN_CHARACTERS) {
            $problems[] = self::TOO_SHORT;
        }
        if (strlen($password) > self::MAX_BYTES) {
            $problems[] = self::TOO_LONG;
        }
        if (str_contains($password, "\0")) {
            // password_hash() throws on one, so say so here instead.
            $problems[] = self::UNUSABLE;
        }
        return $problems;
    }
}
