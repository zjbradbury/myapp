<?php
require_once "config.php";
requireRole(["admin", "operator"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $pdo->prepare("
        INSERT INTO nozzle_logs
        (source_file, log_date, log_time, nozzle, flow, pressure, min_deg, max_deg, rpm, comments)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        trim($_POST["source_file"] ?? "") ?: null,
        trim($_POST["log_date"] ?? "") ?: null,
        trim($_POST["log_time"] ?? "") ?: null,
        trim($_POST["nozzle"] ?? "") ?: null,
        trim($_POST["flow"] ?? "") ?: null,
        trim($_POST["pressure"] ?? "") ?: null,
        trim($_POST["min_deg"] ?? "") ?: null,
        trim($_POST["max_deg"] ?? "") ?: null,
        trim($_POST["rpm"] ?? "") ?: null,
        trim($_POST["comments"] ?? "") ?: null
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
<div class="container">
    <h2>Add Nozzle Record</h2>
    <form method="post">
        <input type="text" name="source_file" placeholder="Source File">
        <input type="date" name="log_date">
        <input type="time" name="log_time" step="1">
        <input type="number" name="nozzle" placeholder="Nozzle">
        <input type="number" step="0.000001" name="flow" placeholder="Flow">
        <input type="number" step="0.000001" name="pressure" placeholder="Pressure">
        <input type="number" step="0.000001" name="min_deg" placeholder="Min Deg">
        <input type="number" step="0.000001" name="max_deg" placeholder="Max Deg">
        <input type="number" step="0.000001" name="rpm" placeholder="RPM">
        <textarea name="comments" placeholder="Comments"></textarea>
        <button type="submit">Save</button>
    </form>
    <p><a href="nozzle_list.php">Back</a></p>
</div>
</body>
</html>