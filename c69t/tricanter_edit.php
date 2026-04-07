<?php
require_once "config.php";
requireRole(["admin", "operator"]);

function nullIfBlank($value) {
    $value = trim((string)($value ?? ''));
    return $value === '' ? null : $value;
}

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

$stmt = $pdo->prepare("SELECT * FROM tricanter_logs WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("Record not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $pdo->prepare("
        UPDATE tricanter_logs
        SET source_file = ?, log_date = ?, log_time = ?, bowl_speed = ?, screw_speed = ?, bowl_rpm = ?, screw_rpm = ?, impeller = ?, feed_rate = ?, torque = ?, temp = ?, pressure = ?, comments = ?
        WHERE id = ?
    ");

    $stmt->execute([
        "web_entry_" . ($_SESSION['username'] ?? 'unknown'),
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
        nullIfBlank($_POST["comments"] ?? null),
        $id
    ]);

    header("Location: tricanter_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Tricanter Record</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="container">
    <h2>Edit Tricanter Record</h2>

    <form method="post">
        <input type="date" name="log_date" value="<?= h($row["log_date"]) ?>">
        <input type="time" name="log_time" step="1" value="<?= h($row["log_time"]) ?>">

        <div class="input-unit-wrap">
            <input type="number" name="bowl_speed" value="<?= h($row["bowl_speed"]) ?>" placeholder="Bowl Speed">
            <span class="unit">%</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.01" name="screw_speed" value="<?= h($row["screw_speed"]) ?>" placeholder="Screw Speed">
            <span class="unit">%</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" name="bowl_rpm" value="<?= h($row["bowl_rpm"]) ?>" placeholder="Bowl RPM">
            <span class="unit">RPM</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" name="screw_rpm" value="<?= h($row["screw_rpm"]) ?>" placeholder="Screw RPM">
            <span class="unit">RPM</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" name="impeller" value="<?= h($row["impeller"]) ?>" placeholder="Impeller">
            <span class="unit">%</span>
        </div>

        <div class="input-unit-wrap long">
            <input type="number" step="0.01" name="feed_rate" value="<?= h($row["feed_rate"]) ?>" placeholder="Feed Rate">
            <span class="unit">m3/hr</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.1" name="torque" value="<?= h($row["torque"]) ?>" placeholder="Torque">
            <span class="unit">%</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.1" name="temp" value="<?= h($row["temp"]) ?>" placeholder="Temp">
            <span class="unit">°C</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.001" name="pressure" value="<?= h($row["pressure"]) ?>" placeholder="Pressure">
            <span class="unit">BAR</span>
        </div>

        <textarea name="comments" placeholder="Comments"><?= h($row["comments"]) ?></textarea>

        <button type="submit">Update</button>
    </form>
</div>

</body>
</html>