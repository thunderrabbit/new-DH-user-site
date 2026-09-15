<?php

namespace Database;

class DBExistaroo
{
    private const AUTOMATIC_SCHEMA_PREFIXES = [
        "00",
        "01",
    ];

    /**
     * DBExistaroo checks if the database exists and
     * creates a table to track DB versions if needed.
     * It's then asked to create TABLE `users` and `cookies`
     * It also checks if there are any users in the users table.
     *
     * @param \Config\Config $config Configuration object containing DB connection details.
     * @param \PDO $pdo Native PDO database connection.
     * @param SchemaPath $schema_path Resolves migration files under db_schemas.
     */
    public function __construct(
        private \Config\Config $config,
        private \PDO $pdo,
        private SchemaPath $schema_path,
    ) {
    }

    /**
     * @return list<string>
     */
    public function checkaroo(): array
    {
        $errors = [];

        if (!$this->configIsValid()) {
            $errors[] = "Config missing one or more DB values:";
            $errors[] = "dbHost";
            $errors[] = "dbUser";
            $errors[] = "dbPass";
            $errors[] = "dbName";
            return $errors;
        }

        if (!$this->dbExists()) {
            $errors[] = "Database '{$this->config->dbName}' does not exist.";
            return $errors;
        }

        if (!$this->domainMatches()) {
            $errors[] = "Domain mismatch: Current domain does not match "
                . "configured domain '{$this->config->domain_name}'.";
            echo "Go fix the value of domain name in classes/Config/Config.php (and probably app_path as well).";
            return $errors;
        }

        if (!$this->appliedDBVersionsTableExists()) {
            $this->applyInitialSchemas();
        } else {
            $this->applyNewAutomaticSchemas();
        }

        if (!$this->hasAnyUsers()) {
            $errors[] = "YallGotAnyMoreOfThemUsers";
        }

        return $errors;
    }

    private function configIsValid(): bool
    {
        return !empty($this->config->dbHost)
            && !empty($this->config->dbUser)
            && !empty($this->config->dbPass)
            && !empty($this->config->dbName);
    }

    private function domainMatches(): bool
    {
        $currentDomain = $_SERVER['HTTP_HOST'] ?? '';
        return $currentDomain === $this->config->domain_name;
    }

    /**
     * Checks if the database exists using native PDO.
     * databaseExists() will die if it cannot access the server or log in.
     * We catch EDatabaseMissing if the database is missing.
     * @return bool True if the database exists, false otherwise.
     */
    private function dbExists(): bool
    {
        try {
            return \Database\Base::databaseExists($this->config);
        } catch (\Database\EDatabaseMissing $e) {
            return false;
        } catch (\Database\EDatabaseException $e) {
            throw new \Exception("Unexpected DB error: " . $e->getMessage());
        }
    }



    private function logSchemaApplication(string $version, string $direction): void
    {
        $direction = "up"; // use PHPMyAdmin to drop migrations
        $stmt = $this->pdo->prepare("INSERT INTO applied_DB_versions (applied_version, direction) VALUES (?, ?)");
        $stmt->execute([$version, $direction]);
    }

    private function appliedDBVersionsTableExists(): bool
    {
        $stmt = $this->pdo->prepare("SHOW TABLES LIKE 'applied_DB_versions'");
        $stmt->execute();
        return count($stmt->fetchAll()) > 0;
    }

    private function applyInitialSchemas(): void
    {
        foreach (self::AUTOMATIC_SCHEMA_PREFIXES as $prefix) {
            $dir = $this->config->app_path . "/db_schemas";
            $schema_dirs = glob("$dir/{$prefix}_*", GLOB_ONLYDIR) ?: [];

            foreach ($schema_dirs as $schema_dir) {
                $version = basename($schema_dir);
                // The automatic prefixes bring a database into existence, so every
                // file here is a CREATE TABLE. Later migrations are the ones that
                // ALTER, and they are named freely - see SchemaPath::resolve().
                $sql_files = glob("$schema_dir/create_*.sql") ?: [];

                foreach ($sql_files as $sql_path) {
                    $this->applySchemaPath($sql_path);
                    $this->logSchemaApplication($version . '/' . basename($sql_path), "up");
                }
            }
        }
    }

