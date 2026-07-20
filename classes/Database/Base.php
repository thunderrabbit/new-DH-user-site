<?php

namespace Database;

const CONFIG_DATABASE_OUTPUT_ENCODING = "utf8mb4";

class Base
{
    private static $pdo;

    // Modern database access using native PDO interface
    private static function initDB(\Config $config)
    {
        /** START - Database **/
        if (empty(self::$pdo)) {
            $dsn = "mysql:host={$config->dbHost}";
            if (!empty($config->dbName)) {
                $dsn .= ";dbname={$config->dbName}";
            }
            $dsn .= ";charset=" . CONFIG_DATABASE_OUTPUT_ENCODING;

            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$pdo = new \PDO($dsn, $config->dbUser, $config->dbPass, $options);

                // Set timezone
                $now = new \DateTime();
                $mins = $now->getOffset() / 60;
                $sgn = ($mins < 0 ? -1 : 1);
                $mins = abs($mins);
                $hrs = floor($mins / 60);
                $mins -= $hrs * 60;
                $offset = sprintf('%+d:%02d', $hrs * $sgn, $mins);
                self::$pdo->exec("SET time_zone='$offset'");
            } catch (\PDOException $e) {
                // Try once more with a sleep (mimic original behavior)
                sleep(1);
                try {
                    self::$pdo = new \PDO($dsn, $config->dbUser, $config->dbPass, $options);
                    // Set timezone again
                    $now = new \DateTime();
                    $mins = $now->getOffset() / 60;
                    $sgn = ($mins < 0 ? -1 : 1);
                    $mins = abs($mins);
                    $hrs = floor($mins / 60);
                    $mins -= $hrs * 60;
                    $offset = sprintf('%+d:%02d', $hrs * $sgn, $mins);
                    self::$pdo->exec("SET time_zone='$offset'");
                } catch (\PDOException $e2) {
                    throw new \Database\EDatabaseException("Could not connect to server after trying with 1s sleep: " . $e2->getMessage());
                }
            }
        }
        /** END - Database **/
    }

    public static function getPDO(\Config $config): \PDO
    {
        self::initDB($config);
        return self::$pdo;
    }

    /**
     * Check if database exists using native PDO
     */
    public static function databaseExists(\Config $config): bool
    {
        try {
            // Connect without database name to check if server is reachable
            $dsn = "mysql:host={$config->dbHost};charset=" . CONFIG_DATABASE_OUTPUT_ENCODING;
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ];

            $conn = new \PDO($dsn, $config->dbUser, $config->dbPass, $options);
        } catch (\PDOException $e) {
            throw new \Database\ECouldNotConnectToServer("Check Config because " . $e->getMessage());
        }

        try {
            $stmt = $conn->prepare("SHOW DATABASES LIKE ?");
            $stmt->execute([$config->dbName]);
            $result = $stmt->fetchAll();

            if (count($result) === 0) {
                throw new \Database\EDatabaseMissing("Database '{$config->dbName}' not found.");
            }

            return count($result) > 0;
        } catch (\PDOException $e) {
            throw new \Database\EDatabaseException("Failed to query for DB existence: " . $e->getMessage());
        }
    }

    /**
     * Execute multiple SQL statements from a string (for schema migrations)
     * Splits on semicolons and executes each statement separately
     */
    public static function executeMultipleSQL(\PDO $pdo, string $sql): void
    {
        // Split SQL into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function ($stmt) {
                return !empty($stmt);
            }
        );

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                } catch (\PDOException $e) {
                    throw new \Database\EDatabaseException("Error executing statement: $statement. Error: " . $e->getMessage());
                }
            }
        }
    }
}
