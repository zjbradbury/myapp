<?php
require_once "config.php";
requireRole(["admin", "operator"]);

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

$stmt = $pdo->prepare("SELECT * FROM nozzle_logs WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("Record not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $pdo->prepare("
        UPDATE nozzle_logs
        SET source_file = ?, log_date = ?, log_time = ?, nozzle = ?, flow = ?, pressure = ?, min_deg = ?, max_deg = ?, rpm = ?, comments = ?
        WHERE id = ?
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
        trim($_POST["comments"] ?? "") ?: null,
        $id
    ]);

    header("Location: nozzle_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Nozzle Record</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require_once "nav.php"; ?>
<div class="container">
    <h2>Edit Nozzle Record</h2>
    <form method="post">
        <input type="text" name="source_file" value="<?= h($row["source_file"]) ?>" placeholder="Source File">
        <input type="date" name="log_date" value="<?= h($row["log_date"]) ?>">
        <input type="time" name="log_time" step="1" value="<?= h($row["log_time"]) ?>">
        <input type="number" name="nozzle" value="<?= h($row["nozzle"]) ?>" placeholder="Nozzle">
        <input type="number" step="0.000001" name="flow" value="<?= h($row["flow"]) ?>" placeholder="Flow">
        <input type="number" step="0.000001" name="pressure" value="<?= h($row["pressure"]) ?>" placeholder="Pressure">
        <input type="number" step="0.000001" name="min_deg" value="<?= h($row["min_deg"]) ?>" placeholder="Min Deg">
        <input type="number" step="0.000001" name="max_deg" value="<?= h($row["max_deg"]) ?>" placeholder="Max Deg">
        <input type="number" step="0.000001" name="rpm" value="<?= h($row["rpm"]) ?>" placeholder="RPM">
        <textarea name="comments" placeholder="Comments"><?= h($row["comments"]) ?></textarea>
        <button type="submit">Update</button>
    </form>
</div>
</body>
</html>