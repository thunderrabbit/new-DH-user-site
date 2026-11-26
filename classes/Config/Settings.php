<?php

declare(strict_types=1);

namespace Config;

/**
 * The site's configuration, checked once and then fixed for the request.
 *
 * Built by Config\Loader from the server's gitignored site_config.php, which
 * returns an array holding only this site's values (see
 * site_config.sample.php). Every key not given takes the default below, so a
 * property added here reaches every server without anyone editing its file.
 *
 * The five keys without a default (database credentials and domain_name)
 * plus cookie_name must be set; a site missing one answers 503 instead of
 * running half-configured. A key this class does not know is refused, so a
 * typo such as 'allow_registraton' cannot silently leave sign-up open.
 *
 * app_path is never configured: the loader passes the project root it is
 * running from, so a file copied from a sibling site cannot point this one
 * at the other's templates, schemas or bootstrap token.
 */
final readonly class Settings
{
    /** Chrome refuses a cookie lifetime longer than this, so we do too. */
    public const int MAX_COOKIE_LIFETIME = 400 * 24 * 60 * 60;

    /** The keys site_config.php may set: everything but app_path. */
    public const array KEYS = [
        'dbHost',
        'dbName',
        'dbUser',
        'dbPass',
        'domain_name',
        'cookie_name',
        'site_title',
        'allow_registration',
        'display_errors',
        'cookie_lifetime',
    ];

    /**
     * @throws EConfigInvalid naming the first bad key
     */
    public function __construct(
        public string $app_path,
        public string $dbHost,
        public string $dbName,
        public string $dbUser,
        #[\SensitiveParameter]
        public string $dbPass,
        // Must equal the browser's HTTP_HOST, or DBExistaroo answers 503.
        public string $domain_name,
        // The login cookie; the browser sees it as __Host-<this>.
        public string $cookie_name,
        // Shown in <title> and the front-page heading.
        public string $site_title = 'Site',
        // May strangers create their own role='user' account at
        // /login/register.php? The first (admin) account is always allowed,
        // gated by bootstrap_token.txt.
        public bool $allow_registration = true,
        // Print PHP errors and uncaught exceptions in the page? They are
        // always logged. Leave false on any site a stranger can reach.
        public bool $display_errors = false,
        public int $cookie_lifetime = 60 * 60 * 24 * 30,
    ) {
        $required = [
            'app_path' => $app_path,
            'dbHost' => $dbHost,
            'dbName' => $dbName,
            'dbUser' => $dbUser,
            'dbPass' => $dbPass,
            'domain_name' => $domain_name,
        ];
        foreach ($required as $key => $value) {
            if (trim($value) === '') {
                throw new EConfigInvalid("Config '$key' must not be empty");
            }
        }
        // A cookie name is an RFC 6265 token; these are the safe ones.
        if (preg_match('/^[A-Za-z0-9._-]+$/', $cookie_name) !== 1) {
            throw new EConfigInvalid("Config 'cookie_name' must be letters, digits, '.', '_' or '-'");
        }
        if ($cookie_lifetime < 60 || $cookie_lifetime > self::MAX_COOKIE_LIFETIME) {
            throw new EConfigInvalid("Config 'cookie_lifetime' must be between 60 seconds and 400 days");
        }
    }

    /**
     * Build from what site_config.php returned.
     *
     * @param array<mixed> $values
     * @throws EConfigInvalid naming the first bad key
     */
    public static function fromArray(array $values, string $app_path): self
    {
        foreach (array_keys($values) as $key) {
            if (!in_array($key, self::KEYS, true)) {
                throw new EConfigInvalid('Config has an unknown key: ' . var_export($key, true));
            }
        }
        // The constructor's defaults, read off a throwaway instance so they
        // are written in one place only.
        $defaults = new self(
            app_path: '-',
            dbHost: '-',
            dbName: '-',
            dbUser: '-',
            dbPass: '-',
            domain_name: '-',
            cookie_name: '-',
        );
        return new self(
            app_path: $app_path,
            dbHost: self::string($values, 'dbHost'),
            dbName: self::string($values, 'dbName'),
            dbUser: self::string($values, 'dbUser'),
            dbPass: self::string($values, 'dbPass'),
            domain_name: self::string($values, 'domain_name'),
            cookie_name: self::string($values, 'cookie_name'),
            site_title: self::string($values, 'site_title', $defaults->site_title),
            allow_registration: self::bool($values, 'allow_registration', $defaults->allow_registration),
            display_errors: self::bool($values, 'display_errors', $defaults->display_errors),
            cookie_lifetime: self::int($values, 'cookie_lifetime', $defaults->cookie_lifetime),
        );
    }

    /** @param array<mixed> $values */
    private static function string(array $values, string $key, ?string $default = null): string
    {
        if (!array_key_exists($key, $values)) {
            return $default ?? throw new EConfigInvalid("Config '$key' is missing");
        }
        $value = $values[$key];
        return is_string($value) ? $value : throw self::wrongType($key, 'a string', $value);
    }

    /** @param array<mixed> $values */
    private static function bool(array $values, string $key, bool $default): bool
    {
        $value = self::given($values, $key, $default);
        return is_bool($value) ? $value : throw self::wrongType($key, 'true or false', $value);
    }

    /** @param array<mixed> $values */
    private static function int(array $values, string $key, int $default): int
    {
        $value = self::given($values, $key, $default);
        return is_int($value) ? $value : throw self::wrongType($key, 'a whole number', $value);
    }

    /**
     * The value as written, null included; the default only when the key is
     * absent. `??` would read `'allow_registration' => null` as absent.
     *
     * @param array<mixed> $values
     */
    private static function given(array $values, string $key, mixed $default): mixed
    {
        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    private static function wrongType(string $key, string $wanted, mixed $value): EConfigInvalid
    {
        // The type only: the value may be the password.
        return new EConfigInvalid("Config '$key' must be $wanted, not " . get_debug_type($value));
    }

    /**
     * var_dump(), print_r() and printRob() of the config show everything but
     * the password. var_export() and json_encode() ignore this: don't.
     *
     * @return array<mixed>
     */
    public function __debugInfo(): array
    {
        return ['dbPass' => '(hidden)'] + get_object_vars($this);
    }
}
