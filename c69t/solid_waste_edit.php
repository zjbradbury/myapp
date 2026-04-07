<?php
require_once "config.php";
requireRole(["admin", "operator"]);

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

$stmt = $pdo->prepare("SELECT * FROM solid_waste_logs WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("Record not found.");
}

$currentUser = $_SESSION['username'] ?? 'unknown';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $pdo->prepare("
        UPDATE solid_waste_logs
        SET source_file = ?, log_date = ?, log_time = ?, amount = ?, comments = ?
        WHERE id = ?
    ");

    $stmt->execute([
        "web_entry_" . $currentUser,
        nullIfBlank($_POST["log_date"] ?? null),
        nullIfBlank($_POST["log_time"] ?? null),
        nullIfBlank($_POST["amount"] ?? null),
        nullIfBlank($_POST["comments"] ?? null),
        $id
    ]);

    header("Location: solid_waste_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Solid Waste Record</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="container">
    <h2>Edit Solid Waste Record</h2>

    <form method="post">
        <input type="date" name="log_date" value="<?= h($row["log_date"]) ?>" required>
        <input type="time" name="log_time" step="1" value="<?= h($row["log_time"]) ?>" required>

        <div class="input-unit-wrap">
            <input type="number" step="0.01" name="amount" value="<?= h($row["amount"]) ?>" placeholder="Amount" required>
            <span class="unit">KG</span>
        </div>

        <textarea name="comments" placeholder="Comments"><?= h($row["comments"]) ?></textarea>

        <button type="submit">Update</button>
    </form>
</div>

</body>
</html>