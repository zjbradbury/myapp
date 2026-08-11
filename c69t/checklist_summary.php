<?php
require_once "config.php";
requireRole(['admin', 'operator', 'viewer']);

$canEdit = in_array(currentRole(), ['admin', 'operator'], true);
$message = '';
$error = '';

$fieldChecks = [
    'leaks_cleaning_circuit' => 'Leaks in cleaning circuit',
    'tank_top_platform_leaks' => 'View tank from top platform check for leaks',
    'suction_pump_1_discharge_pressure' => 'Suction Pump 1 Discharge Pressure',
    'filter_skid_1_differential_pressure' => 'Filter Skid 1 Differential Pressure',
    'filter_skid_2_differential_pressure' => 'Filter Skid 2 Differential Pressure',
    'gas_return_tank_level' => 'Gas return Tank Level',
    'solids_bin_level' => 'Solids bin level',
    'mercury_filter_skid_1_pressure_differential' => 'Mercury filter skid 1 pressure differential',
    'mercury_filter_skid_2_pressure_differential' => 'Mercury filter skid 2 pressure differential',
    'mercury_filter_skid_3_pressure_differential' => 'Mercury filter skid 3 pressure differential',
    'mercury_filter_skid_4_pressure_differential' => 'Mercury filter skid 4 pressure differential',
    'frac_tank_number_online' => 'Frac tank number online',
    'online_frac_tank_level' => 'Level in online Frac Tank',
    'frac_tank_1_level' => 'Level in Frac Tank 1',
    'frac_tank_2_level' => 'Level in Frac Tank 2',
    'frac_tank_3_level' => 'Level in Frac Tank 3',
    'frac_tank_4_level' => 'Level in Frac Tank 4',
    'compressor_fuel_level' => 'Fuel level in compressor',
    'generator_fuel_level' => 'Fuel level in generator',
    'tower_lights_fuel_level' => 'Fuel level in Tower lights',
    'sampling_gas_flow_rotameter' => 'Sampling gas flow reading at field rotameter',
    'lel_recovery_oil_frac_tank' => 'LEL reading around recovery oil frac tank',
    'tank_roof_level' => 'Tank roof Level',
];

