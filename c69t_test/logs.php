<?php
require_once "config.php";
require_once __DIR__ . '/log_filter_helpers.php';
requireLogin();

$canEdit = in_array(currentRole(), ["admin", "operator"], true);
$canDelete = $canEdit;

$tables = [
    'tricanter' => [
        'label' => 'Tricanter',
        'table' => 'tricanter_logs',
        'add' => 'record.php?action=add&table=tricanter',
        'edit' => 'record.php?action=edit&table=tricanter',
        'delete' => 'record.php?action=delete&table=tricanter',
        'desc' => 'Bowl, screw, feed, torque, temperature, and pressure log history.',
        'columns' => [
            ['key' => 'log_date', 'label' => 'Date'],
            ['key' => 'log_time', 'label' => 'Time'],
            ['key' => 'tricanter_status', 'label' => 'State', 'decimals' => 0],
            ['key' => 'bowl_speed', 'label' => 'Bowl Speed', 'suffix' => ' %', 'decimals' => 0],
            ['key' => 'screw_speed', 'label' => 'Screw Speed', 'suffix' => ' %', 'decimals' => 2],
            ['key' => 'bowl_rpm', 'label' => 'Bowl RPM', 'suffix' => ' RPM', 'decimals' => 0],
            ['key' => 'screw_rpm', 'label' => 'Screw RPM', 'suffix' => ' RPM', 'decimals' => 2],
            ['key' => 'impeller', 'label' => 'Impeller', 'decimals' => 0],
            ['key' => 'feed_rate', 'label' => 'Feed Rate', 'suffix' => ' M3/hr', 'decimals' => 2],
            ['key' => 'torque', 'label' => 'Torque', 'suffix' => ' %', 'decimals' => 1],
            ['key' => 'temp', 'label' => 'Temp', 'suffix' => ' °C', 'decimals' => 1],
            ['key' => 'pressure', 'label' => 'Pressure', 'suffix' => ' BAR', 'decimals' => 3],
            ['key' => 'comments', 'label' => 'Comments', 'class' => 'comment-cell'],
        ],
    ],
    'solid_waste' => [
        'label' => 'Solid Waste',
        'table' => 'solid_waste_logs',
        'add' => 'record.php?action=add&table=solid_waste',
        'edit' => 'record.php?action=edit&table=solid_waste',
        'delete' => 'record.php?action=delete&table=solid_waste',
        'desc' => 'Solid waste amount logs with selected range filtering.',
        'columns' => [
            ['key' => 'log_date', 'label' => 'Date'],
            ['key' => 'log_time', 'label' => 'Time'],
            ['key' => 'amount', 'label' => 'Amount', 'suffix' => ' KG', 'decimals' => 0],
            ['key' => 'comments', 'label' => 'Comments', 'class' => 'comment-cell'],
        ],
    ],
    'recovered_water' => [
        'label' => 'Recovered Water Pump',
        'table' => 'recovered_water_pump_logs',
        'add' => 'record.php?action=add&table=recovered_water',
        'edit' => 'record.php?action=edit&table=recovered_water',
        'delete' => 'record.php?action=delete&table=recovered_water',
        'desc' => 'Recovered water pump start and stop levels with cycle history.',
        'columns' => [
            ['key' => 'log_date', 'label' => 'Date'],
            ['key' => 'log_time', 'label' => 'Time'],
            ['key' => 'start_level', 'label' => 'Start Level', 'decimals' => 2],
            ['key' => 'stop_level', 'label' => 'Stop Level', 'decimals' => 2],
            ['key' => 'comments', 'label' => 'Comments', 'class' => 'comment-cell'],
        ],
    ],
    'nozzle' => [
        'label' => 'Nozzle',
        'table' => 'nozzle_logs',
        'add' => 'record.php?action=add&table=nozzle',
        'edit' => 'record.php?action=edit&table=nozzle',
        'delete' => 'record.php?action=delete&table=nozzle',
        'desc' => 'Nozzle flow, pressure, angle, and RPM records.',
        'columns' => [
            ['key' => 'log_date', 'label' => 'Date'],
            ['key' => 'log_time', 'label' => 'Time'],
            ['key' => 'nozzle_status', 'label' => 'State', 'type' => 'bool'],
            ['key' => 'nozzle', 'label' => 'Nozzle', 'prefix' => 'N'],
            ['key' => 'flow', 'label' => 'Flow', 'suffix' => ' M3/hr', 'decimals' => 1],
            ['key' => 'pressure', 'label' => 'Pressure', 'suffix' => ' BAR', 'decimals' => 2],
            ['key' => 'min_deg', 'label' => 'Min Deg', 'suffix' => ' °', 'decimals' => 0],
            ['key' => 'max_deg', 'label' => 'Max Deg', 'suffix' => ' °', 'decimals' => 0],
            ['key' => 'rpm', 'label' => 'RPM', 'suffix' => ' RPM', 'decimals' => 1],
            ['key' => 'comments', 'label' => 'Comments', 'class' => 'comment-cell'],
        ],
    ],
    'sample' => [
        'label' => 'Sample',
        'table' => 'sample_logs',
        'add' => 'record.php?action=add&table=sample',
        'edit' => 'record.php?action=edit&table=sample',
        'delete' => 'record.php?action=delete&table=sample',
        'desc' => 'Sample readings by location, nozzle, operator, and material percentage.',
        'columns' => [
            ['key' => 'log_date', 'label' => 'Date'],
            ['key' => 'log_time', 'label' => 'Time'],
            ['key' => 'sample_location', 'label' => 'Sample Location'],
            ['key' => 'nozzle', 'label' => 'Nozzle'],
            ['key' => 'flow', 'label' => 'Flow', 'suffix' => ' M3/hr', 'decimals' => 2],
            ['key' => 'mercury', 'label' => 'Mercury', 'suffix' => ' %', 'decimals' => 3],
            ['key' => 'solids', 'label' => 'Solids', 'suffix' => ' %', 'decimals' => 2],
            ['key' => 'water', 'label' => 'Water', 'suffix' => ' %', 'decimals' => 2],
            ['key' => 'wax', 'label' => 'Wax', 'suffix' => ' %', 'decimals' => 2],
            ['key' => 'operator', 'label' => 'Operator'],
            ['key' => 'comments', 'label' => 'Comments', 'class' => 'comment-cell'],
        ],
    ],
    'gas_test' => [
        'label' => 'Gas Test',
        'table' => 'gas_test_logs',
        'add' => 'record.php?action=add&table=gas_test',
        'edit' => 'record.php?action=edit&table=gas_test',
        'delete' => 'record.php?action=delete&table=gas_test',
        'desc' => 'Gas readings, locations, product details, and actions taken.',
        'columns' => [
            ['key' => 'log_date', 'label' => 'Date'],
            ['key' => 'log_time', 'label' => 'Time'],
            ['key' => 'device', 'label' => 'Device'],
            ['key' => 'operator', 'label' => 'Operator'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'area_details', 'label' => 'Area Details', 'class' => 'comment-cell'],
            ['key' => 'mercury', 'label' => 'Mercury', 'suffix' => ' µg/m³', 'decimals' => 3],
            ['key' => 'benzene', 'label' => 'Benzene', 'suffix' => ' ppm', 'decimals' => 2],
            ['key' => 'lel', 'label' => 'LEL', 'suffix' => ' %', 'decimals' => 1],
            ['key' => 'h2s', 'label' => 'H2S', 'suffix' => ' ppm', 'decimals' => 1],
            ['key' => 'o2', 'label' => 'O2', 'suffix' => ' %', 'decimals' => 1],
            ['key' => 'product_details', 'label' => 'Product Details', 'class' => 'comment-cell'],
            ['key' => 'action_taken', 'label' => 'Actions Taken', 'class' => 'comment-cell'],
        ],
    ],
    'project_flow' => [
        'label' => 'Project Flow',
        'table' => 'project_flow_logs',
        'add' => 'record.php?action=add&table=project_flow',
        'edit' => 'record.php?action=edit&table=project_flow',
        'delete' => 'record.php?action=delete&table=project_flow',
        'desc' => 'Project flow totaliser history for recovered oil, recovered water, solid waste, tricanter, and nozzle totals.',
        'columns' => [
            ['key' => 'log_date', 'label' => 'Date'],
            ['key' => 'log_time', 'label' => 'Time'],
            ['key' => 'total_recovered_oil', 'label' => 'Recovered Oil', 'suffix' => ' m³', 'decimals' => 4],
            ['key' => 'total_recovered_water', 'label' => 'Recovered Water', 'suffix' => ' m³', 'decimals' => 4],
            ['key' => 'total_solid_waste', 'label' => 'Solid Waste', 'suffix' => ' KG', 'decimals' => 4],
            ['key' => 'total_tricanter', 'label' => 'Tricanter', 'suffix' => ' m³', 'decimals' => 4],
            ['key' => 'total_nozzle', 'label' => 'Nozzle', 'suffix' => ' m³', 'decimals' => 4],
            ['key' => 'comments', 'label' => 'Comments', 'class' => 'comment-cell'],
        ],
    ],
    'pump_values' => [
        'label' => 'Pump Values',
        'table' => 'pump_values_logs',
        'add' => 'record.php?action=add&table=pump_values',
        'edit' => 'record.php?action=edit&table=pump_values',
        'delete' => 'record.php?action=delete&table=pump_values',
        'desc' => 'Pump statuses, feedback, inlet pressure, and outlet pressure records.',
        'columns' => [
            ['key' => 'log_date', 'label' => 'Date'],
            ['key' => 'log_time', 'label' => 'Time'],
            ['key' => 'suction_pump_1_status', 'label' => 'SP1 Status', 'type' => 'pump_status'],
            ['key' => 'suction_pump_2_status', 'label' => 'SP2 Status', 'type' => 'pump_status'],
            ['key' => 'suction_pump_3_status', 'label' => 'SP3 Status', 'type' => 'pump_status'],
            ['key' => 'suction_pump_2_speed_out', 'label' => 'SP2 Speed Out', 'suffix' => ' %', 'decimals' => 2],
            ['key' => 'suction_pump_2_feedback', 'label' => 'SP2 Feedback', 'type' => 'pump_feedback', 'decimals' => 2],
            ['key' => 'suction_pump_2_inlet_pressure', 'label' => 'SP2 Inlet', 'suffix' => ' BAR', 'decimals' => 3],
            ['key' => 'suction_pump_2_outlet_pressure', 'label' => 'SP2 Outlet', 'suffix' => ' BAR', 'decimals' => 3],
            ['key' => 'feed_pump_status', 'label' => 'FP Status', 'type' => 'pump_status'],
            ['key' => 'feed_pump_speed_out', 'label' => 'FP Speed Out', 'suffix' => ' %', 'decimals' => 2],
            ['key' => 'feed_pump_feedback', 'label' => 'FP Feedback', 'type' => 'pump_feedback', 'decimals' => 2],
            ['key' => 'feed_pump_inlet_pressure', 'label' => 'FP Inlet', 'suffix' => ' BAR', 'decimals' => 3],
            ['key' => 'feed_pump_outlet_pressure', 'label' => 'FP Outlet', 'suffix' => ' BAR', 'decimals' => 3],
            ['key' => 'booster_pump_status', 'label' => 'BP Status', 'type' => 'pump_status'],
            ['key' => 'booster_pump_speed_out', 'label' => 'BP Speed Out', 'suffix' => ' %', 'decimals' => 2],
            ['key' => 'booster_pump_feedback', 'label' => 'BP Feedback', 'type' => 'pump_feedback', 'decimals' => 2],
            ['key' => 'booster_pump_inlet_pressure', 'label' => 'BP Inlet', 'suffix' => ' BAR', 'decimals' => 3],
            ['key' => 'booster_pump_outlet_pressure', 'label' => 'BP Outlet', 'suffix' => ' BAR', 'decimals' => 3],
            ['key' => 'comments', 'label' => 'Comments', 'class' => 'comment-cell'],
        ],
    ],
    'nitrogen' => [
        'label' => 'Nitrogen',
        'table' => 'nitrogen_logs',
        'add' => 'record.php?action=add&table=nitrogen',
        'edit' => 'record.php?action=edit&table=nitrogen',
        'delete' => 'record.php?action=delete&table=nitrogen',
        'desc' => 'Nitrogen generator status, purity, flow, pressures, heater temperatures, and interior oxygen records.',
        'columns' => [
            ['key' => 'log_date', 'label' => 'Date'],
            ['key' => 'log_time', 'label' => 'Time'],
            ['key' => 'nitrogen_active', 'label' => 'Active', 'type' => 'bool'],
            ['key' => 'trip_status', 'label' => 'Trip', 'type' => 'trip_status'],
            ['key' => 'outlet_flow', 'label' => 'Outlet Flow', 'suffix' => ' M3/hr', 'decimals' => 2],
            ['key' => 'outlet_purity', 'label' => 'Outlet Purity', 'suffix' => ' % O2', 'decimals' => 2],
            ['key' => 'inlet_pressure', 'label' => 'Inlet Pressure', 'suffix' => ' BAR', 'decimals' => 3],
            ['key' => 'outlet_pressure', 'label' => 'Outlet Pressure', 'suffix' => ' BAR', 'decimals' => 3],
            ['key' => 'pre_heat_temp', 'label' => 'Pre Heat Temp', 'suffix' => ' °C', 'decimals' => 1],
            ['key' => 'post_heat_temp', 'label' => 'Post Heat Temp', 'suffix' => ' °C', 'decimals' => 1],
            ['key' => 'interior_o2', 'label' => 'Interior O2', 'suffix' => ' %', 'decimals' => 2],
            ['key' => 'tank_internal_o2', 'label' => 'Tank Internal O2', 'suffix' => ' %', 'decimals' => 2],
            ['key' => 'comments', 'label' => 'Comments', 'class' => 'comment-cell'],
        ],
    ],
];

