<?php
require_once __DIR__ . "/functions.php";

$message = $_GET["msg"] ?? "";

$dateValue = default_form_date();
$timeValue = default_form_time();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
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
        $resultMessage = "Please complete all Nozzle fields.";
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

        [$ok, $result] = write_parser_file("NOZZLE", $fields, get_data_dir());
        $resultMessage = $ok ? "Nozzle file created: " . $result : $result;
    }

    header("Location: nozzle.php?msg=" . urlencode($resultMessage));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nozzle Entry</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #0f1115;
            color: #ffffff;
        }

        .wrap {
            max-width: 800px;
            margin: 0 auto;
            padding: 18px 14px 28px;
        }

        .topbar, .card, .message {
            background: #171a21;
            border: 1px solid #2b313d;
            border-radius: 16px;
        }

        .topbar {
            padding: 16px;
            margin-bottom: 16px;
        }

        .topbar a {
            text-decoration: none;
            color: #ffffff;
            background: #2a3749;
            border: 1px solid #41526d;
            padding: 10px 14px;
            border-radius: 12px;
            display: inline-block;
            font-weight: bold;
        }

        .message {
            padding: 14px;
            margin-bottom: 16px;
        }

        .card {
            padding: 20px;
        }

        h1 {
            margin-top: 0;
        }

        label {
            display: block;
            margin-top: 14px;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input, textarea, button {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: 1px solid #394253;
            background: #0f131a;
            color: #ffffff;
            font-size: 16px;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        button {
            margin-top: 20px;
            background: #2a3749;
            border: 1px solid #41526d;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #33445b;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <a href="index.php">← Back to Home</a>
        </div>

        <?php if ($message !== ""): ?>
            <div class="message"><?php echo h($message); ?></div>
        <?php endif; ?>

        <div class="card">
            <h1>Nozzle Entry</h1>

            <form method="post" action="">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?php echo h($dateValue); ?>" required>

                <label for="time">Time</label>
                <input type="time" id="time" name="time" value="<?php echo h($timeValue); ?>" required>

                <label for="nozzle">Nozzle</label>
                <input type="number" step="any" id="nozzle" name="nozzle" required>

                <label for="flow">Flow</label>
                <input type="number" step="any" id="flow" name="flow" required>

                <label for="pressure">Pressure</label>
                <input type="number" step="any" id="pressure" name="pressure" required>

                <label for="min_deg">Min Deg</label>
                <input type="number" step="any" id="min_deg" name="min_deg" required>

                <label for="max_deg">Max Deg</label>
                <input type="number" step="any" id="max_deg" name="max_deg" required>

                <label for="rpm">RPM</label>
                <input type="number" step="any" id="rpm" name="rpm" required>

                <label for="comments">Comments</label>
                <textarea id="comments" name="comments">manual_web_entry</textarea>

                <button type="submit">Save Nozzle Entry</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("form").forEach(function (form) {
            form.addEventListener("submit", function () {
                const btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = "Saving...";
                }
            });
        });
    });
    </script>
</body>
</html>