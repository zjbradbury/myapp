<?php
require_once "config.php";
requireRole(["admin"]); // change to requireLogin() if you want all logged in users to download

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
        "columns" => ["log_date", "log_time", "device", "operator", "location", "area_details", "mercury", "benzene", "lel", "h2s", "o2", "product_details", "actions"],
    ],
];

$table = trim($_GET["table"] ?? "");

if (!isset($exportTables[$table])) {
    http_response_code(400);
    die("Invalid table selected.");
}

$range = get_range_filter_state(true);

if (!empty($range["error"])) {
    http_response_code(400);
    die(h($range["error"]));
}

$tableDef = $exportTables[$table];
$columns = $tableDef["columns"];

/*
|--------------------------------------------------------------------------
| BUILD SQL USING SAME RANGE FILTER AS LIST PAGES
|--------------------------------------------------------------------------
*/
$filter = build_log_range_where($range);

$selectSql = implode(", ", array_map(function ($col) {
    return "`{$col}`";
}, $columns));

$sql = "SELECT {$selectSql} FROM `{$table}`" . $filter["sql"] . " ORDER BY log_date ASC, log_time ASC, id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($filter["params"]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| FILE NAME
|--------------------------------------------------------------------------
*/
$startPart = !empty($range["start_sql"]) ? date("Ymd_His", strtotime($range["start_sql"])) : "beginning";
$endPart   = !empty($range["end_sql"]) ? date("Ymd_His", strtotime($range["end_sql"])) : "now";

$filename = $tableDef["label"] . "_" . $startPart . "_to_" . $endPart . ".csv";

/*
|--------------------------------------------------------------------------
| OUTPUT CSV
|--------------------------------------------------------------------------
*/
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$filename}\"");

$output = fopen("php://output", "w");

fputcsv($output, $columns);

foreach ($rows as $row) {
    $line = [];
    foreach ($columns as $col) {
        $line[] = $row[$col] ?? "";
    }
    fputcsv($output, $line);
}

fclose($output);
exit;