<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

class IPBinTest extends Unit
{
    // === ipToBinary tests ===

    public function testIpv4ToBinaryIsFourBytes(): void
    {
        $binary = \Auth\IPBin::ipToBinary('192.168.1.1');
        $this->assertEquals(4, strlen($binary));
    }

    public function testIpv6ToBinaryIsSixteenBytes(): void
    {
        $binary = \Auth\IPBin::ipToBinary('2001:db8::1');
        $this->assertEquals(16, strlen($binary));
    }

    public function testInvalidIpToBinaryIsEmptyString(): void
    {
        $this->assertSame('', \Auth\IPBin::ipToBinary('not.an.ip.address'));
        $this->assertSame('', \Auth\IPBin::ipToBinary(''));
        $this->assertSame('', \Auth\IPBin::ipToBinary('999.999.999.999'));
    }

    // === binaryToIp tests ===

    public function testBinaryToIpRejectsWrongLengths(): void
    {
        $this->assertSame('', \Auth\IPBin::binaryToIp(''));
        $this->assertSame('', \Auth\IPBin::binaryToIp('abc'));
        $this->assertSame('', \Auth\IPBin::binaryToIp(str_repeat("\x00", 5)));
    }

    // === round-trip tests ===

    public function testIpv4RoundTrip(): void
    {
        foreach (['127.0.0.1', '10.0.0.1', '255.255.255.255', '0.0.0.0'] as $ip) {
            $this->assertSame($ip, \Auth\IPBin::binaryToIp(\Auth\IPBin::ipToBinary($ip)));
        }
    }

    public function testIpv6RoundTrip(): void
    {
        // inet_ntop returns the canonical compressed form
        foreach (['2001:db8::1', '::1', 'fe80::1'] as $ip) {
            $this->assertSame($ip, \Auth\IPBin::binaryToIp(\Auth\IPBin::ipToBinary($ip)));
        }
    }
}
