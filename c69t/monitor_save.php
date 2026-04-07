<?php
require_once "config.php";
requireRole(['admin', 'operator']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$allowedKeys = [
    'monitor_master',
    'monitor_refresh_seconds',
    'monitor_nozzle_enabled',
    'monitor_nozzle_minutes',
    'monitor_tricanter_enabled',
    'monitor_tricanter_minutes',
    'monitor_solid_waste_enabled',
    'monitor_solid_waste_minutes',
    'monitor_sample_enabled',
    'monitor_sample_minutes',
    'monitor_gas_test_enabled',
    'monitor_gas_test_minutes',
];

foreach ($allowedKeys as $key) {
    if (array_key_exists($key, $input)) {
        setSetting($pdo, $key, (string)$input[$key]);
    }
}

echo json_encode(['ok' => true]);