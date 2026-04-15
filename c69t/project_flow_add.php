<?php
require_once "config.php";
requireRole(["admin", "operator"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $stmt = $pdo->prepare("
        INSERT INTO project_flow_logs
        (
            source_file,
            log_date,
            log_time,
            total_recovered_oil,
            total_recovered_water,
            total_solid_waste,
            total_tricanter,
            total_nozzle,
            comments
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        "web_entry_" . ($_SESSION['username'] ?? 'unknown'),
        $_POST["log_date"] ?: null,
        $_POST["log_time"] ?: null,
        $_POST["oil"] ?: null,
        $_POST["water"] ?: null,
        $_POST["solid"] ?: null,
        $_POST["tricanter"] ?: null,
        $_POST["nozzle"] ?: null,
        $_POST["comments"] ?: null
    ]);

    header("Location: project_flow_list.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Project Flow</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php require_once "nav.php"; ?>

<div class="container">

    <h2>Add Project Flow</h2>

    <form method="post" class="form-card">

        <label>Date</label>
        <input type="date" name="log_date">

        <label>Time</label>
        <input type="time" name="log_time">

        <label>Recovered Oil</label>
        <input type="number" step="0.0001" name="oil">

        <label>Recovered Water</label>
        <input type="number" step="0.0001" name="water">

        <label>Solid Waste</label>
        <input type="number" step="0.0001" name="solid">

        <label>Tricanter</label>
        <input type="number" step="0.0001" name="tricanter">

        <label>Nozzle</label>
        <input type="number" step="0.0001" name="nozzle">

        <label>Comments</label>
        <textarea name="comments"></textarea>

        <button class="btn">Save</button>

    </form>

</div>
</body>
</html>