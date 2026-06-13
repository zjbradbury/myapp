<?php
require_once "config.php";
requireRole(["admin", "operator"]);

$currentUser = $_SESSION['username'] ?? 'unknown';

if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('nullIfBlank')) {
    function nullIfBlank($value) {
        if ($value === null) return null;
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}

function safe_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function config_names(PDO $pdo, string $table, bool $activeOnly = false): array
{
    $allowed = [
        'config_operators',
        'config_sample_location',
        'config_gas_test_location',
    ];

    if (!in_array($table, $allowed, true) || !safe_table_exists($pdo, $table)) {
        return [];
    }

    $sql = "SELECT name FROM `$table`";
    if ($activeOnly) {
        $sql .= " WHERE active = 1";
    }
    $sql .= " ORDER BY name ASC";

    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

function gas_device_rows(PDO $pdo): array
{
    if (!safe_table_exists($pdo, 'config_gas_test_devices')) {
        return [];
    }

    try {
        return $pdo->query("SELECT * FROM config_gas_test_devices ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function datalist_options(array $values): string
{
    $html = '';
    foreach ($values as $value) {
        $value = trim((string)$value);
        if ($value === '') continue;
        $html .= '<option value="' . h($value) . '"></option>';
    }
    return $html;
}

function yes_no_value($value): string
{
    if ($value === null || $value === '') return '';
    if (is_numeric($value)) return ((int)$value === 1) ? '1' : '0';

    $v = strtolower(trim((string)$value));
    if (in_array($v, ['true', 'yes', 'on', '1'], true)) return '1';
    if (in_array($v, ['false', 'no', 'off', '0'], true)) return '0';

    return '';
}

$schemas = [
    'tricanter' => [
        'label' => 'Tricanter',
        'table' => 'tricanter_logs',
        'list' => 'logs.php?table=tricanter',
        'fields' => [
            ['name' => 'log_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'log_time', 'label' => 'Time', 'type' => 'time', 'required' => true],
            ['name' => 'bowl_speed', 'label' => 'Bowl Speed', 'type' => 'number', 'step' => '0.01', 'unit' => '%'],
            ['name' => 'screw_speed', 'label' => 'Screw Speed', 'type' => 'number', 'step' => '0.01', 'unit' => '%'],
            ['name' => 'bowl_rpm', 'label' => 'Bowl RPM', 'type' => 'number', 'step' => '0.01', 'unit' => 'RPM'],
            ['name' => 'screw_rpm', 'label' => 'Screw RPM', 'type' => 'number', 'step' => '0.01', 'unit' => 'RPM'],
            ['name' => 'impeller', 'label' => 'Impeller', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'feed_rate', 'label' => 'Feed Rate', 'type' => 'number', 'step' => '0.001', 'unit' => 'M3/hr'],
            ['name' => 'torque', 'label' => 'Torque', 'type' => 'number', 'step' => '0.01', 'unit' => '%'],
            ['name' => 'temp', 'label' => 'Temp', 'type' => 'number', 'step' => '0.01', 'unit' => '°C'],
            ['name' => 'pressure', 'label' => 'Pressure', 'type' => 'number', 'step' => '0.001', 'unit' => 'BAR'],
            ['name' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
        ],
    ],
    'solid_waste' => [
        'label' => 'Solid Waste',
        'table' => 'solid_waste_logs',
        'list' => 'logs.php?table=solid_waste',
        'fields' => [
            ['name' => 'log_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'log_time', 'label' => 'Time', 'type' => 'time', 'required' => true],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'step' => '0.01', 'unit' => 'KG'],
            ['name' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
        ],
    ],
    'nozzle' => [
        'label' => 'Nozzle',
        'table' => 'nozzle_logs',
        'list' => 'logs.php?table=nozzle',
        'fields' => [
            ['name' => 'log_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'log_time', 'label' => 'Time', 'type' => 'time', 'required' => true],
            ['name' => 'nozzle', 'label' => 'Nozzle', 'type' => 'text'],
            ['name' => 'flow', 'label' => 'Flow', 'type' => 'number', 'step' => '0.001', 'unit' => 'M3/hr'],
            ['name' => 'pressure', 'label' => 'Pressure', 'type' => 'number', 'step' => '0.001', 'unit' => 'BAR'],
            ['name' => 'min_deg', 'label' => 'Min Deg', 'type' => 'number', 'step' => '0.01', 'unit' => '°'],
            ['name' => 'max_deg', 'label' => 'Max Deg', 'type' => 'number', 'step' => '0.01', 'unit' => '°'],
            ['name' => 'rpm', 'label' => 'RPM', 'type' => 'number', 'step' => '0.01', 'unit' => 'RPM'],
            ['name' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
        ],
    ],
    'sample' => [
        'label' => 'Sample',
        'table' => 'sample_logs',
        'list' => 'logs.php?table=sample',
        'fields' => [
            ['name' => 'log_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'log_time', 'label' => 'Time', 'type' => 'time', 'required' => true],
            ['name' => 'sample_location', 'label' => 'Sample Location', 'type' => 'text', 'datalist' => 'sample_locations'],
            ['name' => 'nozzle', 'label' => 'Nozzle', 'type' => 'text'],
            ['name' => 'flow', 'label' => 'Flow', 'type' => 'number', 'step' => '0.001', 'unit' => 'M3/hr'],
            ['name' => 'mercury', 'label' => 'Mercury', 'type' => 'number', 'step' => '0.001', 'unit' => '%'],
            ['name' => 'solids', 'label' => 'Solids', 'type' => 'number', 'step' => '0.001', 'unit' => '%'],
            ['name' => 'water', 'label' => 'Water', 'type' => 'number', 'step' => '0.001', 'unit' => '%'],
            ['name' => 'wax', 'label' => 'Wax', 'type' => 'number', 'step' => '0.001', 'unit' => '%'],
            ['name' => 'operator', 'label' => 'Operator', 'type' => 'text', 'datalist' => 'operators'],
            ['name' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
        ],
    ],
    'gas_test' => [
        'label' => 'Gas Test',
        'table' => 'gas_test_logs',
        'list' => 'logs.php?table=gas_test',
        'fields' => [
            ['name' => 'log_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'log_time', 'label' => 'Time', 'type' => 'time', 'required' => true],
            ['name' => 'device', 'label' => 'Device', 'type' => 'select_or_text', 'datalist' => 'gas_devices'],
            ['name' => 'operator', 'label' => 'Operator', 'type' => 'text', 'datalist' => 'operators'],
            ['name' => 'location', 'label' => 'Location', 'type' => 'text', 'datalist' => 'gas_locations'],
            ['name' => 'area_details', 'label' => 'Area Details', 'type' => 'text'],
            ['name' => 'mercury', 'label' => 'Mercury', 'type' => 'number', 'step' => '0.001', 'unit' => 'µg/m³', 'gasFlag' => 'allow_mercury'],
            ['name' => 'benzene', 'label' => 'Benzene', 'type' => 'number', 'step' => '0.001', 'unit' => 'ppm', 'gasFlag' => 'allow_benzene'],
            ['name' => 'lel', 'label' => 'LEL', 'type' => 'number', 'step' => '0.001', 'unit' => '%', 'gasFlag' => 'allow_lel'],
            ['name' => 'h2s', 'label' => 'H2S', 'type' => 'number', 'step' => '0.001', 'unit' => 'ppm', 'gasFlag' => 'allow_h2s'],
            ['name' => 'o2', 'label' => 'O2', 'type' => 'number', 'step' => '0.001', 'unit' => '%', 'gasFlag' => 'allow_o2'],
            ['name' => 'product_details', 'label' => 'Product Details', 'type' => 'textarea', 'gasFlag' => 'allow_product_details'],
            ['name' => 'action_taken', 'label' => 'Action Taken', 'type' => 'textarea', 'gasFlag' => 'allow_action_taken'],
        ],
    ],
    'project_flow' => [
        'label' => 'Project Flow',
        'table' => 'project_flow_logs',
        'list' => 'logs.php?table=project_flow',
        'fields' => [
            ['name' => 'log_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'log_time', 'label' => 'Time', 'type' => 'time', 'required' => true],
            ['name' => 'total_recovered_oil', 'label' => 'Total Recovered Oil', 'type' => 'number', 'step' => '0.0001', 'unit' => 'm³'],
            ['name' => 'total_recovered_water', 'label' => 'Total Recovered Water', 'type' => 'number', 'step' => '0.0001', 'unit' => 'm³'],
            ['name' => 'total_solid_waste', 'label' => 'Total Solid Waste', 'type' => 'number', 'step' => '0.0001', 'unit' => 'KG'],
            ['name' => 'total_tricanter', 'label' => 'Total Tricanter', 'type' => 'number', 'step' => '0.0001', 'unit' => 'm³'],
            ['name' => 'total_nozzle', 'label' => 'Total Nozzle', 'type' => 'number', 'step' => '0.0001', 'unit' => 'm³'],
            ['name' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
        ],
    ],
    'pump_values' => [
        'label' => 'Pump Values',
        'table' => 'pump_values_logs',
        'list' => 'logs.php?table=pump_values',
        'fields' => [
            ['name' => 'log_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'log_time', 'label' => 'Time', 'type' => 'time', 'required' => true],
            ['name' => 'suction_pump_1_status', 'label' => 'SP1 Status', 'type' => 'number', 'step' => '1'],
            ['name' => 'suction_pump_2_status', 'label' => 'SP2 Status', 'type' => 'number', 'step' => '1'],
            ['name' => 'suction_pump_2_speed_out', 'label' => 'SP2 Speed Out', 'type' => 'number', 'step' => '0.001'],
            ['name' => 'suction_pump_2_feedback', 'label' => 'SP2 Feedback', 'type' => 'number', 'step' => '0.001'],
            ['name' => 'suction_pump_2_inlet_pressure', 'label' => 'SP2 Inlet Pressure', 'type' => 'number', 'step' => '0.001', 'unit' => 'BAR'],
            ['name' => 'suction_pump_2_outlet_pressure', 'label' => 'SP2 Outlet Pressure', 'type' => 'number', 'step' => '0.001', 'unit' => 'BAR'],
            ['name' => 'feed_pump_status', 'label' => 'Feed Pump Status', 'type' => 'number', 'step' => '1'],
            ['name' => 'feed_pump_speed_out', 'label' => 'Feed Pump Speed Out', 'type' => 'number', 'step' => '0.001'],
            ['name' => 'feed_pump_feedback', 'label' => 'Feed Pump Feedback', 'type' => 'number', 'step' => '0.001'],
            ['name' => 'feed_pump_inlet_pressure', 'label' => 'Feed Pump Inlet Pressure', 'type' => 'number', 'step' => '0.001', 'unit' => 'BAR'],
            ['name' => 'feed_pump_outlet_pressure', 'label' => 'Feed Pump Outlet Pressure', 'type' => 'number', 'step' => '0.001', 'unit' => 'BAR'],
            ['name' => 'booster_pump_status', 'label' => 'Booster Pump Status', 'type' => 'number', 'step' => '1'],
            ['name' => 'booster_pump_speed_out', 'label' => 'Booster Pump Speed Out', 'type' => 'number', 'step' => '0.001'],
            ['name' => 'booster_pump_feedback', 'label' => 'Booster Pump Feedback', 'type' => 'number', 'step' => '0.001'],
            ['name' => 'booster_pump_inlet_pressure', 'label' => 'Booster Pump Inlet Pressure', 'type' => 'number', 'step' => '0.001', 'unit' => 'BAR'],
            ['name' => 'booster_pump_outlet_pressure', 'label' => 'Booster Pump Outlet Pressure', 'type' => 'number', 'step' => '0.001', 'unit' => 'BAR'],
            ['name' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
        ],
    ],
    'nitrogen' => [
        'label' => 'Nitrogen',
        'table' => 'nitrogen_logs',
        'list' => 'logs.php?table=nitrogen',
        'fields' => [
            ['name' => 'log_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'log_time', 'label' => 'Time', 'type' => 'time', 'required' => true],
            ['name' => 'nitrogen_active', 'label' => 'Nitrogen Active', 'type' => 'bool'],
            ['name' => 'trip_status', 'label' => 'Trip Status', 'type' => 'bool'],
            ['name' => 'outlet_flow', 'label' => 'Outlet Flow', 'type' => 'number', 'step' => '0.001', 'unit' => 'M3/hr'],
            ['name' => 'outlet_purity', 'label' => 'Outlet Purity', 'type' => 'number', 'step' => '0.001', 'unit' => '% O2'],
            ['name' => 'inlet_pressure', 'label' => 'Inlet Pressure', 'type' => 'number', 'step' => '0.001', 'unit' => 'BAR'],
            ['name' => 'outlet_pressure', 'label' => 'Outlet Pressure', 'type' => 'number', 'step' => '0.001', 'unit' => 'BAR'],
            ['name' => 'pre_heat_temp', 'label' => 'Pre Heat Temp', 'type' => 'number', 'step' => '0.001', 'unit' => '°C'],
            ['name' => 'post_heat_temp', 'label' => 'Post Heat Temp', 'type' => 'number', 'step' => '0.001', 'unit' => '°C'],
            ['name' => 'interior_o2', 'label' => 'Interior O2', 'type' => 'number', 'step' => '0.001', 'unit' => '%'],
            ['name' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
        ],
    ],
];

$tableKey = strtolower(trim($_GET['table'] ?? $_POST['table'] ?? 'tricanter'));
$action = strtolower(trim($_GET['action'] ?? $_POST['action'] ?? 'add'));
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if (!isset($schemas[$tableKey])) {
    http_response_code(404);
    die("Unknown log table.");
}

if (!in_array($action, ['add', 'edit', 'delete'], true)) {
    $action = 'add';
}

$schema = $schemas[$tableKey];
$dbTable = $schema['table'];
$listUrl = $schema['list'];

if (!safe_table_exists($pdo, $dbTable)) {
    die("Database table not found: " . h($dbTable));
}

$fieldNames = array_map(fn($f) => $f['name'], $schema['fields']);
$operators = config_names($pdo, 'config_operators', true);
$sampleLocations = config_names($pdo, 'config_sample_location');
$gasLocations = config_names($pdo, 'config_gas_test_location');
$gasDevices = gas_device_rows($pdo);
$gasDeviceNames = array_column($gasDevices, 'name');

$gasDeviceConfig = [];
foreach ($gasDevices as $device) {
    $name = (string)($device['name'] ?? '');
    if ($name === '') continue;
    $gasDeviceConfig[$name] = [
        'allow_mercury' => (int)($device['allow_mercury'] ?? 1),
        'allow_benzene' => (int)($device['allow_benzene'] ?? 1),
        'allow_lel' => (int)($device['allow_lel'] ?? 1),
        'allow_h2s' => (int)($device['allow_h2s'] ?? 1),
        'allow_o2' => (int)($device['allow_o2'] ?? 1),
        'allow_product_details' => (int)($device['allow_product_details'] ?? 1),
        'allow_action_taken' => (int)($device['allow_action_taken'] ?? 1),
    ];
}

$row = [];
if (in_array($action, ['edit', 'delete'], true)) {
    if ($id <= 0) {
        die("Missing record ID.");
    }

    $stmt = $pdo->prepare("SELECT * FROM `$dbTable` WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        die("Record not found.");
    }
}

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $row['log_date'] = date('Y-m-d');
    $row['log_time'] = date('H:i');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'delete') {
        if (($_POST['confirm_delete'] ?? '') === 'yes') {
            $stmt = $pdo->prepare("DELETE FROM `$dbTable` WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: " . $listUrl . "&msg=" . urlencode($schema['label'] . " record deleted"));
            exit;
        }

        header("Location: " . $listUrl);
        exit;
    }

    $data = [];
    foreach ($schema['fields'] as $field) {
        $name = $field['name'];
        if (($field['type'] ?? 'text') === 'bool') {
            $data[$name] = ($_POST[$name] ?? '') === '' ? null : (int)$_POST[$name];
        } else {
            $data[$name] = nullIfBlank($_POST[$name] ?? null);
        }
    }

    $data['source_file'] = "web_entry_" . $currentUser;

    if ($action === 'add') {
        $columns = array_keys($data);
        $sql = "INSERT INTO `$dbTable` (`" . implode("`, `", $columns) . "`) VALUES (:" . implode(", :", $columns) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        header("Location: " . $listUrl . "&msg=" . urlencode($schema['label'] . " record added"));
        exit;
    }

    if ($action === 'edit') {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "`$column` = :$column";
        }

        $data['id'] = $id;

        $sql = "UPDATE `$dbTable` SET " . implode(", ", $sets) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        header("Location: " . $listUrl . "&msg=" . urlencode($schema['label'] . " record updated"));
        exit;
    }
}

function field_value(array $row, string $name): string
{
    $value = $row[$name] ?? '';
    if ($value === null) return '';

    if ($name === 'log_time' && preg_match('/^\d{2}:\d{2}:\d{2}$/', (string)$value)) {
        return substr((string)$value, 0, 5);
    }

    return (string)$value;
}

function render_field(array $field, array $row, array $lists): string
{
    $name = $field['name'];
    $label = $field['label'];
    $type = $field['type'] ?? 'text';
    $required = !empty($field['required']) ? 'required' : '';
    $unit = trim((string)($field['unit'] ?? ''));
    $value = field_value($row, $name);
    $gasFlag = $field['gasFlag'] ?? '';
    $gasClass = $gasFlag !== '' ? ' gas-controlled' : '';
    $gasAttr = $gasFlag !== '' ? ' data-gas-flag="' . h($gasFlag) . '"' : '';

    $datalistId = '';
    if (!empty($field['datalist'])) {
        $datalistId = 'list_' . preg_replace('/[^a-z0-9_]/i', '_', $field['datalist']);
    }

    ob_start();
    ?>
    <label class="form-field<?= h($gasClass) ?>"<?= $gasAttr ?>>
        <span class="field-label"><?= h($label) ?></span>
        <?php if ($type === 'textarea'): ?>
            <textarea name="<?= h($name) ?>" <?= $required ?>><?= h($value) ?></textarea>
        <?php elseif ($type === 'bool'): ?>
            <?php $boolValue = yes_no_value($value); ?>
            <select name="<?= h($name) ?>" <?= $required ?>>
                <option value="" <?= $boolValue === '' ? 'selected' : '' ?>>-</option>
                <option value="1" <?= $boolValue === '1' ? 'selected' : '' ?>>ON / TRUE</option>
                <option value="0" <?= $boolValue === '0' ? 'selected' : '' ?>>OFF / FALSE</option>
            </select>
        <?php else: ?>
            <div class="<?= $unit !== '' ? 'unit-wrap' : '' ?>">
                <input
                    type="<?= h($type === 'select_or_text' ? 'text' : $type) ?>"
                    name="<?= h($name) ?>"
                    value="<?= h($value) ?>"
                    <?= $required ?>
                    <?= isset($field['step']) ? 'step="' . h($field['step']) . '"' : '' ?>
                    <?= $datalistId !== '' ? 'list="' . h($datalistId) . '"' : '' ?>>
                <?php if ($unit !== ''): ?><span class="unit"><?= h($unit) ?></span><?php endif; ?>
            </div>
        <?php endif; ?>
    </label>
    <?php
    return ob_get_clean();
}

$actionTitle = [
    'add' => 'Add',
    'edit' => 'Edit',
    'delete' => 'Delete',
][$action];

$pageTitle = $actionTitle . ' ' . $schema['label'] . ' Record';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= h($pageTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <style>
        :root{
            --bg-1:#08131f;
            --bg-2:#0e2235;
            --card:#10273c;
            --card-2:#122c44;
            --line:#214968;
            --line-soft:rgba(138,188,230,.14);
            --text:#e6f2ff;
            --muted:#9cc1de;
            --glow:0 10px 35px rgba(0,0,0,.28);
            --radius:16px;
        }

        body{
            padding-top:70px;
            background:
                radial-gradient(circle at top left, rgba(0,255,255,.06), transparent 22%),
                radial-gradient(circle at top right, rgba(0,135,255,.08), transparent 24%),
                linear-gradient(180deg, var(--bg-1), #091726 40%, #0a1828 100%);
            color:var(--text);
        }

        .record-shell{
            max-width:1280px;
            margin:0 auto;
        }

        .record-card{
            background:linear-gradient(180deg, rgba(18,44,68,.94), rgba(14,34,53,.96));
            border:1px solid var(--line-soft);
            border-radius:var(--radius);
            box-shadow:var(--glow);
            padding:16px;
        }

        .record-head{
            display:flex;
            justify-content:space-between;
            gap:16px;
            align-items:flex-start;
            margin-bottom:16px;
            flex-wrap:wrap;
        }

        .section-kicker{
            font-size:11px;
            letter-spacing:1.1px;
            text-transform:uppercase;
            color:#8abce6;
            margin-bottom:4px;
        }

        .record-head h1{
            margin:0;
            text-align:left;
            font-size:28px;
            line-height:1.1;
        }

        .record-sub{
            color:var(--muted);
            font-size:13px;
            margin-top:6px;
        }

        .record-actions{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        }

        .btn{
            border-radius:10px;
            background:linear-gradient(180deg, #1f4f74, #173d5b);
            border:1px solid rgba(255,255,255,.06);
            transition:transform .18s ease, background .18s ease, box-shadow .18s ease;
        }

        .btn:hover{
            background:linear-gradient(180deg, #255f8c, #1d4a6d);
            transform:translateY(-1px);
            box-shadow:0 6px 18px rgba(0,0,0,.18);
        }

        .btn.danger{
            background:linear-gradient(180deg, #b64242, #832f2f);
        }

        .record-tabs{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
            margin-bottom:16px;
        }

        .record-tab{
            color:#dcecff;
            text-decoration:none;
            padding:8px 11px;
            border-radius:999px;
            border:1px solid rgba(255,255,255,.08);
            background:rgba(255,255,255,.045);
            font-size:12px;
            font-weight:700;
        }

        .record-tab.active{
            background:rgba(0,229,255,.13);
            border-color:rgba(0,229,255,.35);
            color:#bff7ff;
        }

        .record-form{
            display:grid;
            grid-template-columns:repeat(3, minmax(0, 1fr));
            gap:12px;
        }

        .form-field{
            display:block;
            background:linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,.03));
            border:1px solid rgba(255,255,255,.06);
            border-radius:12px;
            padding:10px;
            min-width:0;
        }

        .field-label{
            display:block;
            color:var(--muted);
            text-transform:uppercase;
            letter-spacing:.7px;
            font-size:10px;
            margin-bottom:7px;
        }

        input,
        textarea,
        select{
            width:100%;
            margin:0;
            background:#0a1a29;
            border:1px solid #2a5377;
            border-radius:10px;
            color:#fff;
            padding:9px 10px;
            min-height:40px;
        }

        textarea{
            min-height:96px;
        }

        .form-field:has(textarea){
            grid-column:span 3;
        }

        .unit-wrap{
            position:relative;
        }

        .unit-wrap input{
            padding-right:82px;
        }

        .unit{
            position:absolute;
            right:12px;
            top:50%;
            transform:translateY(-50%);
            color:#92acc3;
            font-size:12px;
            pointer-events:none;
        }

        .form-footer{
            display:flex;
            justify-content:flex-end;
            gap:10px;
            margin-top:16px;
            flex-wrap:wrap;
        }

        .delete-card{
            background:rgba(182,66,66,.11);
            border:1px solid rgba(255,120,120,.22);
            border-radius:14px;
            padding:14px;
            margin-top:12px;
        }

        .delete-details{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
            gap:10px;
            margin-top:12px;
        }

        .delete-detail{
            background:rgba(255,255,255,.045);
            border:1px solid rgba(255,255,255,.06);
            border-radius:10px;
            padding:10px;
        }

        .delete-detail small{
            display:block;
            color:var(--muted);
            text-transform:uppercase;
            letter-spacing:.7px;
            font-size:10px;
            margin-bottom:4px;
        }

        .gas-disabled{
            opacity:.38;
        }

        .gas-disabled input,
        .gas-disabled textarea{
            pointer-events:none;
            background:#08131f;
        }

        @media (max-width:1000px){
            .record-form{
                grid-template-columns:repeat(2, minmax(0, 1fr));
            }

            .form-field:has(textarea){
                grid-column:span 2;
            }
        }

        @media (max-width:700px){
            .record-form{
                grid-template-columns:1fr;
            }

            .form-field:has(textarea){
                grid-column:span 1;
            }

            .record-head h1{
                font-size:23px;
            }
        }
    </style>
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="record-shell">
    <div class="record-card">
        <div class="record-head">
            <div>
                <div class="section-kicker">log record manager</div>
                <h1><?= h($pageTitle) ?></h1>
                <div class="record-sub">
                    <?= h($schema['label']) ?> data entry, editing, and delete confirmation in one page.
                </div>
            </div>

            <div class="record-actions">
                <a class="btn" href="<?= h($listUrl) ?>">Back to Logs</a>
                <a class="btn" href="record.php?table=<?= h($tableKey) ?>&action=add">Add New</a>
            </div>
        </div>

        <div class="record-tabs">
            <?php foreach ($schemas as $key => $s): ?>
                <?php if (!safe_table_exists($pdo, $s['table'])) continue; ?>
                <a class="record-tab <?= $key === $tableKey ? 'active' : '' ?>"
                   href="record.php?table=<?= h($key) ?>&action=<?= h($action === 'delete' ? 'add' : $action) ?>">
                    <?= h($s['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($action === 'delete'): ?>
            <div class="delete-card">
                <h2>Delete this <?= h($schema['label']) ?> record?</h2>
                <p>This cannot be undone. The record will be permanently removed from <b><?= h($dbTable) ?></b>.</p>

                <div class="delete-details">
                    <div class="delete-detail"><small>ID</small><?= (int)$id ?></div>
                    <div class="delete-detail"><small>Date</small><?= h($row['log_date'] ?? '-') ?></div>
                    <div class="delete-detail"><small>Time</small><?= h($row['log_time'] ?? '-') ?></div>
                    <div class="delete-detail"><small>Source</small><?= h($row['source_file'] ?? '-') ?></div>
                </div>

                <form method="post" class="form-footer">
                    <input type="hidden" name="table" value="<?= h($tableKey) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$id ?>">
                    <input type="hidden" name="confirm_delete" value="yes">
                    <a class="btn" href="<?= h($listUrl) ?>">Cancel</a>
                    <button class="btn danger" type="submit">Delete Record</button>
                </form>
            </div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="table" value="<?= h($tableKey) ?>">
                <input type="hidden" name="action" value="<?= h($action) ?>">
                <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

                <div class="record-form">
                    <?php foreach ($schema['fields'] as $field): ?>
                        <?= render_field($field, $row, []) ?>
                    <?php endforeach; ?>
                </div>

                <div class="form-footer">
                    <a class="btn" href="<?= h($listUrl) ?>">Cancel</a>
                    <button class="btn" type="submit"><?= h($actionTitle) ?> Record</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<datalist id="list_operators"><?= datalist_options($operators) ?></datalist>
<datalist id="list_sample_locations"><?= datalist_options($sampleLocations) ?></datalist>
<datalist id="list_gas_locations"><?= datalist_options($gasLocations) ?></datalist>
<datalist id="list_gas_devices"><?= datalist_options($gasDeviceNames) ?></datalist>

<?php if ($tableKey === 'gas_test'): ?>
<script>
const gasDeviceConfig = <?= json_encode($gasDeviceConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

function updateGasFields() {
    const deviceInput = document.querySelector('[name="device"]');
    if (!deviceInput) return;

    const device = deviceInput.value || '';
    const config = gasDeviceConfig[device] || null;

    document.querySelectorAll('.gas-controlled').forEach(wrapper => {
        const flag = wrapper.dataset.gasFlag;
        const enabled = !config || Number(config[flag] ?? 1) === 1;

        wrapper.classList.toggle('gas-disabled', !enabled);

        wrapper.querySelectorAll('input, textarea, select').forEach(input => {
            input.disabled = !enabled;
            if (!enabled) input.value = '';
        });
    });
}

document.addEventListener('input', event => {
    if (event.target && event.target.name === 'device') {
        updateGasFields();
    }
});

document.addEventListener('DOMContentLoaded', updateGasFields);
updateGasFields();
</script>
<?php endif; ?>
</body>
</html>
