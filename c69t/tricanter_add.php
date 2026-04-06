<?php
require_once "config.php";
requireRole(["admin", "operator"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $pdo->prepare("
        INSERT INTO tricanter_logs
        (source_file, log_date, log_time, bowl_speed, screw_speed, bowl_rpm, screw_rpm, impeller, feed_rate, torque, temp, pressure, comments)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        trim($_POST["source_file"] ?? "") ?: null,
        trim($_POST["log_date"] ?? "") ?: null,
        trim($_POST["log_time"] ?? "") ?: null,
        trim($_POST["bowl_speed"] ?? "") ?: null,
        trim($_POST["screw_speed"] ?? "") ?: null,
        trim($_POST["bowl_rpm"] ?? "") ?: null,
        trim($_POST["screw_rpm"] ?? "") ?: null,
        trim($_POST["impeller"] ?? "") ?: null,
        trim($_POST["feed_rate"] ?? "") ?: null,
        trim($_POST["torque"] ?? "") ?: null,
        trim($_POST["temp"] ?? "") ?: null,
        trim($_POST["pressure"] ?? "") ?: null,
        trim($_POST["comments"] ?? "") ?: null
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
<div class="container">
    <h2>Add Tricanter Record</h2>
    <form method="post">
        <input type="text" name="source_file" placeholder="Source File">
        <input type="date" name="log_date">
        <input type="time" name="log_time" step="1">
        <input type="number" step="0.000001" name="bowl_speed" placeholder="Bowl Speed">
        <input type="number" step="0.000001" name="screw_speed" placeholder="Screw Speed">
        <input type="number" step="0.000001" name="bowl_rpm" placeholder="Bowl RPM">
        <input type="number" step="0.000001" name="screw_rpm" placeholder="Screw RPM">
        <input type="text" name="impeller" placeholder="Impeller">
        <input type="number" step="0.000001" name="feed_rate" placeholder="Feed Rate">
        <input type="number" step="0.000001" name="torque" placeholder="Torque">
        <input type="number" step="0.000001" name="temp" placeholder="Temp">
        <input type="number" step="0.000001" name="pressure" placeholder="Pressure">
        <textarea name="comments" placeholder="Comments"></textarea>
        <button type="submit">Save</button>
    </form>
    <p><a href="tricanter_list.php">Back</a></p>
</div>
</body>
</html>