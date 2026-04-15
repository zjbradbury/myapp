<?php
require_once "config.php";
requireRole(["admin", "operator"]);

$id = (int)($_GET["id"] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM project_flow_logs WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("Record not found");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $stmt = $pdo->prepare("
        UPDATE project_flow_logs
        SET
            source_file = ?,
            log_date = ?,
            log_time = ?,
            total_recovered_oil = ?,
            total_recovered_water = ?,
            total_solid_waste = ?,
            total_tricanter = ?,
            total_nozzle = ?,
            comments = ?
        WHERE id = ?
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
        $_POST["comments"] ?: null,
        $id
    ]);

    header("Location: project_flow_list.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Project Flow</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php require_once "nav.php"; ?>

<div class="container">

    <h2>Edit Project Flow</h2>

    <form method="post" class="form-card">

        <label>Date</label>
        <input type="date" name="log_date" value="<?= h($row['log_date']) ?>">

        <label>Time</label>
        <input type="time" name="log_time" value="<?= substr($row['log_time'],0,5) ?>">

        <label>Recovered Oil</label>
        <input type="number" step="0.0001" name="oil" value="<?= $row['total_recovered_oil'] ?>">

        <label>Recovered Water</label>
        <input type="number" step="0.0001" name="water" value="<?= $row['total_recovered_water'] ?>">

        <label>Solid Waste</label>
        <input type="number" step="0.0001" name="solid" value="<?= $row['total_solid_waste'] ?>">

        <label>Tricanter</label>
        <input type="number" step="0.0001" name="tricanter" value="<?= $row['total_tricanter'] ?>">

        <label>Nozzle</label>
        <input type="number" step="0.0001" name="nozzle" value="<?= $row['total_nozzle'] ?>">

        <label>Comments</label>
        <textarea name="comments"><?= h($row['comments']) ?></textarea>

        <button class="btn">Update</button>

    </form>

</div>
</body>
</html>