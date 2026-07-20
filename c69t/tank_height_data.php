<?php
require_once "config.php";
requireRole(['admin', 'operator']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $settingsStmt = $pdo->query("
        SELECT start_flow_total, start_height_mm, target_height_mm, conversion_factor
        FROM tricanter_height_settings
        WHERE id = 1
        LIMIT 1
    ");
    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $latestStmt = $pdo->query("
        SELECT id, log_date, log_time, total_tricanter
        FROM project_flow_logs
        WHERE total_tricanter IS NOT NULL
        ORDER BY log_date DESC, log_time DESC, id DESC
        LIMIT 1
    ");
    $latest = $latestStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $rateStmt = $pdo->query("
        SELECT id, log_date, log_time, feed_rate
        FROM tricanter_logs
        WHERE feed_rate IS NOT NULL
        ORDER BY log_date DESC, log_time DESC, id DESC
        LIMIT 1
    ");
    $latestRate = $rateStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $startFlow = isset($settings['start_flow_total']) && $settings['start_flow_total'] !== null
        ? (float)$settings['start_flow_total']
        : null;
    $startHeight = isset($settings['start_height_mm']) && $settings['start_height_mm'] !== null
        ? (float)$settings['start_height_mm']
        : null;
    $factor = isset($settings['conversion_factor']) && (float)$settings['conversion_factor'] > 0
        ? (float)$settings['conversion_factor']
        : 2.8;
    $targetHeight = isset($settings['target_height_mm']) && $settings['target_height_mm'] !== null
        ? (float)$settings['target_height_mm']
        : null;
    $tricanterRate = isset($latestRate['feed_rate']) ? (float)$latestRate['feed_rate'] : null;
    $currentFlow = isset($latest['total_tricanter'])
        ? (float)$latest['total_tricanter']
        : null;

    $difference = ($startFlow !== null && $currentFlow !== null)
        ? $currentFlow - $startFlow
        : null;
    $heightUsed = $difference !== null ? $difference / $factor : null;
    $currentHeight = ($startHeight !== null && $heightUsed !== null)
        ? $startHeight - $heightUsed
        : null;
    $heightRemaining = ($currentHeight !== null && $targetHeight !== null)
        ? max(0.0, $currentHeight - $targetHeight)
        : null;
    $volumeRemaining = $heightRemaining !== null ? $heightRemaining * $factor : null;
    $hoursToTarget = ($volumeRemaining !== null && $tricanterRate !== null && $tricanterRate > 0)
        ? $volumeRemaining / $tricanterRate
        : null;

    echo json_encode([
        'ok' => true,
        'start_height_mm' => $startHeight,
        'start_flow_total' => $startFlow,
        'target_height_mm' => $targetHeight,
        'current_flow_total' => $currentFlow,
        'flow_difference' => $difference,
        'height_used_mm' => $heightUsed,
        'current_height_mm' => $currentHeight,
        'conversion_factor' => $factor,
        'tricanter_flow_rate' => $tricanterRate,
        'hours_to_target' => $hoursToTarget,
        'latest_reading' => !empty($latest)
            ? trim(($latest['log_date'] ?? '') . ' ' . substr((string)($latest['log_time'] ?? ''), 0, 8))
            : null
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}

