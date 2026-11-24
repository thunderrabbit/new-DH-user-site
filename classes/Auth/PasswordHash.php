<?php

declare(strict_types=1);

namespace Auth;

/**
 * The one place that decides how passwords are hashed.
 *
 * argon2id where the PHP has it, because it reads the whole password; bcrypt
 * (which reads 72 bytes) where it does not, so a site on such a PHP keeps
 * working. /admin/ says which this site has. Nothing else in the app calls
 * password_hash() or password_needs_rehash().
 *
 * Verifying needs no help from here: password_verify() reads the algorithm
 * out of the stored hash, so bcrypt hashes made before the change still
 * verify, and needsRehash() then has login replace them.
 */
final class PasswordHash
{
    public const string ARGON2ID = 'argon2id';

    public const string BCRYPT = '2y';

    /**
     * A hash of nothing anyone can type, per algorithm at PHP's default cost,
     * for IsLoggedIn to check when the username does not exist: a miss must
     * take as long as a wrong password. Regenerate one if PHP's default cost
     * for its algorithm changes.
     */
    private const array ABSENT_USER_HASHES = [
        self::ARGON2ID => '$argon2id$v=19$m=65536,t=4,p=1$Z0lveXJCZGNMVVVpdGZ6TQ'
            . '$ruCfOvvEGkJxUSWsni4vjhXIa0B0fQlvCJ7W7L7m8m4',
        self::BCRYPT => '$2y$12$CNNMbz7HrYFfGv3BK2PH.uNFv1u/rjjvtBsMzzvkhF69Rvm14J8u6',
    ];

    /**
     * @param list<string>|null $available password_algos() by default.
     * @return self::ARGON2ID|self::BCRYPT
     */
    public static function algorithm(?array $available = null): string
    {
        return Argon2id::isListed($available) ? self::ARGON2ID : self::BCRYPT;
    }

    public static function make(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, self::algorithm());
    }

    /**
     * True for a hash made with another algorithm or at an older cost.
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algorithm());
    }

    /**
     * @param self::ARGON2ID|self::BCRYPT|null $algorithm algorithm() by default.
     */
    public static function absentUserHash(?string $algorithm = null): string
    {
        return self::ABSENT_USER_HASHES[$algorithm ?? self::algorithm()];
    }
}
