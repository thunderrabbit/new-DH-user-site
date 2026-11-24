<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

class PhpFloorTest extends Unit
{
    public function testTheLastPhpBelowTheFloorIsRefused(): void
    {
        $this->assertFalse(\Version\Php::meetsFloor(80599));
    }

    public function testTheFloorItselfIsAccepted(): void
    {
        $this->assertTrue(\Version\Php::meetsFloor(80600));
    }

    public function testLaterVersionsAreAccepted(): void
    {
        $this->assertTrue(\Version\Php::meetsFloor(90000));
    }
}
