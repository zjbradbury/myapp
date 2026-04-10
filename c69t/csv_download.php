<?php
require_once "config.php";
requireLogin(); // or requireRole(["admin", "operator", "viewer"]);

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/*
|--------------------------------------------------------------------------
| TABLE EXPORT DEFINITIONS
|--------------------------------------------------------------------------
| Only include the columns you want in the CSV.
| This automatically excludes id, uploaded_at, source_file.
*/
$exportTables = [
    "solid_waste_logs" => [
        "label" => "solid_waste",
        "columns" => ["log_date", "log_time", "amount", "comments"]
    ],
    "nozzle_logs" => [
        "label" => "nozzle",
        "columns" => ["log_date", "log_time", "nozzle", "flow", "pressure", "min_deg", "max_deg", "rpm", "comments"]
    ],
    "tricanter_logs" => [
        "label" => "tricanter",
        "columns" => ["log_date", "log_time", "bowl_speed", "screw_speed", "bowl_rpm", "screw_rpm", "impeller", "feed_rate", "torque", "temp", "pressure", "comments"]
    ],
    "sample_logs" => [
        "label" => "sample",
        "columns" => ["sample_location", "log_date", "log_time", "nozzle", "flow", "mercury", "solids", "water", "wax", "operator", "comments"]
    ],
    "gas_test_logs" => [
        "label" => "gas_test",
        "columns" => ["log_date", "log_time", "device", "operator", "location", "area_details", "mercury", "benzene", "lel", "h2s", "o2", "product_details", "actions"]
    ]
];

/*
|--------------------------------------------------------------------------
| READ FILTER VALUES
|--------------------------------------------------------------------------
| Supports either:
| from_date / from_time / to_date / to_time
| or
| start_date / start_time / end_date / end_time
*/
$table = trim($_GET["table"] ?? "");

$fromDate = trim($_GET["from_date"] ?? $_GET["start_date"] ?? "");
$fromTime = trim($_GET["from_time"] ?? $_GET["start_time"] ?? "");
$toDate   = trim($_GET["to_date"] ?? $_GET["end_date"] ?? "");
$toTime   = trim($_GET["to_time"] ?? $_GET["end_time"] ?? "");

if (!isset($exportTables[$table])) {
    http_response_code(400);
    die("Invalid table.");
}

if ($fromDate === "" || $fromTime === "" || $toDate === "" || $toTime === "") {
    http_response_code(400);
    die("Missing date/time range.");
}

$fromDateTime = $fromDate . ' ' . $fromTime;
$toDateTime   = $toDate . ' ' . $toTime;

/*
|--------------------------------------------------------------------------
| VALIDATE DATES
|--------------------------------------------------------------------------
*/
$fromTs = strtotime($fromDateTime);
$toTs   = strtotime($toDateTime);

if ($fromTs === false || $toTs === false) {
    http_response_code(400);
    die("Invalid date/time range.");
}

if ($toTs < $fromTs) {
    http_response_code(400);
    die("End date/time must be after start date/time.");
}

/*
|--------------------------------------------------------------------------
| BUILD QUERY
|--------------------------------------------------------------------------
*/
$tableDef = $exportTables[$table];
$columns = $tableDef["columns"];

$selectSql = implode(", ", array_map(fn($col) => "`{$col}`", $columns));

$sql = "
    SELECT {$selectSql}
    FROM `{$table}`
    WHERE TIMESTAMP(`log_date`, `log_time`) >= :from_dt
      AND TIMESTAMP(`log_date`, `log_time`) <= :to_dt
    ORDER BY `log_date` ASC, `log_time` ASC, `id` ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ":from_dt" => date("Y-m-d H:i:s", $fromTs),
    ":to_dt"   => date("Y-m-d H:i:s", $toTs),
]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| OUTPUT CSV
|--------------------------------------------------------------------------
*/
$filename = $tableDef["label"]
    . "_"
    . date("Ymd_His", $fromTs)
    . "_to_"
    . date("Ymd_His", $toTs)
    . ".csv";

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