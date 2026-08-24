<?php
require_once "config.php";
requireRole(['admin', 'operator', 'viewer']);

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

$requestedAt = trim((string)($_GET['at'] ?? ''));
$at = checklist_datetime($requestedAt) ?? date('Y-m-d H:i:s');
if ($requestedAt !== '' && checklist_datetime($requestedAt) === null) {
    $error = 'Please enter a valid date and time.';
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
    <link rel="stylesheet" href="checklist_summary.css">
</head>
<body>
<?php include 'nav.php'; ?>
<main class="shell">
    <section class="hero"><h1>Checklist Summary</h1><div class="muted">Latest readings available at or before the selected time.</div></section>
    <?php if ($error): ?><div class="notice error"><?= h($error) ?></div><?php endif; ?>
    <form method="get" class="search">
        <label>Search date and time<input type="datetime-local" name="at" value="<?= h(date('Y-m-d\TH:i', strtotime($at))) ?>"></label>
        <button class="btn" type="submit">Show Values</button><a class="btn" href="checklist_summary.php">Latest</a>
    </form>
    <section class="card">
        <?php foreach ($items as $item): [$kind, $key, $label] = $item; ?>
            <div class="row">
                <div class="label"><?= h($label) ?></div>
                <?php if ($kind === 'manual'): ?>
                    <div class="value">Field Check</div>
                <?php else: $row = $item[3]; $unit = $item[4]; $value = $row[$key] ?? null; ?>
                    <div class="value"><?= h($value === null || $value === '' ? '-' : $value . ($unit ? ' ' . $unit : '')) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>
