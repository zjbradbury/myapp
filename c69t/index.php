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

function write_parser_file($type, $fields, $targetDir) {
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return [false, "Failed to create target folder."];
        }
    }

    $stamp = date("Ymd_His");
    $unique = substr(str_replace(".", "", (string)microtime(true)), -6);
    $fileName = $type . "_" . $stamp . "_" . $unique . ".txt";
    $filePath = rtrim($targetDir, "/") . "/" . $fileName;

    $lines = [];
    $lines[] = "[" . $type . "]";

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

    if ($entryType === "SolidWaste") {
        $date = format_date_ddmmyyyy($_POST["date"] ?? "");
        $time = trim($_POST["time"] ?? "");
        $comments = trim($_POST["comments"] ?? "manual_web_entry");
        $solidWasteLevelKg = trim($_POST["solid_waste_level_kg"] ?? "");

        if ($date === "" || $time === "" || $solidWasteLevelKg === "") {
            $message = "Please complete all Solid Waste required fields.";
        } else {
            $fields = [
                "Date" => $date,
                "Time" => $time,
                "SolidWasteLevelKG" => $solidWasteLevelKg,
                "Comments" => $comments
            ];

            [$ok, $result] = write_parser_file("SolidWaste", $fields, $dataDir);
            $message = $ok
                ? "Solid Waste file created: " . htmlspecialchars($result, ENT_QUOTES, "UTF-8")
                : $result;
        }

    } elseif ($entryType === "Nozzle") {
        $date = format_date_ddmmyyyy($_POST["date"] ?? "");
        $time = trim($_POST["time"] ?? "");
        $comments = trim($_POST["comments"] ?? "manual_web_entry");
        $nozzleNo = trim($_POST["nozzle_no"] ?? "");
        $nozzleVerticalDeg = trim($_POST["nozzle_vertical_deg"] ?? "");

        if ($date === "" || $time === "" || $nozzleNo === "" || $nozzleVerticalDeg === "") {
            $message = "Please complete all Nozzle required fields.";
        } else {
            $fields = [
                "Date" => $date,
                "Time" => $time,
                "NozzleNo" => $nozzleNo,
                "NozzleVerticalDeg" => $nozzleVerticalDeg,
                "Comments" => $comments
            ];

            [$ok, $result] = write_parser_file("Nozzle", $fields, $dataDir);
            $message = $ok
                ? "Nozzle file created: " . htmlspecialchars($result, ENT_QUOTES, "UTF-8")
                : $result;
        }

    } elseif ($entryType === "Tricanter") {
        $date = format_date_ddmmyyyy($_POST["date"] ?? "");
        $time = trim($_POST["time"] ?? "");
        $comments = trim($_POST["comments"] ?? "manual_web_entry");

        if ($date === "" || $time === "") {
            $message = "Please complete Date and Time for Tricanter.";
        } else {
            $fields = [
                "Date" => $date,
                "Time" => $time,
                "BowlSpeedPercent" => trim($_POST["bowl_speed_percent"] ?? ""),
                "ScrewSpeedPercent" => trim($_POST["screw_speed_percent"] ?? ""),
                "BowlRPM" => trim($_POST["bowl_rpm"] ?? ""),
                "ScrewRPM" => trim($_POST["screw_rpm"] ?? ""),
                "ProcessFlowM3hr" => trim($_POST["process_flow_m3hr"] ?? ""),
                "TorquePercent" => trim($_POST["torque_percent"] ?? ""),
                "ProcessTemp" => trim($_POST["process_temp"] ?? ""),
                "ProcessPressureBar" => trim($_POST["process_pressure_bar"] ?? ""),
                "Comments" => $comments
            ];

            [$ok, $result] = write_parser_file("Tricanter", $fields, $dataDir);
            $message = $ok
                ? "Tricanter file created: " . htmlspecialchars($result, ENT_QUOTES, "UTF-8")
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
            max-width: 1300px;
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
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
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
            <h2>Solid Waste</h2>
            <form method="post">
                <input type="hidden" name="entry_type" value="SolidWaste">

                <label for="sw_date">Date</label>
                <input type="date" id="sw_date" name="date" required>

                <label for="sw_time">Time</label>
                <input type="time" id="sw_time" name="time" required>

                <label for="sw_level">Solid Waste Level KG</label>
                <input type="number" step="any" id="sw_level" name="solid_waste_level_kg" required>

                <label for="sw_comments">Comments</label>
                <textarea id="sw_comments" name="comments">manual_web_entry</textarea>

                <button type="submit">Save Solid Waste Entry</button>
            </form>
        </div>

        <div class="card">
            <h2>Nozzle</h2>
            <form method="post">
                <input type="hidden" name="entry_type" value="Nozzle">

                <label for="nz_date">Date</label>
                <input type="date" id="nz_date" name="date" required>

                <label for="nz_time">Time</label>
                <input type="time" id="nz_time" name="time" required>

                <label for="nz_no">Nozzle Number</label>
                <input type="number" id="nz_no" name="nozzle_no" required>

                <label for="nz_deg">Nozzle Vertical Deg</label>
                <input type="number" step="any" id="nz_deg" name="nozzle_vertical_deg" required>

                <label for="nz_comments">Comments</label>
                <textarea id="nz_comments" name="comments">manual_web_entry</textarea>

                <button type="submit">Save Nozzle Entry</button>
            </form>
        </div>

        <div class="card">
            <h2>Tricanter</h2>
            <form method="post">
                <input type="hidden" name="entry_type" value="Tricanter">

                <label for="tr_date">Date</label>
                <input type="date" id="tr_date" name="date" required>

                <label for="tr_time">Time</label>
                <input type="time" id="tr_time" name="time" required>

                <label for="tr_bowl_speed">Bowl Speed Percent</label>
                <input type="number" step="any" id="tr_bowl_speed" name="bowl_speed_percent">

                <label for="tr_screw_speed">Screw Speed Percent</label>
                <input type="number" step="any" id="tr_screw_speed" name="screw_speed_percent">

                <label for="tr_bowl_rpm">Bowl RPM</label>
                <input type="number" step="any" id="tr_bowl_rpm" name="bowl_rpm">

                <label for="tr_screw_rpm">Screw RPM</label>
                <input type="number" step="any" id="tr_screw_rpm" name="screw_rpm">

                <label for="tr_flow">Process Flow M3hr</label>
                <input type="number" step="any" id="tr_flow" name="process_flow_m3hr">

                <label for="tr_torque">Torque Percent</label>
                <input type="number" step="any" id="tr_torque" name="torque_percent">

                <label for="tr_temp">Process Temp</label>
                <input type="number" step="any" id="tr_temp" name="process_temp">

                <label for="tr_pressure">Process Pressure Bar</label>
                <input type="number" step="any" id="tr_pressure" name="process_pressure_bar">

                <label for="tr_comments">Comments</label>
                <textarea id="tr_comments" name="comments">manual_web_entry</textarea>

                <button type="submit">Save Tricanter Entry</button>
            </form>
        </div>
    </div>

    <div class="note">
        Files are written in this format:<br>
        [Tricanter]<br>
        Date=02/04/2026<br>
        Time=14:35<br>
        Comments=manual_web_entry
    </div>
</div>
</body>
</html>