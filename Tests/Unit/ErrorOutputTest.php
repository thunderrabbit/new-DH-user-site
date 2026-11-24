<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

class ErrorOutputTest extends Unit
{
    private function thrownWith(string $secret): \Throwable
    {
        return new \RuntimeException('the database went away');
    }

    public function testTheLogLineNamesTheExceptionAndWhereItWasThrown(): void
    {
        $line = \Security\ErrorOutput::logLine($this->thrownWith('hunter2'));

        $this->assertStringStartsWith('Uncaught RuntimeException: the database went away in ', $line);
        $this->assertStringEndsWith(__FILE__ . ':13', $line);
    }

    public function testTheLogLineLeavesOutTheStackTraceAndItsArguments(): void
    {
        $line = \Security\ErrorOutput::logLine($this->thrownWith('hunter2'));

        $this->assertStringNotContainsString('hunter2', $line);
        $this->assertStringNotContainsString('#0', $line);
        $this->assertStringNotContainsString("\n", $line);
    }
}
