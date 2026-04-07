<?php
require_once "config.php";
requireRole(["admin", "operator"]);

$currentUser = $_SESSION['username'] ?? 'unknown';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $pdo->prepare("
        INSERT INTO solid_waste_logs
        (source_file, log_date, log_time, amount, comments)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        "web_entry_" . $currentUser,
        nullIfBlank($_POST["log_date"] ?? null),
        nullIfBlank($_POST["log_time"] ?? null),
        nullIfBlank($_POST["amount"] ?? null),
        nullIfBlank($_POST["comments"] ?? null)
    ]);

    header("Location: solid_waste_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Solid Waste Record</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="container">
    <h2>Add Solid Waste Record</h2>

    <form method="post">
        <input type="date" name="log_date" id="log_date" required>
        <input type="time" name="log_time" id="log_time" step="1" required>

        <div class="input-unit-wrap">
            <input type="number" step="0.01" name="amount" placeholder="Amount" required>
            <span class="unit">KG</span>
        </div>

        <textarea name="comments" placeholder="Comments"></textarea>

        <button type="submit">Save</button>
    </form>
</div>

<script>
(function () {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');

    document.getElementById('log_date').value = `${year}-${month}-${day}`;
    document.getElementById('log_time').value = `${hours}:${minutes}:${seconds}`;
})();
</script>

</body>
</html>