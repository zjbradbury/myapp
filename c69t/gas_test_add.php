<?php
require_once "config.php";

requireRole(["admin", "operator"]);

$currentUser = $_SESSION['username'] ?? 'unknown';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $stmt = $pdo->prepare("
        INSERT INTO gas_test_logs
        (
            source_file,
            log_date,
            log_time,
            device,
            operator,
            location,
            area_details,
            mercury,
            benzene,
            lel,
            h2s,
            o2,
            product_details,
            action_taken
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        "web_entry_" . $currentUser,
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
        nullIfBlank($_POST["action_taken"] ?? null)
    ]);

    header("Location: gas_test_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Gas Test Record</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="container">
    <h2>Add Gas Test Record</h2>

    <form method="post">
        <input type="date" name="log_date" id="log_date" required>
        <input type="time" name="log_time" id="log_time" step="1" required>

        <input type="text" name="device" placeholder="Device" required>
        <input type="text" name="operator" placeholder="Operator" required>
        <input type="text" name="location" placeholder="Location" required>
        <input type="text" name="area_details" placeholder="Area Details">

        <div class="input-unit-wrap">
            <input type="number" step="0.001" name="mercury" placeholder="Mercury">
            <span class="unit">ppm</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.001" name="benzene" placeholder="Benzene">
            <span class="unit">ppm</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.01" name="lel" placeholder="LEL">
            <span class="unit">%</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.01" name="h2s" placeholder="H2S">
            <span class="unit">ppm</span>
        </div>

        <div class="input-unit-wrap">
            <input type="number" step="0.01" name="o2" placeholder="O2">
            <span class="unit">%</span>
        </div>

        <input type="text" name="product_details" placeholder="Product Details">

        <textarea name="action_taken" placeholder="Actions Taken"></textarea>

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