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
        "web_entry_" . ($_SESSION['username'] ?? 'unknown'),
        nullIfBlank($_POST["log_date"] ?? null),
        nullIfBlank($_POST["log_time"] ?? null),
        nullIfBlank($_POST["nozzle"] ?? null),
        nullIfBlank($_POST["flow"] ?? null),
        nullIfBlank($_POST["pressure"] ?? null),
        nullIfBlank($_POST["min_deg"] ?? null),
        nullIfBlank($_POST["max_deg"] ?? null),
        nullIfBlank($_POST["rpm"] ?? null),
        nullIfBlank($_POST["comments"] ?? null),
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
        <input type="date" name="log_date" value="<?= h($row["log_date"]) ?>">
        <input type="time" name="log_time" step="1" value="<?= h($row["log_time"]) ?>">

        <div class="input-unit-wrap">
            <input type="number" name="nozzle" value="<?= h($row["nozzle"]) ?>" placeholder="Nozzle">
            <span class="unit">N</span>
        </div>

        <div class="input-unit-wrap long">
            <input type="number" step="0.1" name="flow" value="<?= h($row["flow"]) ?>" placeholder="Flow">
            <span class="unit">m3/hr</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.01" name="pressure" value="<?= h($row["pressure"]) ?>" placeholder="Pressure">
            <span class="unit">BAR</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" name="min_deg" value="<?= h($row["min_deg"]) ?>" placeholder="Min Deg">
            <span class="unit">°</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" name="max_deg" value="<?= h($row["max_deg"]) ?>" placeholder="Max Deg">
            <span class="unit">°</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.1" name="rpm" value="<?= h($row["rpm"]) ?>" placeholder="RPM">
            <span class="unit">RPM</span>
        </div>

        <textarea name="comments" placeholder="Comments"><?= h($row["comments"]) ?></textarea>

        <button type="submit">Update</button>
    </form>
</div>

</body>
</html>