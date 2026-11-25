<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Config\EConfigInvalid;
use Config\Loader;

class LoaderTest extends Unit
{
    private string $root;

    protected function _before(): void
    {
        $this->root = sys_get_temp_dir() . '/loader-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0700);
    }

    protected function _after(): void
    {
        array_map(unlink(...), glob($this->root . '/*') ?: []);
        rmdir($this->root);
    }

    private function siteConfig(string $php): void
    {
        file_put_contents($this->root . '/' . Loader::FILE, $php);
    }

    public function testReadsSiteConfigAndDerivesAppPath(): void
    {
        $this->siteConfig("<?php return [
            'dbHost' => 'h', 'dbName' => 'n', 'dbUser' => 'u', 'dbPass' => 'p',
            'domain_name' => 'example.com', 'cookie_name' => 'auth',
            'allow_registration' => false,
        ];");

        $settings = Loader::load($this->root);

        $this->assertSame($this->root, $settings->app_path);
        $this->assertSame('example.com', $settings->domain_name);
        $this->assertFalse($settings->allow_registration);
    }

    public function testRefusesASiteConfigThatIsNotAnArray(): void
    {
        $this->siteConfig('<?php $dbPass = "p";');

        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage('must return an array');
        Loader::load($this->root);
    }

    public function testSaysWhatIsMissingWhenThereIsNoFile(): void
    {
        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage('No site_config.php in ' . $this->root);
        Loader::load($this->root);
    }

    public function testLegacyConfigKeepsItsValuesAndLosesItsAppPath(): void
    {
        $legacy = new class {
            public string $site_title = 'Old site';
            public bool $allow_registration = false;
            public string $domain_name = 'example.com';
            public string $cookie_name = 'auth';
            public int $cookie_lifetime = 3600;
            public string $app_path = '/home/user/sibling.com';
            public string $dbHost = 'h';
            public string $dbName = 'n';
            public string $dbUser = 'u';
            public string $dbPass = 'p';
        };

        $settings = Loader::fromLegacy($legacy, '/home/user/example.com');

        $this->assertSame('/home/user/example.com', $settings->app_path);
        $this->assertSame('Old site', $settings->site_title);
        $this->assertFalse($settings->allow_registration);
        $this->assertSame(3600, $settings->cookie_lifetime);
    }

    public function testLegacyConfigWithoutNewerPropertiesGetsTheirDefaults(): void
    {
        // Older than display_errors and allow_registration, as live sites are.
        $legacy = new class {
            public string $domain_name = 'example.com';
            public string $cookie_name = 'auth';
            public string $dbHost = 'h';
            public string $dbName = 'n';
            public string $dbUser = 'u';
            public string $dbPass = 'p';
        };

        $settings = Loader::fromLegacy($legacy, '/r');

        $this->assertTrue($settings->allow_registration);
        $this->assertFalse($settings->display_errors);
    }

    public function testLegacyConfigWithAnEmptyCookieNameStopsTheSite(): void
    {
        // ConfigSample shipped cookie_name = ''; a site that never set it fails closed.
        $legacy = new class {
            public string $domain_name = 'example.com';
            public string $cookie_name = '';
            public string $dbHost = 'h';
            public string $dbName = 'n';
            public string $dbUser = 'u';
            public string $dbPass = 'p';
        };

        $this->expectException(EConfigInvalid::class);
        $this->expectExceptionMessage("'cookie_name' must be letters");
        Loader::fromLegacy($legacy, '/r');
    }
}