function pump_status_text_for_logs($value): string
{
    if ($value === null || $value === '' || !is_numeric($value)) return '-';
    $value = (int)$value;
    if ($value === 0) return 'OFF';
    if ($value === 1) return 'ON';
    if ($value === 2) return 'ERROR';
    return (string)$value;
}

function bool_text_for_logs($value): string
{
    if ($value === null || $value === '') return '-';
    if (is_numeric($value)) return ((int)$value === 1) ? 'ON' : 'OFF';
    $v = strtolower(trim((string)$value));
    if (in_array($v, ['true', 'on', 'yes', '1'], true)) return 'ON';
    if (in_array($v, ['false', 'off', 'no', '0'], true)) return 'OFF';
    return (string)$value;
}

function trip_status_text_for_logs($value): string
{
    if ($value === null || $value === '') return '-';
    if (is_numeric($value)) return ((int)$value === 1) ? 'TRIPPED' : 'OK';

    $v = strtolower(trim((string)$value));
    if (in_array($v, ['true', 'on', 'yes', '1', 'tripped'], true)) return 'TRIPPED';
    if (in_array($v, ['false', 'off', 'no', '0', 'ok'], true)) return 'OK';
    return (string)$value;
}

function pump_feedback_text_for_logs($value, int $decimals = 2): string
{
    if ($value === null || $value === '') return '-';
    if (!is_numeric($value)) return (string)$value;
    if ((float)$value < 0) return '###';
    return fmt($value, $decimals);
}

