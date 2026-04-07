<?php
require_once "config.php";
requireRole(["admin", "operator"]);

$currentUser = $_SESSION['username'] ?? 'unknown';

function nullIfBlank($value) {
    $value = trim((string)($value ?? ''));
    return $value === '' ? null : $value;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $pdo->prepare("
        INSERT INTO tricanter_logs
        (source_file, log_date, log_time, bowl_speed, screw_speed, bowl_rpm, screw_rpm, impeller, feed_rate, torque, temp, pressure, comments)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        "web_entry_" . $currentUser,
        nullIfBlank($_POST["log_date"] ?? null),
        nullIfBlank($_POST["log_time"] ?? null),
        nullIfBlank($_POST["bowl_speed"] ?? null),
        nullIfBlank($_POST["screw_speed"] ?? null),
        nullIfBlank($_POST["bowl_rpm"] ?? null),
        nullIfBlank($_POST["screw_rpm"] ?? null),
        nullIfBlank($_POST["impeller"] ?? null),
        nullIfBlank($_POST["feed_rate"] ?? null),
        nullIfBlank($_POST["torque"] ?? null),
        nullIfBlank($_POST["temp"] ?? null),
        nullIfBlank($_POST["pressure"] ?? null),
        nullIfBlank($_POST["comments"] ?? null)
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

        <div class="input-unit-wrap">
            <input type="number" name="bowl_speed" placeholder="Bowl Speed">
            <span class="unit">%</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.01" name="screw_speed" placeholder="Screw Speed">
            <span class="unit">%</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" name="bowl_rpm" placeholder="Bowl RPM">
            <span class="unit">RPM</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" name="screw_rpm" placeholder="Screw RPM">
            <span class="unit">RPM</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" name="impeller" placeholder="Impeller">
            <span class="unit">%</span>
        </div>

        <div class="input-unit-wrap long">
            <input type="number" step="0.01" name="feed_rate" placeholder="Feed Rate">
            <span class="unit">m3/hr</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.1" name="torque" placeholder="Torque">
            <span class="unit">%</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.1" name="temp" placeholder="Temp">
            <span class="unit">°C</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.001" name="pressure" placeholder="Pressure">
            <span class="unit">BAR</span>
        </div>

        <textarea name="comments" placeholder="Comments"></textarea>

        <button type="submit">Save</button>
    </form>
</div>

<script>
(function () {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');

    document.getElementById('log_date').value = `${year}-${month}-${day}`;
    document.getElementById('log_time').value = `${hours}:${minutes}:${seconds}`;
})();
</script>

</body>
</html>