<?php

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

header("Content-Type: application/json");

if (!$is_logged_in->isLoggedIn() || !$is_logged_in->isAdmin()) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (empty($input['migration'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing migration identifier"]);
    exit;
}

try {
    $dbExistaroo->applyMigration($input['migration']);
    echo json_encode(["status" => "success", "applied" => $input['migration']]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
