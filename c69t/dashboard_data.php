<?php
require_once "config.php";
requireLogin();

$range = get_range_filter_state();

$nozzle = fetch_log_rows($pdo, 'nozzle_logs', $range);
$tricanter = fetch_log_rows($pdo, 'tricanter_logs', $range);
$solidWaste = fetch_log_rows($pdo, 'solid_waste_logs', $range);
$sample = tableExists($pdo, 'sample_logs') ? fetch_log_rows($pdo, 'sample_logs', $range) : [];
$gasTest = tableExists($pdo, 'gas_test_logs') ? fetch_log_rows($pdo, 'gas_test_logs', $range) : [];
$projectFlow = tableExists($pdo, 'project_flow_logs') ? fetch_log_rows($pdo, 'project_flow_logs', $range) : [];

$solidWaste = solid_diff_minutes_rows($solidWaste);

$projectFlowKpis = get_project_flow_kpis($pdo, $range);

echo json_encode([
    'kpis' => [
        'tricanter' => $tricanter[0] ?? [],
        'nozzle' => $nozzle[0] ?? [],
        'solid' => $solidWaste[0] ?? [],
        'sample' => $sample[0] ?? [],
        'gas' => $gasTest[0] ?? [],
        'project_flow' => $projectFlowKpis
    ],

    'tables' => [
        'nozzle' => $nozzle,
        'tricanter' => $tricanter,
        'solid' => $solidWaste,
        'sample' => $sample,
        'gas' => $gasTest,
        'project_flow' => $projectFlow
    ],

    'charts' => [
        'nozzle' => [
            'labels' => label_series($nozzle),
            'flow' => numeric_series($nozzle, 'flow'),
            'pressure' => numeric_series($nozzle, 'pressure'),
            'rpm' => numeric_series($nozzle, 'rpm'),
            'min_deg' => numeric_series($nozzle, 'min_deg'),
            'max_deg' => numeric_series($nozzle, 'max_deg'),
        ],
        'tricanter' => [
            'labels' => label_series($tricanter),
            'bowl_speed' => numeric_series($tricanter, 'bowl_speed'),
            'screw_speed' => numeric_series($tricanter, 'screw_speed'),
            'bowl_rpm' => numeric_series($tricanter, 'bowl_rpm'),
            'screw_rpm' => numeric_series($tricanter, 'screw_rpm'),
            'impeller' => numeric_series($tricanter, 'impeller'),
            'feed_rate' => numeric_series($tricanter, 'feed_rate'),
            'torque' => numeric_series($tricanter, 'torque'),
            'temp' => numeric_series($tricanter, 'temp'),
            'pressure' => numeric_series($tricanter, 'pressure'),
        ],
        'solid' => [
            'labels' => label_series($solidWaste),
            'amount' => numeric_series($solidWaste, 'amount'),
            'diff' => solid_diff_series($solidWaste),
        ],
        'gas' => [
            'labels' => label_series($gasTest),
            'mercury' => numeric_series($gasTest, 'mercury'),
            'benzene' => numeric_series($gasTest, 'benzene'),
            'lel' => numeric_series($gasTest, 'lel'),
            'h2s' => numeric_series($gasTest, 'h2s'),
            'o2' => numeric_series($gasTest, 'o2'),
        ]
    ]
]);