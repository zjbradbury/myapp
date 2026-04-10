<?php
require_once "config.php";

requireRole(["admin", "operator"]);

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

$stmt = $pdo->prepare("SELECT * FROM gas_test_logs WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("Record not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $stmt = $pdo->prepare("
        UPDATE gas_test_logs
        SET
            source_file = ?,
            log_date = ?,
            log_time = ?,
            device = ?,
            operator = ?,
            location = ?,
            area_details = ?,
            mercury = ?,
            benzene = ?,
            lel = ?,
            h2s = ?,
            o2 = ?,
            product_details = ?,
            action_taken = ?
        WHERE id = ?
    ");

    $stmt->execute([
        "web_entry_" . ($_SESSION['username'] ?? 'unknown'),
        nullIfBlank($_POST["log_date"] ?? null),
        nullIfBlank($_POST["log_time"] ?? null),
        nullIfBlank($_POST["device"] ?? null),
        nullIfBlank($_POST["operator"] ?? null),
        nullIfBlank($_POST["location"] ?? null),
        nullIfBlank($_POST["area_details"] ?? null),
        nullIfBlank($_POST["mercury"] ?? null),
        nullIfBlank($_POST["benzene"] ?? null),
        nullIfBlank($_POST["lel"] ?? null),
        nullIfBlank($_POST["h2s"] ?? null),
        nullIfBlank($_POST["o2"] ?? null),
        nullIfBlank($_POST["product_details"] ?? null),
        nullIfBlank($_POST["action_taken"] ?? null),
        $id
    ]);

    header("Location: gas_test_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Gas Test Record</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="container">
    <h2>Edit Gas Test Record</h2>

    <form method="post">
        <input type="date" name="log_date" value="<?= h($row["log_date"]) ?>" required>
        <input type="time" name="log_time" step="1" value="<?= h($row["log_time"]) ?>" required>

        <input type="text" name="device" value="<?= h($row["device"]) ?>" required>
        <input type="text" name="operator" value="<?= h($row["operator"]) ?>" required>
        <input type="text" name="location" value="<?= h($row["location"]) ?>" required>
        <input type="text" name="area_details" value="<?= h($row["area_details"]) ?>">

        <input type="number" step="0.001" name="mercury" value="<?= h($row["mercury"]) ?>">
        <input type="number" step="0.001" name="benzene" value="<?= h($row["benzene"]) ?>">
        <input type="number" step="0.01" name="lel" value="<?= h($row["lel"]) ?>">
        <input type="number" step="0.01" name="h2s" value="<?= h($row["h2s"]) ?>">
        <input type="number" step="0.01" name="o2" value="<?= h($row["o2"]) ?>">

        <input type="text" name="product_details" value="<?= h($row["product_details"]) ?>">

        <textarea name="action_taken"><?= h($row["action_taken"]) ?></textarea>

        <button type="submit">Update</button>
    </form>
</div>

</body>
</html>