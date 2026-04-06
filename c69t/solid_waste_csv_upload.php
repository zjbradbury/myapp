<?php
/* =========================
   DATABASE CONNECTION
   ========================= */
$host = "mariadb";
$dbname = "myapp";
$user = "zack";
$pass = "Butcher69";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    die("DB Connection Failed: " . $e->getMessage());
}

/* =========================
   HELPERS
   ========================= */
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalize_date_for_mysql(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    // already yyyy-mm-dd
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if ($dt && $dt->format('Y-m-d') === $value) {
        return $dt->format('Y-m-d');
    }

    // dd/mm/yyyy
    $dt = DateTime::createFromFormat('d/m/Y', $value);
    if ($dt && $dt->format('d/m/Y') === $value) {
        return $dt->format('Y-m-d');
    }

    // d/m/Y
    $dt = DateTime::createFromFormat('j/n/Y', $value);
    if ($dt) {
        return $dt->format('Y-m-d');
    }

    // fallback
    $ts = strtotime($value);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return '';
}

function normalize_time_for_mysql(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{2}:\d{2}$/', $value)) {
        return $value . ':00';
    }

    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
        return $value;
    }

    $ts = strtotime($value);
    if ($ts !== false) {
        return date('H:i:s', $ts);
    }

    return '';
}

/* =========================
   UPLOAD HANDLING
   ========================= */
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed.';
    } else {
        $tmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];

        $handle = fopen($tmpPath, 'r');

        if (!$handle) {
            $error = 'Could not open file.';
        } else {
            // skip header row
            fgetcsv($handle);

            $inserted = 0;
            $skipped = 0;

            $stmt = $pdo->prepare("
                INSERT INTO solid_waste_logs
                (source_file, log_date, log_time, amount, comments)
                VALUES (?, ?, ?, ?, ?)
            ");

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 4) {
                    $skipped++;
                    continue;
                }

                $rawDate = trim($row[0] ?? '');
                $rawTime = trim($row[1] ?? '');
                $amount = trim($row[2] ?? '');
                $comments = trim($row[3] ?? '');

                $logDate = normalize_date_for_mysql($rawDate);
                $logTime = normalize_time_for_mysql($rawTime);

                if ($logDate === '' || $logTime === '' || $amount === '') {
                    $skipped++;
                    continue;
                }

                if (!is_numeric($amount)) {
                    $skipped++;
                    continue;
                }

                $stmt->execute([
                    $fileName,
                    $logDate,
                    $logTime,
                    $amount,
                    $comments
                ]);

                $inserted++;
            }

            fclose($handle);

            $message = "Upload complete — {$inserted} rows inserted, {$skipped} skipped.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Solid Waste CSV Upload</title>
    <style>
        body {
            background: #0b1e2d;
            color: #fff;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .card {
            background: #122c44;
            padding: 20px;
            border-radius: 10px;
            max-width: 520px;
            margin: auto;
        }
        .btn {
            background: #1f4a6e;
            color: #fff;
            border: none;
            padding: 10px 14px;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn:hover {
            background: #295d89;
        }
        .alert {
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 6px;
        }
        .success { background: #1f6e3a; }
        .error { background: #6e1f1f; }
        code {
            color: #fff;
        }
    </style>
</head>
<body>

<div class="card">
    <h2>Upload Solid Waste CSV</h2>

    <?php if ($message !== ''): ?>
        <div class="alert success"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="file" name="csv_file" accept=".csv" required><br><br>
        <button class="btn" type="submit">Upload</button>
    </form>

    <p style="margin-top:15px;font-size:13px;">
        Accepted date formats:<br>
        <code>dd/mm/yyyy</code> or <code>yyyy-mm-dd</code>
    </p>

    <p style="margin-top:10px;font-size:13px;">
        Expected CSV format:<br>
        <code>Date,Time,Amount,Comments</code>
    </p>
</div>

</body>
</html>