function selected_table_key(array $tables): string
{
    $key = $_GET['table'] ?? $_POST['table'] ?? $_GET['type'] ?? $_POST['type'] ?? 'tricanter';
    $key = strtolower(trim((string)$key));
    $key = str_replace(['-', ' '], '_', $key);
    return isset($tables[$key]) ? $key : 'tricanter';
}

function log_cell_value(array $row, array $col): string
{
    $key = $col['key'];
    $value = $row[$key] ?? '';

    if ($value === null || $value === '') return '-';

    $type = $col['type'] ?? '';
    if ($type === 'pump_status') return h(pump_status_text_for_logs($value));
    if ($type === 'bool') return h(bool_text_for_logs($value));
    if ($type === 'trip_status') return h(trip_status_text_for_logs($value));
    if ($type === 'pump_feedback') return h(pump_feedback_text_for_logs($value, (int)($col['decimals'] ?? 2)));

    $prefix = $col['prefix'] ?? '';
    $suffix = $col['suffix'] ?? '';

    if (array_key_exists('decimals', $col) && is_numeric($value)) {
        return h($prefix . fmt($value, (int)$col['decimals']) . $suffix);
    }

    return h($prefix . (string)$value . $suffix);
}


function selected_interval_minutes(): int
{
    $allowed = [0, 1, 5, 10, 15, 30, 60, 120, 360, 720];
    $value = (int)($_GET['interval'] ?? $_POST['interval'] ?? 0);
    return in_array($value, $allowed, true) ? $value : 0;
}

