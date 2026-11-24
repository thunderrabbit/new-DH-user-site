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
 * Passwords are hashed with argon2id (Auth\PasswordHash), which reads every
 * byte, so the maximum is only a bound on what the server will hash: 1024
 * characters, far past the 64 NIST says a verifier should accept.
 *
 * A PHP without argon2id falls back to bcrypt, and bcrypt reads 72 bytes and
 * silently ignores the rest. There a longer password is refused rather than
 * cut, which is the third rule above. /admin/ says which case a site is in.
 *
 * Not done: NIST's check of new passwords against a list of known-breached
 * ones. That needs a list on the server or a call to an outside service.
 */
final class PasswordPolicy
{
    public const int MIN_CHARACTERS = 15;

    /** Under argon2id. A bound on the work, not a property of the hash. */
    public const int MAX_CHARACTERS = 1024;

    /** Under the bcrypt fallback: its input limit, not a choice. */
    public const int BCRYPT_MAX_BYTES = 72;

    // The messages spell the numbers out, so each is one string a test can
    // pin whole. Change a limit above and its message here together.
    public const string TOO_SHORT = "Password must be at least 15 characters. Several unrelated words work well.";

    public const string TOO_LONG = "Password is too long: the limit is 1024 characters.";

    public const string TOO_LONG_FOR_BCRYPT = "Password is too long: the limit is 72 bytes, "
        . "which is 72 plain letters, or fewer accented ones.";

    public const string UNUSABLE = "Password contains a character that cannot be used.";

    /**
     * @param PasswordHash::ARGON2ID|PasswordHash::BCRYPT|null $algorithm The one the
     *        password will be hashed with; PasswordHash::algorithm() by default.
     * @return list<string> What is wrong, in words for the person typing;
     *         empty when the password is acceptable.
     */
    public static function problems(
        #[\SensitiveParameter] string $password,
        ?string $algorithm = null,
    ): array {
        $problems = [];
        $characters = mb_strlen($password, 'UTF-8');
        if ($characters < self::MIN_CHARACTERS) {
            $problems[] = self::TOO_SHORT;
        }
        if (($algorithm ?? PasswordHash::algorithm()) === PasswordHash::BCRYPT) {
            if (strlen($password) > self::BCRYPT_MAX_BYTES) {
                $problems[] = self::TOO_LONG_FOR_BCRYPT;
            }
        } elseif ($characters > self::MAX_CHARACTERS) {
            $problems[] = self::TOO_LONG;
        }
        if (str_contains($password, "\0")) {
            // bcrypt's password_hash() throws on one, and no keyboard types
            // one, so it is refused whatever the algorithm.
            $problems[] = self::UNUSABLE;
        }
        return $problems;
    }
}
