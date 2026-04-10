<?php
require_once "config.php";
requireRole(["admin"]);

ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| TABLE DEFINITIONS
|--------------------------------------------------------------------------
*/
$uploadTables = [
    "solid_waste_logs" => [
        "label" => "Solid Waste Logs",
        "columns" => ["source_file", "log_date", "log_time", "amount", "comments"],
        "required" => ["log_date", "log_time"],
        "aliases" => [
            "source_file" => ["source_file", "source file", "file", "filename"],
            "log_date"    => ["log_date", "date", "log date"],
            "log_time"    => ["log_time", "time", "log time"],
            "amount"      => ["amount", "solid waste", "waste", "value", "start level"],
            "comments"    => ["comments", "comment", "notes", "note"]
        ]
    ],

    "nozzle_logs" => [
        "label" => "Nozzle Logs",
        "columns" => ["source_file", "log_date", "log_time", "nozzle", "flow", "pressure", "min_deg", "max_deg", "rpm", "comments"],
        "required" => ["log_date", "log_time"],
        "aliases" => [
            "source_file" => ["source_file", "source file", "file", "filename"],
            "log_date"    => ["log_date", "date", "log date"],
            "log_time"    => ["log_time", "time", "log time"],
            "nozzle"      => ["nozzle", "nozzle number", "nozzle #"],
            "flow"        => ["flow", "flow rate"],
            "pressure"    => ["pressure", "press"],
            "min_deg"     => ["min_deg", "min deg", "min degree", "minimum degree"],
            "max_deg"     => ["max_deg", "max deg", "max degree", "maximum degree"],
            "rpm"         => ["rpm", "speed rpm"],
            "comments"    => ["comments", "comment", "notes", "note"]
        ]
    ],

    "tricanter_logs" => [
        "label" => "Tricanter Logs",
        "columns" => ["source_file", "log_date", "log_time", "bowl_speed", "screw_speed", "bowl_rpm", "screw_rpm", "impeller", "feed_rate", "torque", "temp", "pressure", "comments"],
        "required" => ["log_date", "log_time"],
        "aliases" => [
            "source_file" => ["source_file", "source file", "file", "filename"],
            "log_date"    => ["log_date", "date", "log date"],
            "log_time"    => ["log_time", "time", "log time"],
            "bowl_speed"  => ["bowl_speed", "bowl speed"],
            "screw_speed" => ["screw_speed", "screw speed"],
            "bowl_rpm"    => ["bowl_rpm", "bowl rpm"],
            "screw_rpm"   => ["screw_rpm", "screw rpm"],
            "impeller"    => ["impeller"],
            "feed_rate"   => ["feed_rate", "feed rate", "flow"],
            "torque"      => ["torque"],
            "temp"        => ["temp", "temperature"],
            "pressure"    => ["pressure", "press"],
            "comments"    => ["comments", "comment", "notes", "note"]
        ]
    ],

    "sample_logs" => [
        "label" => "Sample Logs",
        "columns" => ["source_file", "sample_location", "log_date", "log_time", "nozzle", "flow", "mercury", "solids", "water", "wax", "operator", "comments"],
        "required" => ["log_date", "log_time"],
        "aliases" => [
            "source_file"     => ["source_file", "source file", "file", "filename"],
            "sample_location" => ["sample_location", "sample location", "location"],
            "log_date"        => ["log_date", "date", "log date"],
            "log_time"        => ["log_time", "time", "log time"],
            "nozzle"          => ["nozzle", "nozzle #", "nozzle number"],
            "flow"            => ["flow", "flow rate"],
            "mercury"         => ["mercury", "hg"],
            "solids"          => ["solids"],
            "water"           => ["water"],
            "wax"             => ["wax"],
            "operator"        => ["operator", "user"],
            "comments"        => ["comments", "comment", "notes", "note"]
        ]
    ],

    "gas_test_logs" => [
        "label" => "Gas Test Logs",
        "columns" => ["source_file", "log_date", "log_time", "device", "operator", "location", "area_details", "mercury", "benzene", "lel", "h2s", "o2", "product_details", "action_taken"],
        "required" => ["log_date", "log_time"],
        "aliases" => [
            "source_file"     => ["source_file", "source file", "file", "filename"],
            "log_date"        => ["log_date", "date", "log date"],
            "log_time"        => ["log_time", "time", "log time"],
            "device"          => ["device"],
            "operator"        => ["operator", "user"],
            "location"        => ["location"],
            "area_details"    => ["area_details", "area details", "area"],
            "mercury"         => ["mercury", "hg"],
            "benzene"         => ["benzene"],
            "lel"             => ["lel"],
            "h2s"             => ["h2s"],
            "o2"              => ["o2", "oxygen"],
            "product_details" => ["product_details", "product details", "product"],
            "action_taken"         => ["actions", "action", "action_taken"]
        ]
    ]
];

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function normalizeHeader(string $value): string
{
    $value = trim(strtolower($value));
    $value = str_replace(["-", "/", "\\", ".", "(", ")", "[", "]"], " ", $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim($value);
}

function cleanNumeric($value)
{
    if ($value === null) return null;
    $value = trim((string) $value);
    if ($value === '') return null;

    $value = str_replace(",", "", $value);
    $value = preg_replace('/[^0-9.\-]/', '', $value);

    return $value === '' ? null : $value;
}

function parseDbDate(?string $value): ?string
{
    if ($value === null) return null;
    $value = trim($value);
    if ($value === '') return null;

    $value = preg_replace('/\s+/', ' ', $value);

    $formats = [
        'Y-m-d',
        'Y/m/d',
        'd/m/Y',
        'd-m-Y',
        'd.m.Y',
        'm/d/Y',
        'm-d-Y',
        'j/n/Y',
        'j-n-Y',
        'j/m/Y',
        'j-m-Y',
        'd/m/y',
        'd-m-y',
        'm/d/y',
        'm-d-y',
        'd M Y',
        'j M Y',
        'd F Y',
        'j F Y',
        'Ymd'
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat('!' . $format, $value);
        if ($dt && $dt->format($format) === $value) {
            return $dt->format('Y-m-d');
        }
    }

    $ts = strtotime($value);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return null;
}

function parseDbTime(?string $value): ?string
{
    if ($value === null) return null;
    $value = trim($value);
    if ($value === '') return null;

    $value = strtoupper($value);
    $value = preg_replace('/\s+/', ' ', $value);

    $formats = [
        'H:i:s',
        'H:i',
        'G:i',
        'g:i A',
        'g:i:s A',
        'h:i A',
        'h:i:s A',
        'Hi',
        'His',
        'g A',
        'ga'
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat('!' . $format, $value);
        if ($dt) {
            return $dt->format('H:i:s');
        }
    }

    $ts = strtotime($value);
    if ($ts !== false) {
        return date('H:i:s', $ts);
    }

    return null;
}

function mapHeaders(array $csvHeaders, array $aliases): array
{
    $mapped = [];
    $normalizedHeaders = [];

    foreach ($csvHeaders as $index => $header) {
        $normalizedHeaders[$index] = normalizeHeader((string) $header);
    }

    foreach ($aliases as $dbColumn => $possibleNames) {
        foreach ($possibleNames as $name) {
            $want = normalizeHeader($name);
            foreach ($normalizedHeaders as $index => $actual) {
                if ($actual === $want) {
                    $mapped[$dbColumn] = $index;
                    break 2;
                }
            }
        }
    }

    return $mapped;
}

function buildInsertSql(string $table, array $columns): string
{
    $colSql = implode(", ", array_map(fn($c) => "`{$c}`", $columns));
    $placeholders = implode(", ", array_fill(0, count($columns), "?"));
    return "INSERT INTO `{$table}` ({$colSql}) VALUES ({$placeholders})";
}

function getRowValue(array $row, array $mappedHeaders, string $column)
{
    if (!isset($mappedHeaders[$column])) return null;
    $index = $mappedHeaders[$column];
    return array_key_exists($index, $row) ? trim((string) $row[$index]) : null;
}

/*
|--------------------------------------------------------------------------
| FORM STATE
|--------------------------------------------------------------------------
*/
$selectedTable = $_POST['table_name'] ?? array_key_first($uploadTables);
$messages = [];
$errors = [];
$report = [];
$successCount = 0;
$failCount = 0;

/*
|--------------------------------------------------------------------------
| UPLOAD
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($uploadTables[$selectedTable])) {
        $errors[] = "Invalid table selected.";
    }

    if (!isset($_FILES["csv_file"]) || $_FILES["csv_file"]["error"] !== UPLOAD_ERR_OK) {
        $errors[] = "Please choose a CSV file.";
    }

    if (!$errors) {
        $tableDef = $uploadTables[$selectedTable];
        $tmpName = $_FILES["csv_file"]["tmp_name"];
        $originalName = $_FILES["csv_file"]["name"] ?? "upload.csv";

        $handle = fopen($tmpName, "r");
        if (!$handle) {
            $errors[] = "Could not open uploaded CSV file.";
        } else {
            $delimiter = ",";
            $firstLine = fgets($handle);
            rewind($handle);

            if ($firstLine !== false) {
                $commaCount = substr_count($firstLine, ",");
                $semiCount  = substr_count($firstLine, ";");
                if ($semiCount > $commaCount) {
                    $delimiter = ";";
                }
            }

            $headers = fgetcsv($handle, 0, $delimiter);
            if (!$headers || count($headers) === 0) {
                $errors[] = "CSV appears to be empty.";
            } else {
                $mappedHeaders = mapHeaders($headers, $tableDef["aliases"]);
                $insertColumns = $tableDef["columns"];
                $sql = buildInsertSql($selectedTable, $insertColumns);
                $stmt = $pdo->prepare($sql);

                $rowNumber = 1;
                $pdo->beginTransaction();

                try {
                    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                        $rowNumber++;

                        if (count(array_filter($row, fn($v) => trim((string) $v) !== "")) === 0) {
                            continue;
                        }

                        $data = [];
                        $rowErrors = [];

                        foreach ($insertColumns as $column) {
                            if ($column === "source_file") {
                                $data[$column] = $originalName;
                                continue;
                            }

                            $value = getRowValue($row, $mappedHeaders, $column);

                            if ($column === "log_date") {
                                $parsed = parseDbDate($value);
                                if ($value !== null && trim((string) $value) !== "" && $parsed === null) {
                                    $rowErrors[] = "invalid date '{$value}'";
                                }
                                $data[$column] = $parsed;
                                continue;
                            }

                            if ($column === "log_time") {
                                $parsed = parseDbTime($value);
                                if ($value !== null && trim((string) $value) !== "" && $parsed === null) {
                                    $rowErrors[] = "invalid time '{$value}'";
                                }
                                $data[$column] = $parsed;
                                continue;
                            }

                            if (in_array($column, [
                                "amount", "flow", "pressure", "min_deg", "max_deg", "rpm",
                                "bowl_speed", "screw_speed", "bowl_rpm", "screw_rpm", "impeller",
                                "feed_rate", "torque", "temp", "mercury", "solids", "water",
                                "wax", "benzene", "lel", "h2s", "o2"
                            ], true)) {
                                $data[$column] = cleanNumeric($value);
                                continue;
                            }

                            $data[$column] = ($value === '' ? null : $value);
                        }

                        foreach ($tableDef["required"] as $requiredColumn) {
                            if (!isset($data[$requiredColumn]) || $data[$requiredColumn] === null || $data[$requiredColumn] === '') {
                                $rowErrors[] = "missing required field '{$requiredColumn}'";
                            }
                        }

                        if ($rowErrors) {
                            $failCount++;
                            $report[] = "Row {$rowNumber}: " . implode(", ", $rowErrors);
                            continue;
                        }

                        $stmt->execute(array_map(fn($c) => $data[$c] ?? null, $insertColumns));
                        $successCount++;
                    }

                    $pdo->commit();
                    $messages[] = "Upload complete. {$successCount} row(s) inserted, {$failCount} row(s) skipped.";
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $errors[] = "Upload failed: " . $e->getMessage();
                }
            }

            fclose($handle);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSV Upload</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .upload-wrap {
            max-width: 900px;
            margin: 20px auto;
            background: #122c44;
            border-radius: 12px;
            padding: 20px;
            color: #fff;
        }

        .upload-wrap h1 {
            margin-top: 0;
        }

        .form-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: 1fr;
        }

        .field label {
            display: block;
            font-size: 13px;
            color: #9ec3df;
            margin-bottom: 6px;
        }

        .field select,
        .field input[type="file"] {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #2f4e68;
            background: #0d2234;
            color: #fff;
            box-sizing: border-box;
        }

        .btn-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 8px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            background: #1e88e5;
            color: #fff;
            font-weight: bold;
        }

        .msg,
        .err,
        .report-box {
            margin-top: 16px;
            padding: 12px 14px;
            border-radius: 8px;
        }

        .msg {
            background: #10361d;
            border: 1px solid #255c35;
            color: #b9f3c8;
        }

        .err {
            background: #3b1616;
            border: 1px solid #7d2c2c;
            color: #ffb8b8;
        }

        .report-box {
            background: #0d2234;
            border: 1px solid #2f4e68;
            max-height: 280px;
            overflow: auto;
        }

        .help {
            color: #c8d8e5;
            font-size: 13px;
            line-height: 1.5;
        }

        code {
            background: rgba(255,255,255,0.08);
            padding: 2px 5px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="upload-wrap">
    <h1>CSV Upload</h1>
    <p class="help">
        Admin only. Upload a CSV with headers like <code>Date</code>, <code>Time</code>, <code>Flow</code>, <code>Comments</code> etc.
        Date formats like <code>23/01/2026</code>, <code>2026-01-23</code>, <code>1/23/2026</code> and times like
        <code>14:30</code>, <code>2:30 PM</code>, <code>14:30:00</code> are converted automatically.
    </p>

    <form method="post" enctype="multipart/form-data" class="form-grid">
        <div class="field">
            <label for="table_name">Upload to table</label>
            <select name="table_name" id="table_name" required>
                <?php foreach ($uploadTables as $tableName => $def): ?>
                    <option value="<?= h($tableName) ?>" <?= $selectedTable === $tableName ? 'selected' : '' ?>>
                        <?= h($def["label"]) ?> (<?= h($tableName) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="csv_file">CSV file</label>
            <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" required>
        </div>

        <div class="btn-row">
            <button class="btn" type="submit">Upload CSV</button>
        </div>
    </form>

    <?php foreach ($messages as $message): ?>
        <div class="msg"><?= h($message) ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $error): ?>
        <div class="err"><?= h($error) ?></div>
    <?php endforeach; ?>

    <?php if ($report): ?>
        <div class="report-box">
            <strong>Skipped rows</strong>
            <ul>
                <?php foreach ($report as $line): ?>
                    <li><?= h($line) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="report-box">
        <strong>Expected columns for selected tables</strong>
        <ul>
            <?php foreach ($uploadTables as $tableName => $def): ?>
                <li>
                    <strong><?= h($def["label"]) ?></strong>:
                    <?= h(implode(", ", $def["columns"])) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
</body>
</html>