<?php
require_once "config.php";
require_once __DIR__ . '/log_filter_helpers.php';
require_once __DIR__ . '/export_tables.php';

requireRole(["admin"]);

ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| EXPORT TABLE DEFINITIONS
|--------------------------------------------------------------------------
| Only include the columns you want in the CSV.
| id, uploaded_at, source_file are intentionally excluded.
*/
$exportTables = exportTableDefinitions(false);

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

function selected_csv_value_columns(array $columns): array
{
    $requested = $_GET['has_value'] ?? [];
    if (!is_array($requested)) {
        $requested = [$requested];
    }
    $allowed = array_fill_keys($columns, true);

    return array_values(array_unique(array_filter(
        array_map(static fn($value): string => trim((string)$value), $requested),
        static fn(string $column): bool => isset($allowed[$column])
            && !in_array($column, ['log_date', 'log_time'], true)
    )));
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
$selectedValueColumns = selected_csv_value_columns($columns);

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
$rows = filter_rows_by_log_time($rows, $timeSearch);
$rows = filter_rows_with_values($rows, $selectedValueColumns);
$rows = sample_log_rows_by_minutes($rows, $selectedInterval);
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
