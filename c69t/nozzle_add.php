<?php
require_once "config.php";
requireRole(["admin", "operator"]);

$currentUser = $_SESSION['username'] ?? 'unknown';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $pdo->prepare("
        INSERT INTO nozzle_logs
        (source_file, log_date, log_time, nozzle, flow, pressure, min_deg, max_deg, rpm, comments)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        "web_entry_" . $currentUser,
        nullIfBlank($_POST["log_date"] ?? null),
        nullIfBlank($_POST["log_time"] ?? null),
        nullIfBlank($_POST["nozzle"] ?? null),
        nullIfBlank($_POST["flow"] ?? null),
        nullIfBlank($_POST["pressure"] ?? null),
        nullIfBlank($_POST["min_deg"] ?? null),
        nullIfBlank($_POST["max_deg"] ?? null),
        nullIfBlank($_POST["rpm"] ?? null),
        nullIfBlank($_POST["comments"] ?? null)
    ]);

    header("Location: nozzle_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Nozzle Record</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="container">
    <h2>Add Nozzle Record</h2>

    <form method="post">
        <input type="date" name="log_date" id="log_date">
        <input type="time" name="log_time" id="log_time" step="1">

        <div class="input-unit-wrap">
            <input type="number" name="nozzle" placeholder="Nozzle">
            <span class="unit">N</span>
        </div>

        <div class="input-unit-wrap long">
            <input type="number" step="0.1" name="flow" placeholder="Flow">
            <span class="unit">m3/hr</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.01" name="pressure" placeholder="Pressure">
            <span class="unit">BAR</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" name="min_deg" placeholder="Min Deg">
            <span class="unit">°</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" name="max_deg" placeholder="Max Deg">
            <span class="unit">°</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.1" name="rpm" placeholder="RPM">
            <span class="unit">RPM</span>
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