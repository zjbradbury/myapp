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
        $_POST["log_date"],
        $_POST["log_time"],
        $_POST["nozzle"],
        $_POST["flow"],
        $_POST["pressure"],
        $_POST["min_deg"],
        $_POST["max_deg"],
        $_POST["rpm"],
        $_POST["comments"]
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

            <div class="input-unit-wrap">
                <input type="number" name="nozzle" placeholder="Nozzle">
                <span class="unit">#</span>
            </div>

            <div class="input-unit-wrap long">
                <input type="number" step="0.1" name="flow" placeholder="Flow">
                <span class="unit">m³/hr</span>
            </div>

            <div class="input-unit-wrap">
                <input type="number" step="0.01" name="pressure" placeholder="Pressure">
                <span class="unit">bar</span>
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
                <span class="unit">rpm</span>
            </div>

            <button type="submit">Save</button>
        </form>
    </div>

    <script>
    const now = new Date();

    document.getElementById('log_date').value = now.toISOString().split('T')[0];
    document.getElementById('log_time').value = now.toTimeString().split(' ')[0];
    </script>

</body>

</html>