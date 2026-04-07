<?php
require_once "config.php";
requireRole(["admin", "operator"]);

$currentUser = $_SESSION['username'] ?? 'unknown';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $pdo->prepare("
        INSERT INTO tricanter_logs
        (source_file, log_date, log_time, bowl_speed, screw_speed, bowl_rpm, screw_rpm, impeller, feed_rate, torque, temp, pressure, comments)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        "web_entry_" . $currentUser, // AUTO SOURCE
        $_POST["log_date"],
        $_POST["log_time"],
        $_POST["bowl_speed"],
        $_POST["screw_speed"],
        $_POST["bowl_rpm"],
        $_POST["screw_rpm"],
        $_POST["impeller"],
        $_POST["feed_rate"],
        $_POST["torque"],
        $_POST["temp"],
        $_POST["pressure"],
        $_POST["comments"]
    ]);

    header("Location: tricanter_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Add Tricanter Record</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php require_once "nav.php"; ?>

    <div class="container">
        <h2>Add Tricanter Record</h2>

        <form method="post">

            <input type="date" name="log_date" id="log_date">
            <input type="time" name="log_time" id="log_time" step="1">

            <div class="input-with-unit">
                <input class="value-input" type="number" step="1" name="bowl_speed" placeholder="Bowl Speed">
                <input class="unit-input" type="text" value="%" disabled tabindex="-1">
            </div>

            <div class="input-with-unit">
                <input class="value-input" type="number" step="0.01" name="screw_speed" placeholder="Screw Speed">
                <input class="unit-input" type="text" value="%" disabled tabindex="-1">
            </div>

            <div class="input-with-unit">
                <input class="value-input" type="number" step="1" name="bowl_rpm" placeholder="Bowl RPM">
                <input class="unit-input" type="text" value="RPM" disabled tabindex="-1">
            </div>

            <div class="input-with-unit">
                <input class="value-input" type="number" step="1" name="screw_rpm" placeholder="Screw RPM">
                <input class="unit-input" type="text" value="RPM" disabled tabindex="-1">
            </div>

            <div class="input-with-unit">
                <input class="value-input" type="number" step="1" name="impeller" placeholder="Impeller">
                <input class="unit-input" type="text" value="%" disabled tabindex="-1">
            </div>

            <div class="input-with-unit">
                <input class="value-input" type="number" step="0.01" name="feed_rate" placeholder="Feed Rate">
                <input class="unit-input wide-unit" type="text" value="m3/hr" disabled tabindex="-1">
            </div>

            <div class="input-with-unit">
                <input class="value-input" type="number" step="0.1" name="torque" placeholder="Torque">
                <input class="unit-input" type="text" value="%" disabled tabindex="-1">
            </div>

            <div class="input-with-unit">
                <input class="value-input" type="number" step="0.1" name="temp" placeholder="Temp">
                <input class="unit-input" type="text" value="°C" disabled tabindex="-1">
            </div>

            <div class="input-with-unit">
                <input class="value-input" type="number" step="0.001" name="pressure" placeholder="Pressure">
                <input class="unit-input" type="text" value="bar" disabled tabindex="-1">
            </div>

            <textarea name="comments" placeholder="Comments"></textarea>

            <button type="submit">Save</button>
        </form>
    </div>

    <!-- AUTO DATE/TIME SCRIPT -->
    <script>
    const now = new Date();

    document.getElementById('log_date').value = now.toISOString().split('T')[0];

    document.getElementById('log_time').value =
        now.toTimeString().split(' ')[0];
    </script>

</body>

</html>