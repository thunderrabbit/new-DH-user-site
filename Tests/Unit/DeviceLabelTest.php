<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

class DeviceLabelTest extends Unit
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function userAgents(): array
    {
        return [
            'iPhone Safari' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 '
                    . '(KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
                'iPhone · Safari',
            ],
            'iPhone Chrome' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 '
                    . '(KHTML, like Gecko) CriOS/126.0.6478.54 Mobile/15E148 Safari/604.1',
                'iPhone · Chrome',
            ],
            'iPad Firefox' => [
                'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 '
                    . '(KHTML, like Gecko) FxiOS/127.0 Mobile/15E148 Safari/605.1.15',
                'iPad · Firefox',
            ],
            'Android Chrome' => [
                'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 '
                    . '(KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
                'Android · Chrome',
            ],
            'Android Samsung Internet' => [
                'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 '
                    . '(KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0.0.0 Mobile Safari/537.36',
                'Android · Samsung Internet',
            ],
            'Windows Edge' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                    . '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0',
                'Windows · Edge',
            ],
            'Windows Opera' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                    . '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 OPR/111.0.0.0',
                'Windows · Opera',
            ],
            'Mac Safari' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 '
                    . '(KHTML, like Gecko) Version/17.5 Safari/605.1.15',
                'Mac · Safari',
            ],
            'Linux Firefox' => [
                'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0',
                'Linux · Firefox',
            ],
            'ChromeOS Chrome' => [
                'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 '
                    . '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                'ChromeOS · Chrome',
            ],
            'curl' => ['curl/8.5.0', 'Unknown device'],
            'nothing recorded' => ['', 'Unknown device'],
        ];
    }

    /**
     * @dataProvider userAgents
     */
    public function testNamesTheDeviceAndBrowser(string $user_agent, string $expected): void
    {
        $this->assertSame($expected, \Auth\DeviceLabel::fromUserAgent($user_agent));
    }

    public function testAKnownBrowserOnAnUnknownSystemStillSaysWhichBrowser(): void
    {
        $this->assertSame('Firefox', \Auth\DeviceLabel::fromUserAgent('Mozilla/5.0 (Haiku) Firefox/128.0'));
    }
}
