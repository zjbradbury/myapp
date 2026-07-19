<?php
require_once "config.php";

requireLogin();
//requireRole(["admin"]); // change to requireLogin() if you want all logged in users to download

ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| EXPORT TABLE DEFINITIONS
|--------------------------------------------------------------------------
| Only include the columns you want in the CSV.
| id, uploaded_at, source_file are intentionally excluded.
*/
$exportTables = [
    "solid_waste_logs" => [
        "label" => "solid_waste",
        "columns" => ["log_date", "log_time", "amount", "comments"],
    ],
    "nozzle_logs" => [
        "label" => "nozzle",
        "columns" => ["log_date", "log_time", "nozzle", "flow", "pressure", "min_deg", "max_deg", "rpm", "comments"],
    ],
    "tricanter_logs" => [
        "label" => "tricanter",
        "columns" => ["log_date", "log_time", "bowl_speed", "screw_speed", "bowl_rpm", "screw_rpm", "impeller", "feed_rate", "torque", "temp", "pressure", "comments"],
    ],
    "sample_logs" => [
        "label" => "sample",
        "columns" => ["sample_location", "log_date", "log_time", "nozzle", "flow", "mercury", "solids", "water", "wax", "operator", "comments"],
    ],
    "gas_test_logs" => [
        "label" => "gas_test",
        "columns" => ["log_date", "log_time", "device", "operator", "location", "area_details", "mercury", "benzene", "lel", "h2s", "o2", "product_details", "action_taken"],
    ],
    "project_flow_logs" => [
        "label" => "project_flow",
        "columns" => ["log_date", "log_time", "total_recovered_oil", "total_recovered_water", "total_solid_waste", "total_tricanter", "total_nozzle", "comments"],
    ],
    "pump_values_logs" => [
        "label" => "pump_values",
        "columns" => ["log_date", "log_time", "suction_pump_1_status", "suction_pump_2_status", "suction_pump_2_speed_out", "suction_pump_2_feedback", "suction_pump_2_inlet_pressure", "suction_pump_2_outlet_pressure", "feed_pump_status", "feed_pump_speed_out", "feed_pump_feedback", "feed_pump_inlet_pressure", "feed_pump_outlet_pressure", "booster_pump_status", "booster_pump_speed_out", "booster_pump_feedback", "booster_pump_inlet_pressure", "booster_pump_outlet_pressure", "comments"],
    ],
    "nitrogen_logs" => [
        "label" => "nitrogen",
        "columns" => ["log_date", "log_time", "nitrogen_active", "trip_status", "outlet_flow", "outlet_purity", "inlet_pressure", "outlet_pressure", "pre_heat_temp", "post_heat_temp", "interior_o2", "comments"],
    ],
];

$table = trim($_GET["table"] ?? "");

if (!isset($exportTables[$table])) {
    http_response_code(400);
    die("Invalid table selected.");
}


function selected_csv_interval_minutes(): int
{
    $allowed = [0, 1, 5, 10, 15, 30, 60, 120, 360, 720];
    $value = (int)($_GET['interval'] ?? 0);
    return in_array($value, $allowed, true) ? $value : 0;
}

function selected_csv_time_search(): string
{
    return trim((string)($_GET['time_search'] ?? ''));
}

function normalise_csv_time_search(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';

    if (preg_match('/^\d{3,4}$/', $value)) {
        $value = str_pad($value, 4, '0', STR_PAD_LEFT);
        return substr($value, 0, 2) . ':' . substr($value, 2, 2);
    }

    return $value;
}

function filter_csv_rows_by_time_search(array $rows, string $timeSearch): array
{
    $timeSearch = normalise_csv_time_search($timeSearch);
    if ($timeSearch === '') {
        return $rows;
    }

    return array_values(array_filter($rows, function ($row) use ($timeSearch) {
        $time = (string)($row['log_time'] ?? '');
        return strpos($time, $timeSearch) !== false;
    }));
}

function filter_csv_rows_to_minute_increments(array $rows, int $incrementMinutes): array
{
    if (!$rows || $incrementMinutes <= 0) {
        return $rows;
    }

    $latestTimestamp = null;
    foreach ($rows as $row) {
        $stamp = trim((string)($row['log_date'] ?? '') . ' ' . (string)($row['log_time'] ?? ''));
        $timestamp = $stamp !== '' ? strtotime($stamp) : false;
        if ($timestamp === false) continue;
        if ($latestTimestamp === null || $timestamp > $latestTimestamp) {
            $latestTimestamp = $timestamp;
        }
    }

    if ($latestTimestamp === null) {
        return $rows;
    }

    $incrementSeconds = $incrementMinutes * 60;
    $nextTarget = $latestTimestamp;
    $filtered = [];

    // Input rows are newest first. This keeps latest row, then steps backward.
    foreach ($rows as $row) {
        $stamp = trim((string)($row['log_date'] ?? '') . ' ' . (string)($row['log_time'] ?? ''));
        $timestamp = $stamp !== '' ? strtotime($stamp) : false;
        if ($timestamp === false) continue;

        if ($timestamp <= $nextTarget) {
            $filtered[] = $row;
            $nextTarget = $timestamp - $incrementSeconds;
        }
    }

    return $filtered;
}

$range = get_range_filter_state(true);

if (!empty($range["error"])) {
    http_response_code(400);
    die(h($range["error"]));
}

$tableDef = $exportTables[$table];
$columns = $tableDef["columns"];
$selectedInterval = selected_csv_interval_minutes();
$timeSearch = selected_csv_time_search();

/*
|--------------------------------------------------------------------------
| BUILD SQL USING SAME RANGE FILTER AS LIST PAGES
|--------------------------------------------------------------------------
*/
$filter = build_log_range_where($range);

$selectSql = implode(", ", array_map(function ($col) {
    return "`{$col}`";
}, $columns));

$sql = "SELECT {$selectSql} FROM `{$table}`" . $filter["sql"] . " ORDER BY log_date DESC, log_time DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($filter["params"]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$rows = filter_csv_rows_by_time_search($rows, $timeSearch);
$rows = filter_csv_rows_to_minute_increments($rows, $selectedInterval);
$rows = array_reverse($rows);

/*
|--------------------------------------------------------------------------
| FILE NAME
|--------------------------------------------------------------------------
*/
$startPart = !empty($range["start_sql"]) ? date("Ymd_His", strtotime($range["start_sql"])) : "beginning";
$endPart   = !empty($range["end_sql"]) ? date("Ymd_His", strtotime($range["end_sql"])) : "now";

$filterParts = [];
if ($selectedInterval > 0) {
    $filterParts[] = $selectedInterval . "min";
}
if ($timeSearch !== '') {
    $filterParts[] = "time_" . preg_replace('/[^0-9]/', '', $timeSearch);
}
$filterPart = $filterParts ? "_" . implode("_", $filterParts) : "";

$filename = $tableDef["label"] . "_" . $startPart . "_to_" . $endPart . $filterPart . ".csv";

/*
|--------------------------------------------------------------------------
| OUTPUT CSV
|--------------------------------------------------------------------------
*/
$csv = fopen('php://temp', 'r+');

fputcsv($csv, $columns);

foreach ($rows as $row) {
    $line = [];
    foreach ($columns as $col) {
        $line[] = $row[$col] ?? '';
    }
    fputcsv($csv, $line);
}

rewind($csv);
$content = stream_get_contents($csv);
fclose($csv);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $content;
exit;