<?php
require_once "config.php";
require_once __DIR__ . '/tank_height_service.php';
requireRole(['admin']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $data = tankHeightSnapshot($pdo);
    unset($data["settings"], $data["latest"], $data["latest_rate"]);
    echo json_encode(["ok" => true] + $data, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