function selected_time_search(): string
{
    return trim((string)($_GET['time_search'] ?? $_POST['time_search'] ?? ''));
}

function selected_value_columns(array $config): array
{
    $requested = $_GET['has_value'] ?? $_POST['has_value'] ?? [];
    if (!is_array($requested)) {
        $requested = [$requested];
    }

    $allowed = [];
    foreach ($config['columns'] as $column) {
        $key = (string)($column['key'] ?? '');
        if ($key !== '' && !in_array($key, ['log_date', 'log_time'], true)) {
            $allowed[$key] = true;
        }
    }

    return array_values(array_unique(array_filter(
        array_map(static fn($value): string => trim((string)$value), $requested),
        static fn(string $key): bool => isset($allowed[$key])
    )));
}

function url_with_current_state(string $baseUrl, array $extra = []): string
{
    $params = $_GET;
    unset($params['msg']);

    foreach ($extra as $key => $value) {
        if ($value === null) unset($params[$key]);
        else $params[$key] = $value;
    }

    $separator = strpos($baseUrl, '?') === false ? '?' : '&';
    return $baseUrl . ($params ? $separator . http_build_query($params) : '');
}

function record_action_url(string $baseUrl, int $id): string
{
    return url_with_current_state($baseUrl, ['id' => $id]);
}

