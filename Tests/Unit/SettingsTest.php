<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Config\EConfigInvalid;
use Config\Settings;

class SettingsTest extends Unit
{
    /** @return array<string, mixed> */
    private function minimal(): array
    {
        return [
            'dbHost' => 'mysql.example.com',
            'dbName' => 'site_db',
            'dbUser' => 'site_user',
            'dbPass' => 'correct horse battery staple',
            'domain_name' => 'example.com',
            'cookie_name' => 'example_auth',
        ];
    }

    public function testMinimalFileTakesTheShippedDefaults(): void
    {
        $settings = Settings::fromArray($this->minimal(), '/home/u/example.com');

        $this->assertSame('/home/u/example.com', $settings->app_path);
        $this->assertSame('Site', $settings->site_title);
        $this->assertTrue($settings->allow_registration);
        $this->assertFalse($settings->display_errors);
        $this->assertSame(60 * 60 * 24 * 30, $settings->cookie_lifetime);
    }

    public function testGivenValuesReplaceTheDefaults(): void
    {
        $settings = Settings::fromArray(
            [
                'site_title' => 'Moon',
                'allow_registration' => false,
                'display_errors' => true,
                'cookie_lifetime' => 3600,
            ] + $this->minimal(),
            '/home/u/example.com',
        );

        $this->assertSame('Moon', $settings->site_title);
        $this->assertFalse($settings->allow_registration);
        $this->assertTrue($settings->display_errors);
        $this->assertSame(3600, $settings->cookie_lifetime);
    }

    public function testRefusesAMisspelledKey(): void
    {
        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage("unknown key: 'allow_registraton'");
        Settings::fromArray(['allow_registraton' => false] + $this->minimal(), '/r');
    }

    public function testRefusesAnAppPathFromTheFile(): void
    {
        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage("unknown key: 'app_path'");
        Settings::fromArray(['app_path' => '/home/u/sibling.com'] + $this->minimal(), '/r');
    }

    public function testRefusesAMissingRequiredKey(): void
    {
        $values = $this->minimal();
        unset($values['cookie_name']);

        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage("'cookie_name' is missing");
        Settings::fromArray($values, '/r');
    }

    public function testRefusesAnEmptyPassword(): void
    {
        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage("'dbPass' must not be empty");
        Settings::fromArray(['dbPass' => '  '] + $this->minimal(), '/r');
    }

    public function testRefusesAnEmptyAppPath(): void
    {
        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage("'app_path' must not be empty");
        Settings::fromArray($this->minimal(), '');
    }

    public function testRefusesAStringWhereABoolBelongs(): void
    {
        // 'false' is a non-empty string, so a loose check would read it as true.
        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage("'allow_registration' must be true or false, not string");
        Settings::fromArray(['allow_registration' => 'false'] + $this->minimal(), '/r');
    }

    public function testRefusesAnExplicitNullInsteadOfDefaultingIt(): void
    {
        // `??` reads a present null as absent, which handed back the default.
        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage("'allow_registration' must be true or false, not null");
        Settings::fromArray(['allow_registration' => null] + $this->minimal(), '/r');
    }

    public function testRefusesAnExplicitNullForAnOptionalString(): void
    {
        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage("'site_title' must be a string, not null");
        Settings::fromArray(['site_title' => null] + $this->minimal(), '/r');
    }

    public function testWrongTypeMessageLeavesTheValueOut(): void
    {
        try {
            Settings::fromArray(['dbPass' => 12345678] + $this->minimal(), '/r');
            $this->fail('An int password was accepted');
        } catch (EConfigInvalid $e) {
            $this->assertSame("Config 'dbPass' must be a string, not int", $e->getMessage());
        }
    }

    public function testRefusesACookieNameThatIsNotAToken(): void
    {
        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage("'cookie_name' must be letters");
        Settings::fromArray(['cookie_name' => 'a;b'] + $this->minimal(), '/r');
    }

    public function testAcceptsACookieLifetimeAtEachEnd(): void
    {
        $short = Settings::fromArray(['cookie_lifetime' => 60] + $this->minimal(), '/r');
        $long = Settings::fromArray(
            ['cookie_lifetime' => Settings::MAX_COOKIE_LIFETIME] + $this->minimal(),
            '/r',
        );

        $this->assertSame(60, $short->cookie_lifetime);
        $this->assertSame(Settings::MAX_COOKIE_LIFETIME, $long->cookie_lifetime);
    }

    public function testRefusesALifetimeWrittenAsAString(): void
    {
        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage("'cookie_lifetime' must be a whole number, not string");
        Settings::fromArray(['cookie_lifetime' => '3600'] + $this->minimal(), '/r');
    }

    public function testRefusesACookieLifetimeUnderAMinute(): void
    {
        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage("'cookie_lifetime' must be between");
        Settings::fromArray(['cookie_lifetime' => 59] + $this->minimal(), '/r');
    }

    public function testRefusesACookieLifetimeOver400Days(): void
    {
        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage("'cookie_lifetime' must be between");
        Settings::fromArray(
            ['cookie_lifetime' => Settings::MAX_COOKIE_LIFETIME + 1] + $this->minimal(),
            '/r',
        );
    }

    public function testCannotBeChangedOnceBuilt(): void
    {
        $settings = Settings::fromArray($this->minimal(), '/r');

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('readonly');
        // @phpstan-ignore assign.propertyProtectedSet (the point of the test)
        $settings->allow_registration = false;
    }

    public function testDumpHidesThePassword(): void
    {
        $dump = print_r(Settings::fromArray($this->minimal(), '/r'), true);

        $this->assertStringNotContainsString('correct horse', $dump);
        $this->assertStringContainsString('(hidden)', $dump);
        $this->assertStringContainsString('site_user', $dump);
    }
}
