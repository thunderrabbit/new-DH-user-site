<?php

declare(strict_types=1);

namespace Tests\Unit;

use Auth\PasswordHash;
use Codeception\Test\Unit;

/**
 * The runner image's PHP has argon2id; the bcrypt fallback is reached by
 * naming the algorithms a lesser PHP would list.
 */
class PasswordHashTest extends Unit
{
    public function testArgon2idIsUsedWhereThePhpHasIt(): void
    {
        $this->assertSame('argon2id', PasswordHash::algorithm(['2y', 'argon2i', 'argon2id']));
    }

    public function testBcryptIsTheFallbackWhereItDoesNot(): void
    {
        $this->assertSame('2y', PasswordHash::algorithm(['2y']));
    }

    public function testANewHashIsArgon2idAndVerifies(): void
    {
        $hash = PasswordHash::make('correct horse battery staple');

        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertTrue(password_verify('correct horse battery staple', $hash));
        $this->assertFalse(password_verify('correct horse battery stapl', $hash));
    }

    public function testEveryByteOfALongPasswordCounts(): void
    {
        // bcrypt would call these two the same: they differ only past byte 72.
        $long = str_repeat('a', 100);
        $hash = PasswordHash::make($long . 'x');

        $this->assertFalse(password_verify($long . 'y', $hash));
        $this->assertTrue(password_verify($long . 'x', $hash));
    }

    public function testABcryptHashFromBeforeTheChangeNeedsRehashing(): void
    {
        $old = password_hash('correct horse battery staple', PASSWORD_BCRYPT, ['cost' => 4]);

        $this->assertTrue(PasswordHash::needsRehash($old));
    }

    public function testAFreshHashDoesNot(): void
    {
        $this->assertFalse(PasswordHash::needsRehash(PasswordHash::make('correct horse battery staple')));
    }

    public function testTheAbsentUserHashMatchesTheAlgorithmInUse(): void
    {
        $this->assertStringStartsWith('$argon2id$', PasswordHash::absentUserHash());
        $this->assertStringStartsWith('$argon2id$', PasswordHash::absentUserHash('argon2id'));
        $this->assertStringStartsWith('$2y$', PasswordHash::absentUserHash('2y'));
    }

    public function testTheAbsentUserHashesAreRealHashesAtTodaysCost(): void
    {
        // A malformed one would make password_verify() return at once, and
        // an outdated cost would make a missing user answer faster or slower.
        $this->assertFalse(password_needs_rehash(PasswordHash::absentUserHash('argon2id'), 'argon2id'));
        $this->assertFalse(password_needs_rehash(PasswordHash::absentUserHash('2y'), '2y'));
        $this->assertTrue(password_verify('never a real password', PasswordHash::absentUserHash('argon2id')));
        $this->assertTrue(password_verify('never a real password', PasswordHash::absentUserHash('2y')));
    }
}
