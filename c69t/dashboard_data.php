<?php
require_once "config.php";
requireRole(['admin','operator','viewer']);

$range = get_range_filter_state();

$nozzle = fetch_log_rows($pdo, 'nozzle_logs', $range, 'id DESC');
$tricanter = fetch_log_rows($pdo, 'tricanter_logs', $range, 'id DESC');
$solid = fetch_log_rows($pdo, 'solid_waste_logs', $range, 'id DESC');
$sample = tableExists($pdo, 'sample_logs') ? fetch_log_rows($pdo, 'sample_logs', $range, 'id DESC') : [];
$gas = tableExists($pdo, 'gas_test_logs') ? fetch_log_rows($pdo, 'gas_test_logs', $range, 'id DESC') : [];
$project = tableExists($pdo, 'project_flow_logs') ? fetch_log_rows($pdo, 'project_flow_logs', $range, 'id DESC') : [];

$solid = solid_diff_minutes_rows($solid);

echo json_encode([
    'tables' => [
        'nozzle' => $nozzle,
        'tricanter' => $tricanter,
        'solid' => $solid,
        'sample' => $sample,
        'gas' => $gas,
        'project' => $project
    ],
    'kpis' => [
        'nozzle' => $nozzle[0] ?? [],
        'tricanter' => $tricanter[0] ?? [],
        'solid' => $solid[0] ?? [],
        'sample' => $sample[0] ?? [],
        'gas' => $gas[0] ?? [],
        'project' => get_project_flow_kpis($pdo, $range)
    ],
    'charts' => [
        'nozzle' => [
            'labels' => label_series($nozzle),
            'flow' => numeric_series($nozzle,'flow'),
            'pressure' => numeric_series($nozzle,'pressure'),
            'rpm' => numeric_series($nozzle,'rpm'),
            'min' => numeric_series($nozzle,'min_deg'),
            'max' => numeric_series($nozzle,'max_deg')
        ],
        'tricanter' => [
            'labels' => label_series($tricanter),
            'bowl' => numeric_series($tricanter,'bowl_speed'),
            'screw' => numeric_series($tricanter,'screw_speed'),
            'torque' => numeric_series($tricanter,'torque')
        ],
        'solid' => [
            'labels' => label_series($solid),
            'amount' => numeric_series($solid,'amount'),
            'diff' => solid_diff_series($solid)
        ],
        'gas' => [
            'labels' => label_series($gas),
            'h2s' => numeric_series($gas,'h2s'),
            'lel' => numeric_series($gas,'lel')
        ]
    ]
]);