    /**
     * A file added to an automatic prefix AFTER a site was deployed used to
     * sit as "pending" until an admin clicked it, which is a trap when the
     * code that needs it ships at the same time (login_attempts, the cookies
     * expires_at column): nobody can log in to click. So on an existing
     * install, anything still unapplied under 00/01 is applied on the next
     * request, which is what "automatic" promised all along.
     *
     * Sites whose applied_DB_versions rows are bare directory names (logged
     * before 03b8b46, June 2025) are skipped: every file would look pending
     * there and CREATE TABLE would fail on every request.
     */
    private function applyNewAutomaticSchemas(): void
    {
        foreach ($this->getAppliedVersions() as $key) {
            if (!str_contains($key, '/')) {
                return;
            }
        }
        foreach ($this->getPendingMigrations() as $key) {
            if (in_array(substr($key, 0, 2), self::AUTOMATIC_SCHEMA_PREFIXES, true)) {
                $this->applyMigration($key);
            }
        }
    }

    private function applySchemaPath(string $sql_path): void
    {
        // $sql_path cannot be empty
        if (empty($sql_path)) {
            throw new \Exception("Schema path cannot be empty.");
        }
        // $sql_path must be a valid file path
        if (!file_exists($sql_path)) {
            throw new \Exception("Missing schema file: $sql_path");
        }
        $sql = file_get_contents($sql_path);
        if ($sql === false) {
            throw new \Exception("Could not read schema file: $sql_path");
        }

        // Use native PDO to execute multiple SQL statements
        \Database\Base::executeMultipleSQL($this->pdo, $sql);
    }

    private function hasAnyUsers(): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM users LIMIT 1");
            $stmt->execute();
            return count($stmt->fetchAll()) > 0;
        } catch (\PDOException $e) {
            // If users table doesn't exist, return false (no users)
            if ($e->getCode() == '42S02') { // Table doesn't exist
                return false;
            }
            // Re-throw other PDO exceptions
            throw $e;
        }
    }

    public function firstUserExistBool(): bool
    {
        return $this->hasAnyUsers();
    }

    /**
     * @return list<string>
     */
    public function getPendingMigrations(): array
    {
        $pending = [];
        $applied = $this->getAppliedVersions();
        $base_dir = $this->config->app_path . "/db_schemas";

        // Get all numbered schema directories (e.g., 00_bedrock, 01_gumdrop_cloud, 02_workers, etc.)
        $schema_dirs = glob("$base_dir/[0-9][0-9]_*", GLOB_ONLYDIR) ?: [];
        sort($schema_dirs); // Ensure they're processed in numerical order

        foreach ($schema_dirs as $schema_dir) {
            $version = basename($schema_dir);   // directory name in $base_dir, e.g. "02_workers"
            // print_rob($version, false);
            $create_files = glob("$schema_dir/*_*.sql") ?: [];

            foreach ($create_files as $file) {
                $key = "$version/" . basename($file);
                if (!in_array($key, $applied)) {
                    $pending[] = $key;
                    // echo "Pending migration found: $key<br>";
                } else {
                    // echo "Skipping already applied migration: $key<br>";
                }
            }
        }

        return $pending;
    }

    /**
     * @return list<string>
     */
    private function getAppliedVersions(): array
    {
        $versions = [];
        $stmt = $this->pdo->prepare("SELECT applied_version FROM applied_DB_versions");
        $stmt->execute();
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $version) {
            if (is_string($version)) {
                $versions[] = $version;
            }
        }
        return $versions;
    }

    public function applyMigration(string $versionWithFile): void
    {
        $path = $this->schema_path->resolve($versionWithFile);

        $this->applySchemaPath($path);
        $this->logSchemaApplication($versionWithFile, "up");
    }

    /**
     * Use PHPMyAdmin to drop migrations
     * I'm not going to write code to drop migrations.
     *
     * But if I did, I think the simplest way would be to:
     * 1. Not "log" drops in TABLE `applied_DB_versions` with logSchemaApplication
     * 2. allow only the most recent row to be dropped from TABLE `applied_DB_versions`.
     *
     * Otherwise, we need to parse a log of all the adds, drops, adds, drops, etc which is probably too annoying.
     */
}
