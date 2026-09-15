<?php

declare(strict_types=1);

namespace Database;

/**
 * Resolves a migration's relative path (e.g. "00_bedrock/create_users.sql")
 * to a validated absolute path under $app_path/db_schemas.
 * Kept free of PDO/Config so the checks stay unit-testable without a DB.
 */
class SchemaPath
{
    public function __construct(
        private string $app_path,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function resolve(string $versionWithFile): string
    {
        // Sanitize and validate relative path
        if (empty($versionWithFile)) {
            throw new \Exception("Migration version with file cannot be empty.");
        }
        if (strpos($versionWithFile, '..') !== false) {
            throw new \Exception("Invalid migration path (traversal not allowed): $versionWithFile");
        }
        // Shape only, deliberately not `create_`: a migration may ALTER an existing
        // table as readily as create a new one. This is a traversal guard on a string
        // that arrives in a JSON POST body, not a naming convention - the anchors and
        // the dot-free character class are what matter. Initial schemas are the ones
        // held to `create_*.sql`, in DBExistaroo::applyInitialSchemas().
        if (!preg_match('#^[0-9]{2}_[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+\.sql$#', $versionWithFile)) {
            throw new \Exception("Invalid migration path format: $versionWithFile");
        }

        $fullPath = $this->app_path . "/db_schemas/" . $versionWithFile;

        // Resolve real paths and check containment
        $realBase = realpath($this->app_path . "/db_schemas");
        $realTarget = realpath($fullPath);

        if ($realBase === false || $realTarget === false || !str_starts_with($realTarget, $realBase)) {
            throw new \Exception("Resolved path escapes base directory: $versionWithFile");
        }

        if (!file_exists(filename: $realTarget)) {
            throw new \Exception(message: "Migration file does not exist: $realTarget");
        }

        return $realTarget;
    }
}
