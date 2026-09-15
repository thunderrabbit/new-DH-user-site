<?php

declare(strict_types=1);

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

/**
 * Set up by prepend.php.
 *
 * @var \Database\DBExistaroo $dbExistaroo
 * @var \Auth\IsLoggedIn $is_logged_in
 */

header("Content-Type: application/json");

if (!$is_logged_in->isLoggedIn() || !$is_logged_in->isAdmin()) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$input = json_decode((string) file_get_contents("php://input"), true);
$migration = is_array($input) ? ($input['migration'] ?? null) : null;

if (!is_string($migration) || $migration === '') {
    http_response_code(400);
    echo json_encode(["error" => "Missing migration identifier"]);
    exit;
}

try {
    $dbExistaroo->applyMigration($migration);
    echo json_encode(["status" => "success", "applied" => $migration]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
