<?php

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
        if (!preg_match('#^[0-9]{2}_[a-zA-Z0-9_-]+/create_[a-zA-Z0-9_-]+\.sql$#', $versionWithFile)) {
            throw new \Exception("Invalid migration path format: $versionWithFile");
        }

        $fullPath = $this->app_path . "/db_schemas/" . $versionWithFile;

        // Resolve real paths and check containment
        $realBase = realpath($this->app_path . "/db_schemas");
        $realTarget = realpath($fullPath);

        if (!$realTarget || strpos($realTarget, $realBase) !== 0) {
            throw new \Exception("Resolved path escapes base directory: $versionWithFile");
        }

        if (!file_exists(filename: $realTarget)) {
            throw new \Exception(message: "Migration file does not exist: $realTarget");
        }

        return $realTarget;
    }
}
