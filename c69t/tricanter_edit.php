<?php
require_once "config.php";
requireRole(["admin", "operator"]);

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
        trim($_POST["comments"] ?? "") ?: null,
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
            <input type="number" step="0.000001" name="bowl_speed" value="<?= h($row["bowl_speed"]) ?>"
                placeholder="Bowl Speed">
            <input type="number" step="0.000001" name="screw_speed" value="<?= h($row["screw_speed"]) ?>"
                placeholder="Screw Speed">
            <input type="number" step="0.000001" name="bowl_rpm" value="<?= h($row["bowl_rpm"]) ?>"
                placeholder="Bowl RPM">
            <input type="number" step="0.000001" name="screw_rpm" value="<?= h($row["screw_rpm"]) ?>"
                placeholder="Screw RPM">
            <input type="text" name="impeller" value="<?= h($row["impeller"]) ?>" placeholder="Impeller">
            <input type="number" step="0.000001" name="feed_rate" value="<?= h($row["feed_rate"]) ?>"
                placeholder="Feed Rate">
            <input type="number" step="0.000001" name="torque" value="<?= h($row["torque"]) ?>" placeholder="Torque">
            <input type="number" step="0.000001" name="temp" value="<?= h($row["temp"]) ?>" placeholder="Temp">
            <input type="number" step="0.000001" name="pressure" value="<?= h($row["pressure"]) ?>"
                placeholder="Pressure">
            <textarea name="comments" placeholder="Comments"><?= h($row["comments"]) ?></textarea>
            <button type="submit">Update</button>
        </form>
    </div>
</body>

</html>