<?php

declare(strict_types=1);

namespace Config;

/**
 * Finds the server's configuration and turns it into a Config\Settings.
 *
 * The configuration is site_config.php in the project root (beside
 * bootstrap_token.txt, above the web root), gitignored, returning an array
 * of only this site's values. See site_config.sample.php.
 *
 * A server set up before site_config.php existed has the old
 * classes/Config/Config.php instead, a class with a property per value. That
 * still works, so pulling this code takes no site down; README says how to
 * convert one. Remove the fallback once every site has converted.
 */
final class Loader
{
    public const string FILE = 'site_config.php';
    public const string LEGACY_FILE = 'classes/Config/Config.php';

    /**
     * @param string $root the project root, the directory holding prepend.php
     * @throws EConfigInvalid when neither file is there, or what it holds is wrong
     */
    public static function load(string $root): Settings
    {
        $file = $root . '/' . self::FILE;
        if (is_file($file)) {
            return Settings::fromArray(self::read($file), $root);
        }
        $legacy_file = $root . '/' . self::LEGACY_FILE;
        if (is_file($legacy_file)) {
            return self::fromLegacy(self::instantiate($legacy_file), $root);
        }
        throw new EConfigInvalid('No ' . self::FILE . ' in ' . $root);
    }

    /**
     * An old Config object's values, as Settings would have them.
     *
     * Its app_path is dropped, like any key Settings does not know: an old file
     * may carry both, and refusing them would take a working site down. That is
     * the old leniency, kept only until the server converts.
     */
    public static function fromLegacy(object $legacy, string $root): Settings
    {
        $values = array_intersect_key(get_object_vars($legacy), array_flip(Settings::KEYS));
        return Settings::fromArray($values, $root);
    }

    /**
     * @return array<mixed>
     * @throws EConfigInvalid when the file returns anything but an array
     */
    private static function read(string $file): array
    {
        $values = self::requireFile($file);
        if (!is_array($values)) {
            throw new EConfigInvalid(self::FILE . ' must return an array');
        }
        return $values;
    }

    /**
     * A typo in the file is a ParseError, which quotes the code it choked on,
     * and that may be the password. Only where it is goes into the message.
     *
     * @throws EConfigInvalid when the file does not parse
     */
    private static function requireFile(string $file): mixed
    {
        try {
            return require $file;
        } catch (\ParseError $e) {
            throw new EConfigInvalid("$file does not parse, line " . $e->getLine());
        }
    }

    /** @throws EConfigInvalid when the file does not declare the class */
    private static function instantiate(string $legacy_file): object
    {
        $class = 'Config\Config';
        if (!class_exists($class, false)) {
            self::requireFile($legacy_file);
        }
        if (!class_exists($class, false)) {
            throw new EConfigInvalid(self::LEGACY_FILE . ' does not declare ' . $class);
        }
        return new $class();
    }
}
