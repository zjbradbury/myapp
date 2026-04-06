<?php
$host = "localhost";
$dbname = "hmi_data";
$user = "your_user";
$pass = "your_password";

$message = "";

$tableConfigs = [
    "nozzle_logs" => [
        "allowed_columns" => [
            "source_file",
            "log_date",
            "log_time",
            "nozzle",
            "flow",
            "pressure",
            "min_deg",
            "max_deg",
            "rpm",
            "comments"
        ]
    ],
    "tricanter_logs" => [
        "allowed_columns" => [
            "source_file",
            "log_date",
            "log_time",
            "bowl_speed",
            "screw_speed",
            "bowl_rpm",
            "screw_rpm",
            "impeller",
            "feed_rate",
            "torque",
            "temp",
            "pressure",
            "comments"
        ]
    ]
];

function normalize_header($header) {
    $header = trim($header);
    $header = strtolower($header);
    $header = preg_replace('/[^a-z0-9\s_]/', '', $header);
    $header = preg_replace('/\s+/', '_', $header);
    return $header;
}

function parse_excel_date($value) {
    $value = trim((string)$value);
    if ($value === "") {
        return null;
    }

    $formats = ["d/m/Y", "j/n/Y", "Y-m-d", "d-m-Y", "m/d/Y", "n/j/Y"];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format("Y-m-d");
        }
    }

    return null;
}

function parse_excel_time($value) {
    $value = trim((string)$value);
    if ($value === "") {
        return null;
    }

    if (is_numeric($value)) {
        $seconds = round(((float)$value) * 86400);
        $seconds = $seconds % 86400;
        return gmdate("H:i:s", $seconds);
    }

    $formats = ["H:i:s", "H:i", "g:i A", "g:i:s A", "h:i:s A", "h:i A"];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format("H:i:s");
        }
    }

    return null;
}

function clean_nozzle($value) {
    $value = trim((string)$value);
    if ($value === "") {
        return null;
    }

    $value = preg_replace('/^N/i', '', $value);
    $value = trim($value);

    if ($value === "") {
        return null;
    }

    return is_numeric($value) ? (int)$value : null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        if (empty($_POST["target_table"]) || !isset($tableConfigs[$_POST["target_table"]])) {
            throw new Exception("Invalid target table selected.");
        }

        $targetTable = $_POST["target_table"];
        $allowedColumns = $tableConfigs[$targetTable]["allowed_columns"];

        if (!isset($_FILES["csv_file"]) || $_FILES["csv_file"]["error"] !== UPLOAD_ERR_OK) {
            throw new Exception("CSV file upload failed.");
        }

        $tmpName = $_FILES["csv_file"]["tmp_name"];
        $originalName = $_FILES["csv_file"]["name"];

        $handle = fopen($tmpName, "r");
        if (!$handle) {
            throw new Exception("Could not open uploaded CSV.");
        }

        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            throw new Exception("CSV appears to be empty.");
        }

        $csvHeaders = array_map("normalize_header", $rawHeaders);

        $insertableColumns = [];
        foreach ($csvHeaders as $header) {
            if (in_array($header, $allowedColumns, true)) {
                $insertableColumns[] = $header;
            } else {
                $insertableColumns[] = null;
            }
        }

        $finalColumns = array_values(array_filter(array_unique(array_filter($insertableColumns))));
        if (empty($finalColumns)) {
            throw new Exception("No valid matching columns found in CSV.");
        }

        if (!in_array("source_file", $finalColumns, true)) {
            $finalColumns[] = "source_file";
        }

        $quotedCols = array_map(fn($col) => "`$col`", $finalColumns);
        $placeholders = array_map(fn($col) => ":$col", $finalColumns);

        $sql = "INSERT INTO `$targetTable` (" . implode(", ", $quotedCols) . ")
                VALUES (" . implode(", ", $placeholders) . ")";
        $stmt = $pdo->prepare($sql);

        $rowCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn($v) => trim((string)$v) !== "")) === 0) {
                continue;
            }

            $data = [];
            foreach ($csvHeaders as $index => $header) {
                $mappedColumn = $insertableColumns[$index] ?? null;
                if ($mappedColumn === null) {
                    continue;
                }

                $value = $row[$index] ?? null;
                $value = is_string($value) ? trim($value) : $value;

                if ($value === "") {
                    $value = null;
                }

                if ($mappedColumn === "log_date" && $value !== null) {
                    $value = parse_excel_date($value);
                }

                if ($mappedColumn === "log_time" && $value !== null) {
                    $value = parse_excel_time($value);
                }

                if ($mappedColumn === "nozzle" && $value !== null) {
                    $value = clean_nozzle($value);
                }

                $data[$mappedColumn] = $value;
            }

            $data["source_file"] = $originalName;

            foreach ($finalColumns as $col) {
                if (!array_key_exists($col, $data)) {
                    $data[$col] = null;
                }
            }

            $params = [];
            foreach ($finalColumns as $col) {
                $params[":$col"] = $data[$col];
            }

            $stmt->execute($params);
            $rowCount++;
        }

        fclose($handle);

        $message = "Upload complete. Inserted {$rowCount} row(s) into {$targetTable}.";
    } catch (Throwable $e) {
        $message = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New CSV Upload to Database</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 700px;
            margin: 40px auto;
            padding: 20px;
        }
        form {
            border: 1px solid #ccc;
            padding: 20px;
            border-radius: 8px;
        }
        label {
            display: block;
            margin-top: 14px;
            font-weight: bold;
        }
        input, select, button {
            margin-top: 6px;
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
        }
        button {
            margin-top: 20px;
            cursor: pointer;
        }
        .message {
            margin-bottom: 20px;
            padding: 12px;
            background: #f3f3f3;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <h1>Upload Excel CSV to Database</h1>

    <?php if ($message !== ""): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <label for="target_table">Target Table</label>
        <select name="target_table" id="target_table" required>
            <option value="">Select table</option>
            <option value="nozzle_logs">nozzle_logs</option>
            <option value="tricanter_logs">tricanter_logs</option>
        </select>

        <label for="csv_file">CSV File</label>
        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>

        <button type="submit">Upload CSV</button>
    </form>
</body>
</html>