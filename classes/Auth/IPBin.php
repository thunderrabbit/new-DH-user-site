<?php

declare(strict_types=1);

namespace Auth;

class IPBin
{
    public static function ipToBinary(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6 address
            $binary = inet_pton($ip) ?: '';
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4 address
            $binary = inet_pton($ip) ?: '';
        } else {
            // Invalid IP address
            $binary = '';
        }
        return $binary;
    }
    public static function binaryToIp(
        string $binary
    ): string {
        if (strlen($binary) === 16) {
            // IPv6 address
            $ip = inet_ntop($binary) ?: '';
        } elseif (strlen($binary) === 4) {
            // IPv4 address
            $ip = inet_ntop($binary) ?: '';
        } else {
            // Invalid binary data
            $ip = '';
        }
        return $ip;
    }
}
