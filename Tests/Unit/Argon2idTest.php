<?php

declare(strict_types=1);

namespace Tests\Unit;

use Auth\Argon2id;
use Codeception\Test\Unit;

class Argon2idTest extends Unit
{
    public function testItIsListedWhenPhpNamesIt(): void
    {
        $this->assertTrue(Argon2id::isListed(['2y', 'argon2i', 'argon2id']));
    }

    public function testArgon2iAloneIsNotArgon2id(): void
    {
        $this->assertFalse(Argon2id::isListed(['2y', 'argon2i']));
    }

    public function testAPhpWithOnlyBcryptSaysSo(): void
    {
        $this->assertSame('bcrypt only; this PHP has no argon2id', Argon2id::summary(false, null));
    }

    public function testATrialIsIgnoredWhenTheAlgorithmIsNotListed(): void
    {
        $this->assertSame('bcrypt only; this PHP has no argon2id', Argon2id::summary(false, 80));
    }

    public function testListedButFailingIsItsOwnAnswer(): void
    {
        $this->assertSame('argon2id is listed, but a test hash failed', Argon2id::summary(true, null));
    }

    public function testAWorkingHashReportsHowLongItTook(): void
    {
        $this->assertSame('argon2id available (a test hash took 80 ms)', Argon2id::summary(true, 80));
    }

    public function testTheTrialMakesARealHashWhereThisPhpCan(): void
    {
        if (!Argon2id::isListed()) {
            $this->markTestSkipped('this PHP has no argon2id');
        }
        $this->assertIsInt(Argon2id::trialMilliseconds());
        $this->assertStringStartsWith('argon2id available', Argon2id::report());
    }
}
