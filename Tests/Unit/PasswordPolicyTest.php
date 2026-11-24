<?php

declare(strict_types=1);

namespace Tests\Unit;

use Auth\PasswordPolicy;
use Codeception\Test\Unit;

class PasswordPolicyTest extends Unit
{
    public function testFifteenCharactersIsEnough(): void
    {
        $this->assertSame([], PasswordPolicy::problems(str_repeat('a', 15)));
    }

    public function testFourteenCharactersIsRefused(): void
    {
        $this->assertSame(
            ["Password must be at least 15 characters. Several unrelated words work well."],
            PasswordPolicy::problems(str_repeat('a', 14))
        );
    }

    public function testLengthIsCountedInCharactersNotBytes(): void
    {
        // Fourteen characters, twenty-eight bytes: still too short.
        $this->assertSame([PasswordPolicy::TOO_SHORT], PasswordPolicy::problems(str_repeat('é', 14)));
        $this->assertSame([], PasswordPolicy::problems(str_repeat('é', 15)));
    }

    public function testThereAreNoCompositionRules(): void
    {
        // One repeated lower-case letter, a phrase with spaces, and digits
        // only: all fine. Length is the only thing asked for.
        $this->assertSame([], PasswordPolicy::problems('correct horse battery staple'));
        $this->assertSame([], PasswordPolicy::problems('123456789012345'));
    }

    public function testUnderArgon2idAPasswordPastBcryptsLimitIsFine(): void
    {
        $this->assertSame([], PasswordPolicy::problems(str_repeat('a', 73), 'argon2id'));
        $this->assertSame([], PasswordPolicy::problems(str_repeat('é', 37), 'argon2id'));
    }

    public function testUnderArgon2idTheBoundIsAThousandAndTwentyFourCharacters(): void
    {
        $this->assertSame([], PasswordPolicy::problems(str_repeat('a', 1024), 'argon2id'));
        $this->assertSame(
            ["Password is too long: the limit is 1024 characters."],
            PasswordPolicy::problems(str_repeat('a', 1025), 'argon2id')
        );
    }

    public function testThatBoundIsCountedInCharactersNotBytes(): void
    {
        // 1024 characters, 2048 bytes.
        $this->assertSame([], PasswordPolicy::problems(str_repeat('é', 1024), 'argon2id'));
    }

    public function testTheAlgorithmInUseIsTheDefault(): void
    {
        // The runner's PHP has argon2id, so 73 bytes is fine without saying so.
        $this->assertSame([], PasswordPolicy::problems(str_repeat('a', 73)));
    }

    public function testUnderBcryptSeventyTwoBytesIsTheMostItReads(): void
    {
        $this->assertSame([], PasswordPolicy::problems(str_repeat('a', 72), '2y'));
        $this->assertSame(
            ["Password is too long: the limit is 72 bytes, which is 72 plain letters, or fewer accented ones."],
            PasswordPolicy::problems(str_repeat('a', 73), '2y')
        );
    }

    public function testUnderBcryptTheMaximumIsCountedInBytes(): void
    {
        // Thirty-seven characters, seventy-four bytes: bcrypt would drop the last one.
        $this->assertSame(
            [PasswordPolicy::TOO_LONG_FOR_BCRYPT],
            PasswordPolicy::problems(str_repeat('é', 37), '2y')
        );
    }

    public function testUnderBcryptTheArgon2idBoundDoesNotAlsoApply(): void
    {
        $this->assertSame(
            [PasswordPolicy::TOO_LONG_FOR_BCRYPT],
            PasswordPolicy::problems(str_repeat('a', 1025), '2y')
        );
    }

    public function testANulByteIsRefusedBeforePasswordHashCanThrowOnIt(): void
    {
        $this->assertSame(
            ["Password contains a character that cannot be used."],
            PasswordPolicy::problems("long enough but\0broken")
        );
    }

    public function testEveryProblemIsReportedAtOnce(): void
    {
        $this->assertSame(
            [PasswordPolicy::TOO_SHORT, PasswordPolicy::UNUSABLE],
            PasswordPolicy::problems("short\0")
        );
    }
}
