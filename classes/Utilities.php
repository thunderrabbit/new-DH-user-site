<?php

class Utilities
{
    public static function randomString(
        int $length,
        string $possible = ""
    ): string {
        $randString = "";
        // define possible characters
        if (empty($possible)) {
            $possible = "0123456789abcdfghjkmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ";
        }
        // add random characters
        for ($i = 0; $i < $length; $i++) {
            // pick a random character from the possible ones
            $char = substr($possible, random_int(0, strlen($possible) - 1), 1);
            $randString .= $char;
        }
        return $randString;
    }

    /**
     * Used in DBExistaroo::applyMigration() as
     * $path = \Utilities::getSchemaFilePath($this->config->app_path, $versionWithFile);
     *
     * @param string $appPath
     * @param string $versionWithFile
     * @throws \Exception
     * @return bool|string
     */
    public static function getSchemaFilePath(string $appPath, string $versionWithFile): string
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

        $fullPath = $appPath . "/db_schemas/" . $versionWithFile;

        // Resolve real paths and check containment
        $realBase = realpath($appPath . "/db_schemas");
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