function nav_url_for_table(string $key): string
{
    $params = $_GET;
    unset($params['msg']);
    unset($params['has_value']);
    $params['table'] = $key;
    return 'logs.php?' . http_build_query($params);
}

$selectedKey = selected_table_key($tables);
$config = $tables[$selectedKey];
$range = get_range_filter_state(true);
$selectedInterval = selected_interval_minutes();
$timeSearch = selected_time_search();
$selectedValueColumns = selected_value_columns($config);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && $canDelete) {
    $postedKey = selected_table_key($tables);
    $postedConfig = $tables[$postedKey];
    $selectedIds = $_POST['selected_ids'] ?? [];

    if (!is_array($selectedIds) || !$selectedIds) {
        $error = 'No records were selected.';
    } else {
        $ids = array_values(array_unique(array_filter(array_map('intval', $selectedIds), fn($id) => $id > 0)));

        if (!$ids) {
            $error = 'No valid records were selected.';
        } else {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM `" . $postedConfig['table'] . "` WHERE id IN ($placeholders)");
            $stmt->execute($ids);

            $qs = $_GET;
            unset($qs['msg']);
            $qs['table'] = $postedKey;
            $qs['msg'] = $stmt->rowCount() . ' record(s) deleted';

            header('Location: logs.php?' . http_build_query($qs));
            exit;
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $message = (string)$_GET['msg'];
}

$rows = [];
try {
    if (function_exists('tableExists') && !tableExists($pdo, $config['table'])) {
        $error = $config['table'] . ' does not exist yet.';
    } else {
        $rows = fetch_log_rows($pdo, $config['table'], $range, 'log_date DESC, log_time DESC, id DESC');
        $rows = filter_rows_by_log_time($rows, $timeSearch);
        $rows = filter_rows_with_values($rows, $selectedValueColumns);
        $rows = sample_log_rows_by_minutes($rows, $selectedInterval);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$csvParams = [
    'table' => $config['table'],
    'start' => $range['start'] ?? '',
    'end' => $range['end'] ?? '',
    'quick' => $range['quick'] ?? '',
    'interval' => $selectedInterval,
    'time_search' => $timeSearch,
    'has_value' => $selectedValueColumns,
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="c69t.ico" type="image/x-icon">
    <title><?= h($config['label']) ?> Logs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="indexStyle.css">
    <link rel="stylesheet" href="logs.css">
</head>

<body>
    <?php require_once "nav.php"; ?>

    <div class="logs-shell">
        <div class="logo-row">
            <img src="MoombaTankCleaningLogoTransparent.PNG" alt="Moomba Tank Cleaning">
            <img src="Contract69TanksLogoTransparent.png" alt="Contract 69 Tanks">
        </div>

        <div class="logs-hero info-card">
            <div>
                <div class="section-kicker">log viewer</div>
                <h1 class="logs-title"><?= h($config['label']) ?> Logs</h1>
                <div class="logs-subtitle"><?= h($config['desc']) ?></div>
            </div>

            <div class="logs-actions">
                <?php if ($canEdit && !empty($config['add'])): ?>
                    <a class="btn" href="<?= h(url_with_current_state($config['add'])) ?>">Add Record</a>
                <?php endif; ?>
                <?php if (currentRole() === 'admin'): ?>
                    <a class="btn" href="csv_download.php?<?= h(http_build_query($csvParams)) ?>">Download CSV</a>
                <?php endif; ?>
                <a class="btn" href="excel_download.php">Export to Excel</a>
            </div>
        </div>

        <div class="tabs-card panel">
            <div class="log-tabs">
                <?php foreach ($tables as $key => $tableConfig): ?>
                    <a class="log-tab <?= $key === $selectedKey ? 'active' : '' ?>" href="<?= h(nav_url_for_table($key)) ?>">
                        <?= h($tableConfig['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="filter-card panel">
            <?php render_range_filter($range, 'Filtering ' . $config['label'] . ' table to selected range'); ?>

            <form method="get" class="list-extra-filter-form" style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                <?php foreach ($_GET as $key => $value): ?>
                    <?php if (!in_array($key, ['interval', 'time_search', 'has_value'], true) && !is_array($value)): ?>
                        <input type="hidden" name="<?= h($key) ?>" value="<?= h((string)$value) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>

                <label style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#9cc1de;">
                    Show interval
                    <select name="interval">
                        <option value="0" <?= $selectedInterval === 0 ? 'selected' : '' ?>>All records</option>
                        <option value="1" <?= $selectedInterval === 1 ? 'selected' : '' ?>>Every 1 minute</option>
                        <option value="5" <?= $selectedInterval === 5 ? 'selected' : '' ?>>Every 5 minutes</option>
                        <option value="10" <?= $selectedInterval === 10 ? 'selected' : '' ?>>Every 10 minutes</option>
                        <option value="15" <?= $selectedInterval === 15 ? 'selected' : '' ?>>Every 15 minutes</option>
                        <option value="30" <?= $selectedInterval === 30 ? 'selected' : '' ?>>Every 30 minutes</option>
                        <option value="60" <?= $selectedInterval === 60 ? 'selected' : '' ?>>Every 60 minutes</option>
                        <option value="120" <?= $selectedInterval === 120 ? 'selected' : '' ?>>Every 2 hours</option>
                        <option value="360" <?= $selectedInterval === 360 ? 'selected' : '' ?>>Every 6 hours</option>
                        <option value="720" <?= $selectedInterval === 720 ? 'selected' : '' ?>>Every 12 hours</option>
                    </select>
                </label>

                <label style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#9cc1de;">
                    Time search
                    <input type="text" name="time_search" value="<?= h($timeSearch) ?>" placeholder="06:15 or 06:">
                </label>

                <details class="column-value-filter" <?= $selectedValueColumns ? 'open' : '' ?>>
                    <summary>
                        Has an entry<?= $selectedValueColumns ? ' (' . count($selectedValueColumns) . ' selected)' : '' ?>
                    </summary>
                    <div class="column-value-options">
                        <?php foreach ($config['columns'] as $column): ?>
                            <?php
                            $columnKey = (string)($column['key'] ?? '');
                            if ($columnKey === '' || in_array($columnKey, ['log_date', 'log_time'], true)) continue;
                            ?>
                            <label>
                                <input type="checkbox"
                                    name="has_value[]"
                                    value="<?= h($columnKey) ?>"
                                    <?= in_array($columnKey, $selectedValueColumns, true) ? 'checked' : '' ?>>
                                <?= h($column['label'] ?? $columnKey) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </details>

                <button class="btn" type="submit">Apply</button>
                <a class="btn" href="<?= h(url_with_current_state('logs.php', ['interval' => null, 'time_search' => null, 'has_value' => null])) ?>">Clear Log Filters</a>
            </form>
        </div>

        <?php if ($message !== ''): ?>
            <div class="notice-success"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="notice-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" id="bulkDeleteForm">
            <input type="hidden" name="bulk_delete" value="1">
            <input type="hidden" name="table" value="<?= h($selectedKey) ?>">

            <div class="table-panel panel">
                <div class="table-toolbar">
                    <div class="record-count"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?> loaded</div>
                    <?php if ($canDelete): ?>
                        <button class="btn danger" type="submit" onclick="return confirm('Delete selected <?= h($config['label']) ?> record(s)?');">
                            Delete Selected
                        </button>
                    <?php endif; ?>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <?php if ($canDelete): ?>
                                    <th class="checkbox-col">
                                        <input type="checkbox" id="selectAllRows" aria-label="Select all rows">
                                    </th>
                                <?php endif; ?>

                                <?php foreach ($config['columns'] as $col): ?>
                                    <th><?= h($col['label']) ?></th>
                                <?php endforeach; ?>

                                <?php if (($canEdit && !empty($config['edit'])) || ($canDelete && !empty($config['delete']))): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="<?= count($config['columns']) + ((($canEdit && !empty($config['edit'])) || ($canDelete && !empty($config['delete']))) ? 1 : 0) + ($canDelete ? 1 : 0) ?>">
                                        No records found in selected range.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $row): ?>
                                    <?php
                                    $rowAlertClass = '';
                                    if (
                                        $selectedKey === 'tricanter'
                                        && isset($row['tricanter_status'])
                                        && $row['tricanter_status'] !== ''
                                        && (int)$row['tricanter_status'] !== 5
                                    ) {
                                        $rowAlertClass = 'tri-row tricanter-status-alert';
                                    } elseif (
                                        $selectedKey === 'nozzle'
                                        && isset($row['nozzle_status'])
                                        && $row['nozzle_status'] !== ''
                                        && (int)$row['nozzle_status'] === 0
                                    ) {
                                        $rowAlertClass = 'nozzle-row nozzle-status-alert';
                                    } elseif (
                                        $selectedKey === 'nitrogen'
                                        && isset($row['nitrogen_active'])
                                        && $row['nitrogen_active'] !== ''
                                        && (int)$row['nitrogen_active'] === 0
                                    ) {
                                        $rowAlertClass = 'nitrogen-row nitrogen-status-alert';
                                    }
                                    ?>
                                    <tr class="<?= h($rowAlertClass) ?>">
                                        <?php if ($canDelete): ?>
                                            <td class="checkbox-cell">
                                                <input type="checkbox" name="selected_ids[]" value="<?= (int)$row['id'] ?>">
                                            </td>
                                        <?php endif; ?>

                                        <?php foreach ($config['columns'] as $col): ?>
                                            <td class="<?= h($col['class'] ?? '') ?>"><?= log_cell_value($row, $col) ?></td>
                                        <?php endforeach; ?>

                                        <?php if (($canEdit && !empty($config['edit'])) || ($canDelete && !empty($config['delete']))): ?>
                                            <td>
                                                <div class="actions-cell">
                                                    <?php if ($canEdit && !empty($config['edit'])): ?>
                                                        <a class="btn small" href="<?= h(record_action_url($config['edit'], (int)$row['id'])) ?>">Edit</a>
                                                    <?php endif; ?>

                                                    <?php if ($canDelete && !empty($config['delete'])): ?>
                                                        <a class="btn small danger"
                                                            href="<?= h(record_action_url($config['delete'], (int)$row['id'])) ?>"
                                                            onclick="return confirm('Delete this record?');">
                                                            Delete
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>

    <script>
        const selectAllRows = document.getElementById('selectAllRows');
        if (selectAllRows) {
            selectAllRows.addEventListener('change', function() {
                document.querySelectorAll('input[name="selected_ids[]"]').forEach(function(box) {
                    box.checked = selectAllRows.checked;
                });
            });
        }
    </script>
</body>

</html>
