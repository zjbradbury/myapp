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
    $value = trim((string) $value);
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
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (is_numeric($value)) {
        $seconds = (int) round(((float) $value) * 86400);
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

    // Handle both:
    // 1) a single object: {"table":"TRICANTER","data":{...}}
    // 2) an array of objects: [{"table":"NOZZLE","data":{...}}, {...}]
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
            ]
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
            ]
        ]
    ];

    $inserted = 0;

    foreach ($records as $record) {

        if (!isset($record['table']) || !isset($record['data']) || !is_array($record['data'])) {
            continue;
        }

        $section = strtoupper(trim((string) $record['table']));

        if (!isset($sectionMap[$section])) {
            continue;
        }

        $table = $sectionMap[$section]['table'];
        $allowedColumns = $sectionMap[$section]['columns'];

        $insertData = [
            'source_file' => $filename
        ];

        foreach ($record['data'] as $key => $value) {

            $key = trim((string) $key);

            if (!isset($allowedColumns[$key])) {
                continue;
            }

            $column = $allowedColumns[$key];
            $value = is_string($value) ? trim($value) : $value;

            if ($value === '') {
                $value = null;
            }

            if ($column === 'log_date' && $value !== null) {
                $value = parse_log_date($value);
            }

            if ($column === 'log_time' && $value !== null) {
                $value = parse_log_time($value);
            }

            $insertData[$column] = $value;
        }

        // Skip if only source_file exists and no mapped section fields were found
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