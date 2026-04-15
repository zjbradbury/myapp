<?php
header('Content-Type: application/json');

$host = "mariadb";
$dbname = "myapp";
$user = "zack";
$pass = "Butcher69";
$sharedSecret = "ButcherWhiskeyTango";

function fail($msg, $code = 400)
{
    http_response_code($code);
    echo json_encode([
        "status" => "error",
        "message" => $msg
    ]);
    exit;
}

function parse_log_date($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $formats = [
        'd/m/Y',
        'j/n/Y',
        'Y-m-d',
        'd-m-Y',
        'j-n-Y'
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    return null;
}

function parse_log_time($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    if (is_numeric($value)) {
        $seconds = (int) round(((float)$value) * 86400);
        $seconds = $seconds % 86400;
        return gmdate('H:i:s', $seconds);
    }

    $formats = [
        'H:i:s',
        'H:i',
        'G:i:s',
        'G:i',
        'g:i A',
        'g:i:s A',
        'h:i A',
        'h:i:s A'
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('H:i:s');
        }
    }

    return null;
}

function normalize_key($key)
{
    $key = trim((string)$key);
    $key = str_replace([' ', '-'], '_', $key);
    return strtolower($key);
}

function parse_number($value)
{
    if ($value === null) {
        return null;
    }

    if (is_string($value)) {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = str_replace(',', '', $value);

        // Allow normal numbers and scientific notation like 9.889203E-02
        if (is_numeric($value)) {
            return (float)$value;
        }

        // Fallback cleanup for values with units or stray characters
        $value = preg_replace('/[^0-9eE+\-\.]/', '', $value);

        if ($value === '' || $value === '-' || $value === '.' || $value === '-.' || !is_numeric($value)) {
            return null;
        }
    }

    if (!is_numeric($value)) {
        return null;
    }

    return (float)$value;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail("Invalid request method", 405);
    }

    $secret = $_POST['secret'] ?? '';
    $filename = $_POST['filename'] ?? '';
    $payloadJson = $_POST['payload'] ?? '';

    if ($secret !== $sharedSecret) {
        fail("Unauthorized", 403);
    }

    if ($filename === '' || $payloadJson === '') {
        fail("Missing required fields");
    }

    $records = json_decode($payloadJson, true);

    if (!is_array($records)) {
        fail("Invalid payload JSON");
    }

    if (isset($records['table']) && isset($records['data'])) {
        $records = [$records];
    }

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sectionMap = [
        'NOZZLE' => [
            'table' => 'nozzle_logs',
            'columns' => [
                'Date' => 'log_date',
                'Time' => 'log_time',
                'Nozzle' => 'nozzle',
                'Flow' => 'flow',
                'Pressure' => 'pressure',
                'Min_Deg' => 'min_deg',
                'Max_Deg' => 'max_deg',
                'RPM' => 'rpm',
                'Comments' => 'comments'
            ],
            'numeric_columns' => ['flow', 'pressure', 'min_deg', 'max_deg', 'rpm']
        ],
        'TRICANTER' => [
            'table' => 'tricanter_logs',
            'columns' => [
                'Date' => 'log_date',
                'Time' => 'log_time',
                'Bowl_Speed' => 'bowl_speed',
                'Screw_Speed' => 'screw_speed',
                'Bowl_RPM' => 'bowl_rpm',
                'Screw_RPM' => 'screw_rpm',
                'Impeller' => 'impeller',
                'Feed_Rate' => 'feed_rate',
                'Torque' => 'torque',
                'Temp' => 'temp',
                'Pressure' => 'pressure',
                'Comments' => 'comments'
            ],
            'numeric_columns' => [
                'bowl_speed',
                'screw_speed',
                'bowl_rpm',
                'screw_rpm',
                'impeller',
                'feed_rate',
                'torque',
                'temp',
                'pressure'
            ]
        ],
        'SOLID_WASTE' => [
            'table' => 'solid_waste_logs',
            'columns' => [
                'Date' => 'log_date',
                'Time' => 'log_time',
                'Amount' => 'amount',
                'Comments' => 'comments'
            ],
            'numeric_columns' => ['amount']
        ],
        'PROJECTFLOW' => [
            'table' => 'project_flow_logs',
            'columns' => [
                'Date' => 'log_date',
                'Time' => 'log_time',
                'Total_Recovered_Oil' => 'total_recovered_oil',
                'Total_Recovered_Water' => 'total_recovered_water',
                'Total_Solid_Waste' => 'total_solid_waste',
                'Total_Tricanter' => 'total_tricanter',
                'Total_Nozzle' => 'total_nozzle',
                'Comments' => 'comments'
            ],
            'numeric_columns' => [
                'total_recovered_oil',
                'total_recovered_water',
                'total_solid_waste',
                'total_tricanter',
                'total_nozzle'
            ]
        ]
    ];

    $inserted = 0;

    foreach ($records as $record) {
        if (!isset($record['table']) || !isset($record['data']) || !is_array($record['data'])) {
            continue;
        }

        $section = strtoupper(trim((string)$record['table']));
        $section = str_replace(' ', '_', $section);

        if (!isset($sectionMap[$section])) {
            continue;
        }

        $table = $sectionMap[$section]['table'];
        $allowedColumns = $sectionMap[$section]['columns'];
        $numericColumns = $sectionMap[$section]['numeric_columns'] ?? [];

        $insertData = [
            'source_file' => $filename
        ];

        $solidStart = null;
        $solidStop = null;

        foreach ($record['data'] as $key => $value) {
            $originalKey = trim((string)$key);
            $normalizedKey = normalize_key($originalKey);

            $value = is_string($value) ? trim($value) : $value;
            if ($value === '') {
                $value = null;
            }

            if ($section === 'SOLID_WASTE') {
                if (in_array($normalizedKey, ['start', 'start_value', 'start_level', 'startlevel'], true)) {
                    $solidStart = parse_number($value);
                    continue;
                }

                if (in_array($normalizedKey, ['stop', 'stop_value', 'stop_level', 'stoplevel'], true)) {
                    $solidStop = parse_number($value);
                    continue;
                }

                if ($normalizedKey === 'amount') {
                    $insertData['amount'] = parse_number($value);
                    continue;
                }
            }

            if (!isset($allowedColumns[$originalKey])) {
                $matchedColumn = null;

                foreach ($allowedColumns as $mapKey => $mapColumn) {
                    if (normalize_key($mapKey) === $normalizedKey) {
                        $matchedColumn = $mapColumn;
                        break;
                    }
                }

                if ($matchedColumn === null) {
                    continue;
                }

                $column = $matchedColumn;
            } else {
                $column = $allowedColumns[$originalKey];
            }

            if ($column === 'log_date' && $value !== null) {
                $value = parse_log_date($value);
            } elseif ($column === 'log_time' && $value !== null) {
                $value = parse_log_time($value);
            } elseif (in_array($column, $numericColumns, true) && $value !== null) {
                $value = parse_number($value);
            }

            $insertData[$column] = $value;
        }

        if ($section === 'SOLID_WASTE' && !isset($insertData['amount'])) {
            if ($solidStart !== null && $solidStop !== null) {
                $insertData['amount'] = $solidStart - $solidStop;
            }
        }

        if (count($insertData) <= 1) {
            continue;
        }

        $columns = array_keys($insertData);

        $quotedColumns = array_map(function ($col) {
            return "`" . $col . "`";
        }, $columns);

        $placeholders = array_map(function ($col) {
            return ":" . $col;
        }, $columns);

        $sql = "INSERT INTO `" . $table . "` (" . implode(", ", $quotedColumns) . ")
                VALUES (" . implode(", ", $placeholders) . ")";

        $stmt = $pdo->prepare($sql);

        $params = [];
        foreach ($insertData as $col => $val) {
            $params[":" . $col] = $val;
        }

        $stmt->execute($params);
        $inserted++;
    }

    if ($inserted === 0) {
        fail("No records inserted (mapping mismatch or empty data)", 400);
    }

    echo json_encode([
        "status" => "success",
        "message" => "Records uploaded",
        "inserted" => $inserted
    ]);

} catch (Throwable $e) {
    fail($e->getMessage(), 500);
}