<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

class LocalPathTest extends Unit
{
    public function testAcceptsPathsOnThisSite(): void
    {
        $this->assertTrue(\Admin\LocalPath::isLocal('/'));
        $this->assertTrue(\Admin\LocalPath::isLocal('/tqiq/'));
        $this->assertTrue(\Admin\LocalPath::isLocal('/admin/migrate_tables.php?a=1&b=2'));
    }

    public function testRefusesAnythingThatCouldBeAnotherHost(): void
    {
        $this->assertFalse(\Admin\LocalPath::isLocal(''));
        $this->assertFalse(\Admin\LocalPath::isLocal('tqiq/'));
        $this->assertFalse(\Admin\LocalPath::isLocal('//example.com/'));
        $this->assertFalse(\Admin\LocalPath::isLocal('/\\example.com/'));
        $this->assertFalse(\Admin\LocalPath::isLocal('https://example.com/'));
        $this->assertFalse(\Admin\LocalPath::isLocal('javascript:alert(1)'));
        $this->assertFalse(\Admin\LocalPath::isLocal("/a\nb"));
        $this->assertFalse(\Admin\LocalPath::isLocal('/a b'));
    }

    public function testRefusesPathsWiderThanTheLinkColumn(): void
    {
        $this->assertTrue(\Admin\LocalPath::isLocal('/' . str_repeat('a', \Admin\LocalPath::MAX - 1)));
        $this->assertFalse(\Admin\LocalPath::isLocal('/' . str_repeat('a', \Admin\LocalPath::MAX)));
    }
}
