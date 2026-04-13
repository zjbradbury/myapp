<?php
require_once "config.php";

requireRole(["admin", "operator"]);

$currentUser = $_SESSION['username'] ?? 'unknown';

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

$operatorOptions = getConfigNames($pdo, 'config_operators', true);
$sampleLocationOptions = getConfigNames($pdo, 'config_sample_location');

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $stmt = $pdo->prepare("
        INSERT INTO sample_logs
        (source_file, log_date, log_time, sample_location, nozzle, flow, mercury, solids, water, wax, operator, comments)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        "web_entry_" . $currentUser,
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
        nullIfBlank($_POST["comments"] ?? null)
    ]);

    header("Location: sample_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Sample Record</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require_once "nav.php"; ?>

    <div class="container">
        <h2>Add Sample Record</h2>

        <form method="post">
            <input type="date" name="log_date" id="log_date" required>
            <input type="time" name="log_time" id="log_time" step="1" required>

            <input type="text" name="sample_location" list="sample_location_list" placeholder="Sample Location" required>
            <datalist id="sample_location_list">
                <?= renderDatalistOptions($sampleLocationOptions) ?>
            </datalist>

            <div class="input-unit-wrap">
                <input type="text" name="nozzle" placeholder="Nozzle">
                <span class="unit">N</span>
            </div>

            <div class="input-unit-wrap long">
                <input type="number" step="0.01" name="flow" placeholder="Flow">
                <span class="unit">m3/hr</span>
            </div>

            <div class="input-unit-wrap">
                <input type="number" step="0.001" name="mercury" placeholder="Mercury">
                <span class="unit">ppm</span>
            </div>

            <div class="input-unit-wrap">
                <input type="number" step="0.01" name="solids" placeholder="Solids">
                <span class="unit">%</span>
            </div>

            <div class="input-unit-wrap">
                <input type="number" step="0.01" name="water" placeholder="Water">
                <span class="unit">%</span>
            </div>

            <div class="input-unit-wrap">
                <input type="number" step="0.01" name="wax" placeholder="Wax">
                <span class="unit">%</span>
            </div>

            <input type="text" name="operator" list="operator_list" placeholder="Operator">
            <datalist id="operator_list">
                <?= renderDatalistOptions($operatorOptions) ?>
            </datalist>

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