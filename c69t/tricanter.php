<?php
require_once __DIR__ . "/functions.php";

$message = $_GET["msg"] ?? "";

$dateValue = default_form_date();
$timeValue = default_form_time();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
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
        $resultMessage = "Please complete all required Tricanter fields.";
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

        [$ok, $result] = write_parser_file("TRICANTER", $fields, get_data_dir());
        $resultMessage = $ok ? "Tricanter file created: " . $result : $result;
    }

    header("Location: tricanter.php?msg=" . urlencode($resultMessage));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tricanter Entry</title>

    <link rel="icon" type="image/png" href="favicon.png">
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
            <h1>Tricanter Entry</h1>
            <p class="form-sub">Creates a file in your exact TRICANTER parser format.</p>

            <form method="post" action="">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?php echo h($dateValue); ?>" required>

                <label for="time">Time</label>
                <input type="time" id="time" name="time" value="<?php echo h($timeValue); ?>" required>

                <label for="bowl_speed">Bowl Speed</label>
                <input type="number" step="any" id="bowl_speed" name="bowl_speed" required>

                <label for="screw_speed">Screw Speed</label>
                <input type="number" step="any" id="screw_speed" name="screw_speed" required>

                <label for="bowl_rpm">Bowl RPM</label>
                <input type="number" step="any" id="bowl_rpm" name="bowl_rpm" required>

                <label for="screw_rpm">Screw RPM</label>
                <input type="number" step="any" id="screw_rpm" name="screw_rpm" required>

                <label for="impeller">Impeller</label>
                <input type="text" id="impeller" name="impeller">

                <label for="feed_rate">Feed Rate</label>
                <input type="number" step="any" id="feed_rate" name="feed_rate" required>

                <label for="torque">Torque</label>
                <input type="number" step="any" id="torque" name="torque" required>

                <label for="temp">Temp</label>
                <input type="number" step="any" id="temp" name="temp" required>

                <label for="pressure">Pressure</label>
                <input type="number" step="any" id="pressure" name="pressure" required>

                <label for="comments">Comments</label>
                <textarea id="comments" name="comments">manual_web_entry</textarea>

                <button type="submit">Save Tricanter Entry</button>
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