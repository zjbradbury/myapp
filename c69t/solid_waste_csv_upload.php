<?php
require_once "config.php";
requireLogin();

$canUpload = in_array(currentRole(), ["admin", "operator"], true);

if (!$canUpload) {
    http_response_code(403);
    die("Access denied.");
}

$message = "";
$error = "";

// function h($value) {
//     return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
// }

function normalizeHeader($header) {
    $header = trim((string)$header);
    $header = strtolower($header);
    $header = str_replace([" ", "-", "/"], "_", $header);
    return $header;
}

function parseDateValue($value) {
    $value = trim((string)$value);
    if ($value === '') return null;

    $formats = [
        'Y-m-d',
        'd/m/Y',
        'j/n/Y',
        'd-m-Y',
        'j-n-Y',
        'm/d/Y',
        'n/j/Y',
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
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

function parseTimeValue($value) {
    $value = trim((string)$value);
    if ($value === '') return null;

    $formats = [
        'H:i:s',
        'H:i',
        'g:i A',
        'g:i a',
        'h:i A',
        'h:i a',
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please select a valid CSV file.";
    } else {
        $tmpPath = $_FILES['csv_file']['tmp_name'];
        $originalName = $_FILES['csv_file']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($ext !== 'csv') {
            $error = "Only .csv files are allowed.";
        } else {
            $handle = fopen($tmpPath, 'r');

            if (!$handle) {
                $error = "Could not open uploaded CSV file.";
            } else {
                $header = fgetcsv($handle);

                if ($header === false) {
                    $error = "CSV file is empty.";
                    fclose($handle);
                } else {
                    $normalizedHeaders = array_map('normalizeHeader', $header);

                    $dateIndex = array_search('date', $normalizedHeaders, true);
                    $timeIndex = array_search('time', $normalizedHeaders, true);
                    $amountIndex = array_search('amount', $normalizedHeaders, true);
                    $commentsIndex = array_search('comments', $normalizedHeaders, true);

                    if ($dateIndex === false || $timeIndex === false || $amountIndex === false) {
                        $error = "CSV must contain headers: date, time, amount. Comments is optional.";
                        fclose($handle);
                    } else {
                        $inserted = 0;
                        $skipped = 0;
                        $lineNumber = 1;

                        try {
                            $pdo->beginTransaction();

                            $stmt = $pdo->prepare("
                                INSERT INTO solid_waste_logs
                                    (source_file, uploaded_at, log_date, log_time, amount, comments)
                                VALUES
                                    (:source_file, NOW(), :log_date, :log_time, :amount, :comments)
                            ");

                            while (($row = fgetcsv($handle)) !== false) {
                                $lineNumber++;

                                if (
                                    count(array_filter($row, function($v) {
                                        return trim((string)$v) !== '';
                                    })) === 0
                                ) {
                                    continue;
                                }

                                $rawDate = $row[$dateIndex] ?? '';
                                $rawTime = $row[$timeIndex] ?? '';
                                $rawAmount = $row[$amountIndex] ?? '';
                                $rawComments = ($commentsIndex !== false) ? ($row[$commentsIndex] ?? '') : '';

                                $logDate = parseDateValue($rawDate);
                                $logTime = parseTimeValue($rawTime);
                                $amount = trim((string)$rawAmount);
                                $comments = trim((string)$rawComments);

                                if ($logDate === null || $logTime === null || $amount === '' || !is_numeric($amount)) {
                                    $skipped++;
                                    continue;
                                }

                                $stmt->execute([
                                    ':source_file' => $originalName,
                                    ':log_date' => $logDate,
                                    ':log_time' => $logTime,
                                    ':amount' => $amount,
                                    ':comments' => $comments,
                                ]);

                                $inserted++;
                            }

                            $pdo->commit();

                            $message = "Upload complete. Inserted {$inserted} row(s), skipped {$skipped} invalid row(s).";
                        } catch (Throwable $e) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            $error = "Upload failed: " . $e->getMessage();
                        }

                        fclose($handle);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Solid Waste CSV Upload</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .upload-wrap {
            max-width: 760px;
            margin: 30px auto;
            background: #111827;
            border: 1px solid #263043;
            border-radius: 12px;
            padding: 24px;
            color: #e5e7eb;
        }

        .upload-wrap h2 {
            margin-top: 0;
            margin-bottom: 18px;
        }

        .form-row {
            margin-bottom: 16px;
        }

        .form-row label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-row input[type="file"] {
            width: 100%;
            padding: 10px;
            background: #0b1220;
            color: #e5e7eb;
            border: 1px solid #374151;
            border-radius: 8px;
        }

        .btn-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            border: 1px solid #3b82f6;
            background: #2563eb;
            color: white;
            cursor: pointer;
        }

        .btn.secondary {
            background: #1f2937;
            border-color: #4b5563;
        }

        .msg {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-weight: 600;
        }

        .msg.success {
            background: #052e16;
            border: 1px solid #166534;
            color: #bbf7d0;
        }

        .msg.error {
            background: #3f0d12;
            border: 1px solid #991b1b;
            color: #fecaca;
        }

        .help-box {
            margin-top: 20px;
            padding: 16px;
            border-radius: 10px;
            background: #0b1220;
            border: 1px solid #263043;
        }

        .help-box code {
            background: #1f2937;
            padding: 2px 6px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="container">
    <div class="upload-wrap">
        <h2>Upload Solid Waste CSV</h2>

        <?php if ($message !== ""): ?>
            <div class="msg success"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="msg error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="form-row">
                <label for="csv_file">CSV File</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn">Upload CSV</button>
                <a href="index.php" class="btn secondary">Back to Dashboard</a>
                <a href="solid_waste_logs.php" class="btn secondary">View Solid Waste Logs</a>
            </div>
        </form>

        <div class="help-box">
            <strong>CSV format:</strong><br><br>
            Required headers: <code>date,time,amount</code><br>
            Optional header: <code>comments</code><br><br>

            Example:<br>
            <code>
                date,time,amount,comments<br>
                07/04/2026,06:00,125.5,morning run<br>
                07/04/2026,12:15,98.0,manual entry
            </code>
        </div>
    </div>
</div>
</body>
</html>