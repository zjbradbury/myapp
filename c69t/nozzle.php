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
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="wrap-narrow">
        <div class="topbar">
            <a href="index.php">← Back to Home</a>
        </div>

        <?php if ($message !== ""): ?>
        <div class="message"><?php echo h($message); ?></div>
        <?php endif; ?>

        <div class="card">
            <h1>Nozzle Entry</h1>
            <!-- <p class="form-sub">Creates a file in your exact NOZZLE parser format.</p> -->

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
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll("form").forEach(function(form) {
            form.addEventListener("submit", function() {
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