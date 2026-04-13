<?php
require_once "config.php";

requireRole(["admin", "operator"]);

if (!function_exists('getConfigNames')) {
    function getConfigNames(PDO $pdo, string $tableName, bool $activeOnly = false): array
    {
        $allowedTables = [
            'config_operators',
            'config_sample_location',
        ];

        if (!in_array($tableName, $allowedTables, true)) {
            return [];
        }

        $sql = "SELECT name FROM `$tableName`";

        if ($activeOnly) {
            $sql .= " WHERE active = 1";
        }

        $sql .= " ORDER BY name ASC";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

if (!function_exists('renderDatalistOptions')) {
    function renderDatalistOptions(array $options): string
    {
        $html = '';

        foreach ($options as $option) {
            $value = trim((string)$option);
            if ($value === '') {
                continue;
            }

            $html .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
        }

        return $html;
    }
}

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

$stmt = $pdo->prepare("SELECT * FROM sample_logs WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("Record not found.");
}

$operatorOptions = getConfigNames($pdo, 'config_operators', true);
$sampleLocationOptions = getConfigNames($pdo, 'config_sample_location');

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $stmt = $pdo->prepare("
        UPDATE sample_logs
        SET source_file = ?, log_date = ?, log_time = ?, sample_location = ?, nozzle = ?, flow = ?, mercury = ?, solids = ?, water = ?, wax = ?, operator = ?, comments = ?
        WHERE id = ?
    ");

    $stmt->execute([
        "web_entry_" . ($_SESSION['username'] ?? 'unknown'),
        nullIfBlank($_POST["log_date"] ?? null),
        nullIfBlank($_POST["log_time"] ?? null),
        nullIfBlank($_POST["sample_location"] ?? null),
        nullIfBlank($_POST["nozzle"] ?? null),
        nullIfBlank($_POST["flow"] ?? null),
        nullIfBlank($_POST["mercury"] ?? null),
        nullIfBlank($_POST["solids"] ?? null),
        nullIfBlank($_POST["water"] ?? null),
        nullIfBlank($_POST["wax"] ?? null),
        nullIfBlank($_POST["operator"] ?? null),
        nullIfBlank($_POST["comments"] ?? null),
        $id
    ]);

    header("Location: sample_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Sample Record</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require_once "nav.php"; ?>

    <div class="container">
        <h2>Edit Sample Record</h2>

        <form method="post">
            <input type="date" name="log_date" value="<?= h($row["log_date"]) ?>" required>
            <input type="time" name="log_time" step="1" value="<?= h($row["log_time"]) ?>" required>

            <input
                type="text"
                name="sample_location"
                list="sample_location_list"
                value="<?= h($row["sample_location"]) ?>"
                placeholder="Sample Location"
                required
            >
            <datalist id="sample_location_list">
                <?= renderDatalistOptions($sampleLocationOptions) ?>
            </datalist>

            <div class="input-unit-wrap">
                <input type="text" name="nozzle" value="<?= h($row["nozzle"]) ?>" placeholder="Nozzle">
                <span class="unit">N</span>
            </div>

            <div class="input-unit-wrap long">
                <input type="number" step="0.01" name="flow" value="<?= h($row["flow"]) ?>" placeholder="Flow">
                <span class="unit">m3/hr</span>
            </div>

            <div class="input-unit-wrap">
                <input type="number" step="0.001" name="mercury" value="<?= h($row["mercury"]) ?>" placeholder="Mercury">
                <span class="unit">ppm</span>
            </div>

            <div class="input-unit-wrap">
                <input type="number" step="0.01" name="solids" value="<?= h($row["solids"]) ?>" placeholder="Solids">
                <span class="unit">%</span>
            </div>

            <div class="input-unit-wrap">
                <input type="number" step="0.01" name="water" value="<?= h($row["water"]) ?>" placeholder="Water">
                <span class="unit">%</span>
            </div>

            <div class="input-unit-wrap">
                <input type="number" step="0.01" name="wax" value="<?= h($row["wax"]) ?>" placeholder="Wax">
                <span class="unit">%</span>
            </div>

            <input
                type="text"
                name="operator"
                list="operator_list"
                value="<?= h($row["operator"]) ?>"
                placeholder="Operator"
            >
            <datalist id="operator_list">
                <?= renderDatalistOptions($operatorOptions) ?>
            </datalist>

            <textarea name="comments" placeholder="Comments"><?= h($row["comments"]) ?></textarea>

            <button type="submit">Update</button>
        </form>
    </div>
</body>
</html>