try {
    $columnSql = [];
    foreach ($fieldChecks as $key => $_label) {
        $columnSql[] = "`$key` VARCHAR(255) DEFAULT NULL";
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS checklist_field_checks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        checked_at DATETIME NOT NULL,
        " . implode(",\n", $columnSql) . ",
        created_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_checked_at (checked_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    $error = 'Checklist storage could not be prepared: ' . $e->getMessage();
}

function checklist_datetime(string $value): ?string
{
    if ($value === '') return null;
    $ts = strtotime($value);
    return $ts === false ? null : date('Y-m-d H:i:s', $ts);
}

function checklist_latest_row(PDO $pdo, string $table, string $at): array
{
    if (!tableExists($pdo, $table)) return [];
    $stmt = $pdo->prepare("SELECT *, TIMESTAMP(log_date, log_time) AS source_at
        FROM `$table`
        WHERE TIMESTAMP(log_date, log_time) <= ?
        ORDER BY log_date DESC, log_time DESC, id DESC LIMIT 1");
    $stmt->execute([$at]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

$requestedAt = trim((string)($_GET['at'] ?? $_POST['at'] ?? ''));
$at = checklist_datetime($requestedAt) ?? date('Y-m-d H:i:s');
if ($requestedAt !== '' && checklist_datetime($requestedAt) === null) {
    $error = 'Please enter a valid date and time.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_field_checks']) && $canEdit && $error === '') {
    $columns = array_keys($fieldChecks);
    $quoted = array_map(static fn($key) => "`$key`", $columns);
    $values = [$at];
    foreach ($columns as $column) $values[] = nullIfBlank($_POST[$column] ?? null);
    $values[] = $_SESSION['username'] ?? null;
    $placeholders = implode(',', array_fill(0, count($values), '?'));
    try {
        $stmt = $pdo->prepare("INSERT INTO checklist_field_checks
            (`checked_at`, " . implode(', ', $quoted) . ", `created_by`) VALUES ($placeholders)");
        $stmt->execute($values);
        header('Location: checklist_summary.php?at=' . rawurlencode(date('Y-m-d\TH:i', strtotime($at))) . '&saved=1');
        exit;
    } catch (Throwable $e) {
        $error = 'The field checks could not be saved: ' . $e->getMessage();
    }
}

if (isset($_GET['saved'])) $message = 'Field checks saved.';

$manual = [];
if ($error === '' || tableExists($pdo, 'checklist_field_checks')) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM checklist_field_checks WHERE checked_at <= ? ORDER BY checked_at DESC, id DESC LIMIT 1');
        $stmt->execute([$at]);
        $manual = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        if ($error === '') $error = $e->getMessage();
    }
}

$pump = checklist_latest_row($pdo, 'pump_values_logs', $at);
$nozzle = checklist_latest_row($pdo, 'nozzle_logs', $at);
$tricanter = checklist_latest_row($pdo, 'tricanter_logs', $at);
$nitrogen = checklist_latest_row($pdo, 'nitrogen_logs', $at);

$items = [
    ['manual', 'leaks_cleaning_circuit', $fieldChecks['leaks_cleaning_circuit']],
    ['manual', 'tank_top_platform_leaks', $fieldChecks['tank_top_platform_leaks']],
    ['manual', 'suction_pump_1_discharge_pressure', $fieldChecks['suction_pump_1_discharge_pressure']],
    ['auto', 'suction_pump_2_outlet_pressure', 'Suction Pump 2 Discharge Pressure', $pump, 'BAR'],
    ['manual', 'filter_skid_1_differential_pressure', $fieldChecks['filter_skid_1_differential_pressure']],
    ['manual', 'filter_skid_2_differential_pressure', $fieldChecks['filter_skid_2_differential_pressure']],
    ['manual', 'gas_return_tank_level', $fieldChecks['gas_return_tank_level']],
    ['auto', 'feed_pump_outlet_pressure', 'Feed Pump Discharge Pressure', $pump, 'BAR'],
    ['auto', 'flow', 'Feed Pump Discharge Flow', $nozzle, 'M3/hr'],
    ['auto', 'booster_pump_outlet_pressure', 'Booster Pump Discharge Pressure', $pump, 'BAR'],
    ['auto', 'nozzle', 'Scanjet Nozzle in Operation', $nozzle, ''],
    ['auto', 'feed_rate', 'Tricanter Feed Rate', $tricanter, 'M3/hr'],
    ['auto', 'bowl_rpm', 'Tricanter Bowl RPM', $tricanter, 'RPM'],
    ['auto', 'screw_rpm', 'Tricanter Screw RPM', $tricanter, 'RPM'],
    ['manual', 'solids_bin_level', $fieldChecks['solids_bin_level']],
    ['manual', 'mercury_filter_skid_1_pressure_differential', $fieldChecks['mercury_filter_skid_1_pressure_differential']],
    ['manual', 'mercury_filter_skid_2_pressure_differential', $fieldChecks['mercury_filter_skid_2_pressure_differential']],
    ['manual', 'mercury_filter_skid_3_pressure_differential', $fieldChecks['mercury_filter_skid_3_pressure_differential']],
    ['manual', 'mercury_filter_skid_4_pressure_differential', $fieldChecks['mercury_filter_skid_4_pressure_differential']],
    ['manual', 'frac_tank_number_online', $fieldChecks['frac_tank_number_online']],
    ['manual', 'online_frac_tank_level', $fieldChecks['online_frac_tank_level']],
    ['manual', 'frac_tank_1_level', $fieldChecks['frac_tank_1_level']],
    ['manual', 'frac_tank_2_level', $fieldChecks['frac_tank_2_level']],
    ['manual', 'frac_tank_3_level', $fieldChecks['frac_tank_3_level']],
    ['manual', 'frac_tank_4_level', $fieldChecks['frac_tank_4_level']],
    ['manual', 'compressor_fuel_level', $fieldChecks['compressor_fuel_level']],
    ['manual', 'generator_fuel_level', $fieldChecks['generator_fuel_level']],
    ['manual', 'tower_lights_fuel_level', $fieldChecks['tower_lights_fuel_level']],
    ['auto', 'outlet_purity', 'Oxygen Level in Nitrogen Flow from N2 Generator', $nitrogen, '% O2'],
    ['auto', 'outlet_flow', 'Nitrogen Flow from N2 Generator', $nitrogen, 'M3/hr'],
    ['auto', 'tank_internal_o2', 'Oxygen Reading at Field Transmitter Display', $nitrogen, '%'],
    ['manual', 'sampling_gas_flow_rotameter', $fieldChecks['sampling_gas_flow_rotameter']],
    ['manual', 'lel_recovery_oil_frac_tank', $fieldChecks['lel_recovery_oil_frac_tank']],
    ['manual', 'tank_roof_level', $fieldChecks['tank_roof_level']],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="c69t.ico" type="image/x-icon">
    <title>Checklist Summary</title>
    <link rel="stylesheet" href="indexStyle.css">
    <style>
        body{margin:0;padding:20px;background:#091726;color:#e6f2ff;font-family:Arial,sans-serif}.shell{max-width:1100px;margin:auto}.hero,.search,.card{background:#10273c;border:1px solid #214968;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.25)}.hero{padding:22px;margin:55px 0 14px}.hero h1{margin:0 0 6px;font-size:30px}.muted,.source{color:#9cc1de}.search{padding:16px;margin-bottom:14px;display:flex;gap:12px;align-items:end;flex-wrap:wrap}.search label{display:grid;gap:6px;font-size:12px}.search input,.field-input{background:#091b2c;color:#fff;border:1px solid #315d7d;border-radius:8px;padding:10px}.btn{background:#1f4f74;color:#fff;border:0;border-radius:8px;padding:10px 14px;text-decoration:none;cursor:pointer}.card{overflow:hidden}.row{display:grid;grid-template-columns:minmax(280px,1.4fr) minmax(180px,1fr) 185px;gap:14px;align-items:center;padding:12px 16px;border-bottom:1px solid rgba(138,188,230,.12)}.row:last-child{border-bottom:0}.label{font-weight:700}.kind{font-size:11px;color:#7fc5fa;text-transform:uppercase;margin-top:4px}.value{font-size:17px;font-weight:700}.source{font-size:11px}.field-input{width:100%;box-sizing:border-box}.actions{padding:16px;display:flex;justify-content:flex-end}.notice{padding:12px;border-radius:8px;margin-bottom:14px}.ok{background:#123d35}.error{background:#572a2a}@media(max-width:760px){.row{grid-template-columns:1fr}.source{text-align:left}.hero{margin-top:65px}}
    </style>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="shell">
    <section class="hero"><h1>Checklist Summary</h1><div class="muted">Latest readings available at or before the selected time.</div></section>
    <?php if ($message): ?><div class="notice ok"><?= h($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?= h($error) ?></div><?php endif; ?>
    <form method="get" class="search">
        <label>Search date and time<input type="datetime-local" name="at" value="<?= h(date('Y-m-d\TH:i', strtotime($at))) ?>"></label>
        <button class="btn" type="submit">Show Snapshot</button><a class="btn" href="checklist_summary.php">Latest</a>
    </form>
    <form method="post">
        <input type="hidden" name="at" value="<?= h(date('Y-m-d\TH:i', strtotime($at))) ?>">
        <section class="card">
        <?php foreach ($items as $item): [$kind, $key, $label] = $item; ?>
            <div class="row">
                <div><div class="label"><?= h($label) ?></div><div class="kind"><?= $kind === 'manual' ? 'Field check' : 'From table' ?></div></div>
                <?php if ($kind === 'manual'): ?>
                    <?php if ($canEdit): ?><input class="field-input" type="text" name="<?= h($key) ?>" value="<?= h($manual[$key] ?? '') ?>" placeholder="Enter field check"><?php else: ?><div class="value"><?= h(($manual[$key] ?? '') !== '' ? $manual[$key] : '-') ?></div><?php endif; ?>
                    <div class="source"><?= !empty($manual['checked_at']) ? 'Checked ' . h(date('d/m/Y H:i', strtotime($manual['checked_at']))) : 'No saved check' ?></div>
                <?php else: $row = $item[3]; $unit = $item[4]; $value = $row[$key] ?? null; ?>
                    <div class="value"><?= h($value === null || $value === '' ? '-' : $value . ($unit ? ' ' . $unit : '')) ?></div>
                    <div class="source"><?= !empty($row['source_at']) ? 'Logged ' . h(date('d/m/Y H:i', strtotime($row['source_at']))) : 'No data' ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if ($canEdit): ?><div class="actions"><button class="btn" type="submit" name="save_field_checks" value="1">Save Field Checks</button></div><?php endif; ?>
        </section>
    </form>
</main>
</body>
</html>
