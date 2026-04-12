<?php
require_once "config.php";
requireRole(["admin", "operator"]);

$currentUser = $_SESSION['username'] ?? 'unknown';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getGasTestDevices(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT *
        FROM config_gas_test_devices
        ORDER BY name ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getActiveOperators(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT name
        FROM config_operators
        WHERE active = 1
        ORDER BY name ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$deviceRows = getGasTestDevices($pdo);
$operatorOptions = getActiveOperators($pdo);

$deviceConfigMap = [];
foreach ($deviceRows as $d) {
    $deviceConfigMap[$d["name"]] = [
        "allow_mercury" => (int)$d["allow_mercury"],
        "allow_benzene" => (int)$d["allow_benzene"],
        "allow_lel" => (int)$d["allow_lel"],
        "allow_h2s" => (int)$d["allow_h2s"],
        "allow_o2" => (int)$d["allow_o2"],
        "allow_product_details" => (int)$d["allow_product_details"],
        "allow_action_taken" => (int)$d["allow_action_taken"],
    ];
}

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
    <style>
        .field-locked {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="container">
    <h2>Add Gas Test Record</h2>

    <form method="post">
        <input type="date" name="log_date" id="log_date" required>
        <input type="time" name="log_time" id="log_time" step="1" required>

        <input type="text" name="device" id="device" list="device_list" placeholder="Device" required>
        <datalist id="device_list">
            <?php foreach ($deviceRows as $row): ?>
                <option value="<?= h($row["name"]) ?>">
            <?php endforeach; ?>
        </datalist>

        <input type="text" name="operator" list="operator_list" placeholder="Operator" required>
        <datalist id="operator_list">
            <?php foreach ($operatorOptions as $option): ?>
                <option value="<?= h($option) ?>">
            <?php endforeach; ?>
        </datalist>

        <input type="text" name="location" placeholder="Location" required>
        <input type="text" name="area_details" placeholder="Area Details">

        <div class="input-unit-wrap device-field" data-field="allow_mercury">
            <input type="number" step="0.001" name="mercury" placeholder="Mercury">
            <span class="unit">ppm</span>
        </div>

        <div class="input-unit-wrap device-field" data-field="allow_benzene">
            <input type="number" step="0.001" name="benzene" placeholder="Benzene">
            <span class="unit">ppm</span>
        </div>

        <div class="input-unit-wrap device-field" data-field="allow_lel">
            <input type="number" step="0.01" name="lel" placeholder="LEL">
            <span class="unit">%</span>
        </div>

        <div class="input-unit-wrap device-field" data-field="allow_h2s">
            <input type="number" step="0.01" name="h2s" placeholder="H2S">
            <span class="unit">ppm</span>
        </div>

        <div class="input-unit-wrap device-field" data-field="allow_o2">
            <input type="number" step="0.01" name="o2" placeholder="O2">
            <span class="unit">%</span>
        </div>

        <input type="text" name="product_details" class="device-field" data-field="allow_product_details" placeholder="Product Details">
        <textarea name="action_taken" class="device-field" data-field="allow_action_taken" placeholder="Actions Taken"></textarea>

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

(function () {
    const deviceInput = document.getElementById('device');
    const deviceConfigMap = <?= json_encode($deviceConfigMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const fields = document.querySelectorAll('.device-field');

    function clearElementValues(el) {
        if (el.matches('input, textarea')) {
            el.value = '';
        }
        el.querySelectorAll('input, textarea').forEach(function (child) {
            child.value = '';
        });
    }

    function setElementState(el, enabled) {
        if (enabled) {
            el.classList.remove('field-locked');

            if (el.matches('input, textarea, select, button')) {
                el.disabled = false;
            }

            el.querySelectorAll('input, textarea, select, button').forEach(function (child) {
                child.disabled = false;
            });
        } else {
            el.classList.add('field-locked');
            clearElementValues(el);

            if (el.matches('input, textarea, select, button')) {
                el.disabled = true;
            }

            el.querySelectorAll('input, textarea, select, button').forEach(function (child) {
                child.disabled = true;
            });
        }
    }

    function updateFields() {
        const selectedDevice = deviceInput.value.trim();
        const config = deviceConfigMap[selectedDevice] || null;

        fields.forEach(function (el) {
            const key = el.getAttribute('data-field');
            const enabled = config && Number(config[key]) === 1;
            setElementState(el, enabled);
        });
    }

    deviceInput.addEventListener('input', updateFields);
    deviceInput.addEventListener('change', updateFields);
    updateFields();
})();
</script>

</body>
</html>