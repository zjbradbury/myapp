<?php
$message = "";

function clean_value($value) {
    return trim(str_replace(["\r", "\n"], " ", (string)$value));
}

function format_date_ddmmyyyy($value) {
    $value = trim((string)$value);

    if ($value === "") {
        return "";
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return "";
    }

    return date("d/m/Y", $ts);
}

function write_parser_file($sectionName, $fields, $targetDir) {
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return [false, "Failed to create target folder."];
        }
    }

    $stamp = date("Ymd_His");
    $unique = substr(str_replace(".", "", (string)microtime(true)), -6);
    $fileName = $sectionName . "_" . $stamp . "_" . $unique . ".txt";
    $filePath = rtrim($targetDir, "/") . "/" . $fileName;

    $lines = [];
    $lines[] = "[" . $sectionName . "]";

    foreach ($fields as $key => $value) {
        $lines[] = $key . "=" . clean_value($value);
    }

    $content = implode(PHP_EOL, $lines) . PHP_EOL;

    if (file_put_contents($filePath, $content) === false) {
        return [false, "Failed to write file."];
    }

    return [true, $fileName];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $dataDir = __DIR__ . "/data";
    $entryType = trim($_POST["entry_type"] ?? "");

    if ($entryType === "NOZZLE") {
        $date = format_date_ddmmyyyy($_POST["date"] ?? "");
        $time = trim($_POST["time"] ?? "");
        $nozzle = trim($_POST["nozzle"] ?? "");
        $flow = trim($_POST["flow"] ?? "");
        $pressure = trim($_POST["pressure"] ?? "");
        $minDeg = trim($_POST["min_deg"] ?? "");
        $maxDeg = trim($_POST["max_deg"] ?? "");
        $rpm = trim($_POST["rpm"] ?? "");
        $comments = trim($_POST["comments"] ?? "manual_web_entry");

        if ($date === "" || $time === "" || $nozzle === "" || $flow === "" || $pressure === "" || $minDeg === "" || $maxDeg === "" || $rpm === "") {
            $message = "Please complete all Nozzle fields.";
        } else {
            $fields = [
                "Date" => $date,
                "Time" => $time,
                "Nozzle" => $nozzle,
                "Flow" => $flow,
                "Pressure" => $pressure,
                "Min Deg" => $minDeg,
                "Max Deg" => $maxDeg,
                "RPM" => $rpm,
                "Comments" => $comments
            ];

            [$ok, $result] = write_parser_file("NOZZLE", $fields, $dataDir);
            $message = $ok
                ? "Nozzle file created: " . htmlspecialchars($result, ENT_QUOTES, "UTF-8")
                : $result;
        }

    } elseif ($entryType === "TRICANTER") {
        $date = format_date_ddmmyyyy($_POST["date"] ?? "");
        $time = trim($_POST["time"] ?? "");
        $bowlSpeed = trim($_POST["bowl_speed"] ?? "");
        $screwSpeed = trim($_POST["screw_speed"] ?? "");
        $bowlRpm = trim($_POST["bowl_rpm"] ?? "");
        $screwRpm = trim($_POST["screw_rpm"] ?? "");
        $impeller = trim($_POST["impeller"] ?? "");
        $feedRate = trim($_POST["feed_rate"] ?? "");
        $torque = trim($_POST["torque"] ?? "");
        $temp = trim($_POST["temp"] ?? "");
        $pressure = trim($_POST["pressure"] ?? "");
        $comments = trim($_POST["comments"] ?? "manual_web_entry");

        if ($date === "" || $time === "" || $bowlSpeed === "" || $screwSpeed === "" || $bowlRpm === "" || $screwRpm === "" || $feedRate === "" || $torque === "" || $temp === "" || $pressure === "") {
            $message = "Please complete all required Tricanter fields.";
        } else {
            $fields = [
                "Date" => $date,
                "Time" => $time,
                "Bowl Speed" => $bowlSpeed,
                "Screw Speed" => $screwSpeed,
                "Bowl RPM" => $bowlRpm,
                "Screw RPM" => $screwRpm,
                "Impeller" => $impeller,
                "Feed Rate" => $feedRate,
                "Torque" => $torque,
                "Temp" => $temp,
                "Pressure" => $pressure,
                "Comments" => $comments
            ];

            [$ok, $result] = write_parser_file("TRICANTER", $fields, $dataDir);
            $message = $ok
                ? "Tricanter file created: " . htmlspecialchars($result, ENT_QUOTES, "UTF-8")
                : $result;
        }

    } elseif ($entryType === "SOLID_WASTE") {
        $date = format_date_ddmmyyyy($_POST["date"] ?? "");
        $time = trim($_POST["time"] ?? "");
        $amount = trim($_POST["amount"] ?? "");
        $comments = trim($_POST["comments"] ?? "manual_web_entry");

        if ($date === "" || $time === "" || $amount === "") {
            $message = "Please complete all Solid Waste fields.";
        } else {
            $fields = [
                "Date" => $date,
                "Time" => $time,
                "Start Level" => $amount,
                "Stop Level" => "0",
                "Comments" => $comments
            ];

            [$ok, $result] = write_parser_file("SOLID_WASTE", $fields, $dataDir);
            $message = $ok
                ? "Solid Waste file created: " . htmlspecialchars($result, ENT_QUOTES, "UTF-8")
                : $result;
        }

    } else {
        $message = "Unknown entry type.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Process Data Entry</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #111111;
            color: #ffffff;
            font-family: Arial, sans-serif;
        }

        .wrap {
            max-width: 1400px;
            margin: 0 auto;
        }

        h1 {
            margin-bottom: 20px;
        }

        .message {
            margin-bottom: 20px;
            padding: 12px 14px;
            border-radius: 8px;
            background: #222222;
            border: 1px solid #444444;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 20px;
        }

        .card {
            background: #1b1b1b;
            border: 1px solid #333333;
            border-radius: 12px;
            padding: 18px;
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 12px;
        }

        label {
            display: block;
            margin-top: 10px;
            margin-bottom: 4px;
            font-weight: bold;
        }

        input,
        textarea,
        button {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #444444;
            background: #0f0f0f;
            color: #ffffff;
            font-size: 14px;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        button {
            margin-top: 16px;
            cursor: pointer;
            background: #2b2b2b;
            font-weight: bold;
        }

        button:hover {
            background: #3a3a3a;
        }

        .note {
            margin-top: 20px;
            color: #bbbbbb;
            font-size: 13px;
            line-height: 1.5;
        }

        .small {
            color: #aaaaaa;
            font-size: 12px;
            margin-top: 6px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Manual Process Data Entry</h1>

    <?php if ($message !== ""): ?>
        <div class="message"><?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?></div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <h2>NOZZLE</h2>
            <form method="post">
                <input type="hidden" name="entry_type" value="NOZZLE">

                <label for="nz_date">Date</label>
                <input type="date" id="nz_date" name="date" required>

                <label for="nz_time">Time</label>
                <input type="time" id="nz_time" name="time" required>

                <label for="nz_nozzle">Nozzle</label>
                <input type="number" step="any" id="nz_nozzle" name="nozzle" required>

                <label for="nz_flow">Flow</label>
                <input type="number" step="any" id="nz_flow" name="flow" required>

                <label for="nz_pressure">Pressure</label>
                <input type="number" step="any" id="nz_pressure" name="pressure" required>

                <label for="nz_min_deg">Min Deg</label>
                <input type="number" step="any" id="nz_min_deg" name="min_deg" required>

                <label for="nz_max_deg">Max Deg</label>
                <input type="number" step="any" id="nz_max_deg" name="max_deg" required>

                <label for="nz_rpm">RPM</label>
                <input type="number" step="any" id="nz_rpm" name="rpm" required>

                <label for="nz_comments">Comments</label>
                <textarea id="nz_comments" name="comments">manual_web_entry</textarea>

                <button type="submit">Save Nozzle Entry</button>
            </form>
        </div>

        <div class="card">
            <h2>TRICANTER</h2>
            <form method="post">
                <input type="hidden" name="entry_type" value="TRICANTER">

                <label for="tr_date">Date</label>
                <input type="date" id="tr_date" name="date" required>

                <label for="tr_time">Time</label>
                <input type="time" id="tr_time" name="time" required>

                <label for="tr_bowl_speed">Bowl Speed</label>
                <input type="number" step="any" id="tr_bowl_speed" name="bowl_speed" required>

                <label for="tr_screw_speed">Screw Speed</label>
                <input type="number" step="any" id="tr_screw_speed" name="screw_speed" required>

                <label for="tr_bowl_rpm">Bowl RPM</label>
                <input type="number" step="any" id="tr_bowl_rpm" name="bowl_rpm" required>

                <label for="tr_screw_rpm">Screw RPM</label>
                <input type="number" step="any" id="tr_screw_rpm" name="screw_rpm" required>

                <label for="tr_impeller">Impeller</label>
                <input type="text" id="tr_impeller" name="impeller">

                <label for="tr_feed_rate">Feed Rate</label>
                <input type="number" step="any" id="tr_feed_rate" name="feed_rate" required>

                <label for="tr_torque">Torque</label>
                <input type="number" step="any" id="tr_torque" name="torque" required>

                <label for="tr_temp">Temp</label>
                <input type="number" step="any" id="tr_temp" name="temp" required>

                <label for="tr_pressure">Pressure</label>
                <input type="number" step="any" id="tr_pressure" name="pressure" required>

                <label for="tr_comments">Comments</label>
                <textarea id="tr_comments" name="comments">manual_web_entry</textarea>

                <button type="submit">Save Tricanter Entry</button>
            </form>
        </div>

        <div class="card">
            <h2>SOLID WASTE</h2>
            <form method="post">
                <input type="hidden" name="entry_type" value="SOLID_WASTE">

                <label for="sw_date">Date</label>
                <input type="date" id="sw_date" name="date" required>

                <label for="sw_time">Time</label>
                <input type="time" id="sw_time" name="time" required>

                <label for="sw_amount">Amount</label>
                <input type="number" step="any" id="sw_amount" name="amount" required>

                <div class="small">This writes Start Level = Amount entered, and Stop Level = 0</div>

                <label for="sw_comments">Comments</label>
                <textarea id="sw_comments" name="comments">manual_web_entry</textarea>

                <button type="submit">Save Solid Waste Entry</button>
            </form>
        </div>
    </div>

    <div class="note">
        Files are written to:<br>
        <strong><?php echo htmlspecialchars(__DIR__ . "/data", ENT_QUOTES, "UTF-8"); ?></strong>
    </div>
</div>
</body>
</html>