<?php

function loadHeightSettings(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM tricanter_height_settings WHERE id = 1 LIMIT 1')
        ->fetch(PDO::FETCH_ASSOC) ?: [];
}

function latestTricanterFlow(PDO $pdo): ?array
{
    $row = $pdo->query('SELECT id, log_date, log_time, total_tricanter FROM project_flow_logs
        WHERE total_tricanter IS NOT NULL ORDER BY log_date DESC, log_time DESC, id DESC LIMIT 1')
        ->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function latestTricanterRate(PDO $pdo): ?array
{
    $row = $pdo->query('SELECT id, log_date, log_time, feed_rate FROM tricanter_logs
        WHERE feed_rate IS NOT NULL ORDER BY log_date DESC, log_time DESC, id DESC LIMIT 1')
        ->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function tankHeightSnapshot(PDO $pdo): array
{
    $settings = loadHeightSettings($pdo);
    $latest = latestTricanterFlow($pdo);
    $latestRate = latestTricanterRate($pdo);

    $startFlow = isset($settings['start_flow_total']) && $settings['start_flow_total'] !== null ? (float)$settings['start_flow_total'] : null;
    $startHeight = isset($settings['start_height_mm']) && $settings['start_height_mm'] !== null ? (float)$settings['start_height_mm'] : null;
    $targetHeight = isset($settings['target_height_mm']) && $settings['target_height_mm'] !== null ? (float)$settings['target_height_mm'] : null;
    $diameter = isset($settings['diameter_m']) && $settings['diameter_m'] !== null ? (float)$settings['diameter_m'] : null;
    $currentFlow = $latest ? (float)$latest['total_tricanter'] : null;
    $tricanterRate = $latestRate ? (float)$latestRate['feed_rate'] : null;
    $factor = $diameter !== null && $diameter > 0 ? pi() * pow($diameter / 2, 2) / 1000 : null;

    $missing = [];
    if ($diameter === null || $diameter <= 0) $missing[] = 'tank diameter';
    if ($startHeight === null) $missing[] = 'starting height';
    if ($targetHeight === null) $missing[] = 'target height';
    if ($startFlow === null) $missing[] = 'starting flow total';

    $complete = !$missing;
    $difference = $complete && $currentFlow !== null ? $currentFlow - $startFlow : null;
    $heightUsed = $difference !== null && $factor !== null ? $difference / $factor : null;
    $currentHeight = $startHeight !== null && $heightUsed !== null ? $startHeight - $heightUsed : null;
    $heightRemaining = $currentHeight !== null && $targetHeight !== null ? max(0.0, $currentHeight - $targetHeight) : null;
    $volumeRemaining = $heightRemaining !== null && $factor !== null ? $heightRemaining * $factor : null;
    $hoursToTarget = $volumeRemaining !== null && $tricanterRate !== null && $tricanterRate > 0 ? $volumeRemaining / $tricanterRate : null;

    return [
        'settings' => $settings, 'latest' => $latest, 'latest_rate' => $latestRate,
        'setup_complete' => $complete,
        'setup_error' => $missing ? 'Tank height setup is incomplete. Enter and save: ' . implode(', ', $missing) . '.' : null,
        'start_height_mm' => $startHeight, 'start_flow_total' => $startFlow,
        'target_height_mm' => $targetHeight, 'diameter_m' => $diameter,
        'current_flow_total' => $currentFlow, 'flow_difference' => $difference,
        'height_used_mm' => $heightUsed, 'current_height_mm' => $currentHeight,
        'volume_per_millimetre' => $factor, 'tricanter_flow_rate' => $tricanterRate,
        'hours_to_target' => $hoursToTarget,
        'latest_reading' => $latest ? trim(($latest['log_date'] ?? '') . ' ' . substr((string)($latest['log_time'] ?? ''), 0, 8)) : null,
    ];
}
