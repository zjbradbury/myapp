<?php
require_once "config.php";
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
        'add' => null,
        'edit' => null,
        'delete' => null,
        'desc' => 'Pump statuses, feedback, inlet pressure, and outlet pressure records.',
        'columns' => [
                        ['key' => 'log_date', 'label' => 'Date'],
            ['key' => 'log_time', 'label' => 'Time'],
            ['key' => 'suction_pump_1_status', 'label' => 'SP1 Status', 'type' => 'pump_status'],
            ['key' => 'suction_pump_2_status', 'label' => 'SP2 Status', 'type' => 'pump_status'],
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
        'add' => null,
        'edit' => null,
        'delete' => null,
        'desc' => 'Nitrogen generator status, purity, flow, pressures, heater temperatures, and interior oxygen records.',
        'columns' => [
                        ['key' => 'log_date', 'label' => 'Date'],
            ['key' => 'log_time', 'label' => 'Time'],
            ['key' => 'nitrogen_active', 'label' => 'Active', 'type' => 'bool'],
            ['key' => 'trip_status', 'label' => 'Trip', 'type' => 'bool'],
            ['key' => 'outlet_flow', 'label' => 'Outlet Flow', 'suffix' => ' M3/hr', 'decimals' => 2],
            ['key' => 'outlet_purity', 'label' => 'Outlet Purity', 'suffix' => ' % O2', 'decimals' => 2],
            ['key' => 'inlet_pressure', 'label' => 'Inlet Pressure', 'suffix' => ' BAR', 'decimals' => 3],
            ['key' => 'outlet_pressure', 'label' => 'Outlet Pressure', 'suffix' => ' BAR', 'decimals' => 3],
            ['key' => 'pre_heat_temp', 'label' => 'Pre Heat Temp', 'suffix' => ' °C', 'decimals' => 1],
            ['key' => 'post_heat_temp', 'label' => 'Post Heat Temp', 'suffix' => ' °C', 'decimals' => 1],
            ['key' => 'interior_o2', 'label' => 'Interior O2', 'suffix' => ' %', 'decimals' => 2],
            ['key' => 'comments', 'label' => 'Comments', 'class' => 'comment-cell'],
        ],
    ],
];


function pump_status_text_for_logs($value): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return '-';
    }

    $value = (int)$value;
    if ($value === 0) return 'OFF';
    if ($value === 1) return 'ON';
    if ($value === 2) return 'ERROR';

    return (string)$value;
}

function bool_text_for_logs($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    if (is_numeric($value)) {
        return ((int)$value === 1) ? 'ON' : 'OFF';
    }

    $v = strtolower(trim((string)$value));
    if (in_array($v, ['true', 'on', 'yes', '1'], true)) return 'ON';
    if (in_array($v, ['false', 'off', 'no', '0'], true)) return 'OFF';

    return (string)$value;
}

function pump_feedback_text_for_logs($value, int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    if (!is_numeric($value)) {
        return (string)$value;
    }

    if ((float)$value < 0) {
        return '###';
    }

    return fmt($value, $decimals);
}

function selected_table_key(array $tables): string
{
    $key = $_GET['table'] ?? $_GET['type'] ?? 'tricanter';
    $key = strtolower(trim((string)$key));
    $key = str_replace(['-', ' '], '_', $key);
    return isset($tables[$key]) ? $key : 'tricanter';
}

function log_cell_value(array $row, array $col): string
{
    $key = $col['key'];
    $value = $row[$key] ?? '';

    if ($value === null || $value === '') {
        return '-';
    }

    $type = $col['type'] ?? '';

    if ($type === 'pump_status') {
        return h(pump_status_text_for_logs($value));
    }

    if ($type === 'bool') {
        return h(bool_text_for_logs($value));
    }

    if ($type === 'pump_feedback') {
        return h(pump_feedback_text_for_logs($value, (int)($col['decimals'] ?? 2)));
    }

    $prefix = $col['prefix'] ?? '';
    $suffix = $col['suffix'] ?? '';

    if (array_key_exists('decimals', $col) && is_numeric($value)) {
        return h($prefix . fmt($value, (int)$col['decimals']) . $suffix);
    }

    return h($prefix . (string)$value . $suffix);
}

$selectedKey = selected_table_key($tables);
$config = $tables[$selectedKey];

$range = get_range_filter_state(true);
$message = '';
$error = '';


function record_action_url(string $baseUrl, int $id): string
{
    $separator = strpos($baseUrl, '?') === false ? '?' : '&';
    return $baseUrl . $separator . 'id=' . $id;
}

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
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$csvParams = [
    'table' => $config['table'],
    'start' => $range['start'] ?? '',
    'end' => $range['end'] ?? '',
    'quick' => $range['quick'] ?? '',
];

function nav_url_for_table(string $key): string
{
    $params = $_GET;
    unset($params['msg']);
    $params['table'] = $key;
    return 'logs.php?' . http_build_query($params);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= h($config['label']) ?> Logs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="indexStyle.css">
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
            background:
                radial-gradient(circle at top left, rgba(0,255,255,.06), transparent 22%),
                radial-gradient(circle at top right, rgba(0,135,255,.08), transparent 24%),
                linear-gradient(180deg, var(--bg-1), #091726 40%, #0a1828 100%);
            color:var(--text);
            font-family: Arial, sans-serif;
            margin:0;
            padding:15px;
        }

        .logs-shell{
            max-width:1800px;
            margin:0 auto;
        }

        .logo-row{
            display:flex;
            justify-content:center;
            align-items:center;
            gap:20px;
            margin:6px 0 14px;
        }

        .logo-row img{
            height:110px;
            width:auto;
            max-width:100%;
            filter:drop-shadow(0 10px 28px rgba(0,0,0,.25));
        }

        .panel,
        .info-card{
            background:linear-gradient(180deg, rgba(18,44,68,.94), rgba(14,34,53,.96));
            border:1px solid var(--line-soft);
            border-radius:var(--radius);
            box-shadow:var(--glow);
            backdrop-filter: blur(8px);
        }

        .logs-hero{
            padding:16px;
            margin-bottom:14px;
            display:grid;
            grid-template-columns:minmax(280px, 1fr) auto;
            gap:16px;
            align-items:center;
        }

        .section-kicker{
            font-size:11px;
            letter-spacing:1.1px;
            text-transform:uppercase;
            color:#8abce6;
            margin-bottom:4px;
        }

        .logs-title{
            font-size:30px;
            line-height:1;
            margin:0 0 6px;
            font-weight:800;
        }

        .logs-subtitle{
            color:var(--muted);
            font-size:13px;
        }

        .logs-actions{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
            justify-content:flex-end;
        }

        .btn{
            background:linear-gradient(180deg, #1f4f74, #173d5b);
            border:1px solid rgba(255,255,255,.06);
            color:#fff;
            border-radius:10px;
            padding:9px 12px;
            cursor:pointer;
            font-size:12px;
            text-decoration:none;
            display:inline-block;
            line-height:1.2;
            width:auto;
            transition:transform .18s ease, background .18s ease, box-shadow .18s ease;
        }

        .btn:hover{
            background:linear-gradient(180deg, #255f8c, #1d4a6d);
            transform:translateY(-1px);
            box-shadow:0 6px 18px rgba(0,0,0,.18);
        }

        .btn.danger{
            background:linear-gradient(180deg, #b64242, #7b2828);
        }

        .btn.small{
            padding:6px 9px;
            font-size:11px;
        }

        .tabs-card{
            padding:10px;
            margin-bottom:14px;
        }

        .log-tabs{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        }

        .log-tab{
            text-decoration:none;
            color:#dcecff;
            background:rgba(255,255,255,.045);
            border:1px solid rgba(255,255,255,.07);
            border-radius:999px;
            padding:8px 12px;
            font-size:12px;
            font-weight:700;
            letter-spacing:.2px;
            text-transform:uppercase;
        }

        .log-tab.active{
            background:rgba(0,229,255,.15);
            border-color:rgba(0,229,255,.35);
            color:#bff7ff;
            box-shadow:0 0 18px rgba(0,229,255,.08);
        }

        .filter-card{
            padding:14px;
            margin-bottom:14px;
        }

        .filter-card .range-layout,
        .filter-card .list-range-layout{
            display:grid;
            grid-template-columns:minmax(0,1fr) auto;
            gap:12px;
            align-items:center;
        }

        .filter-card input[type="datetime-local"],
        .filter-card input,
        .filter-card select{
            background:#0a1a29;
            border:1px solid #2a5377;
            border-radius:10px;
            color:#fff;
            padding:8px 10px;
            box-sizing:border-box;
        }

        .filter-card .btn,
        .filter-card button{
            margin:0;
        }

        .range-active,
        .list-range-active{
            margin-top:8px;
            color:#9ed0f2;
            font-size:12px;
        }

        .range-error,
        .list-range-error{
            margin-top:8px;
            color:#ff9d9d;
            font-size:12px;
        }

        .notice-success,
        .notice-error{
            margin:12px 0;
            padding:10px 12px;
            border-radius:12px;
            border:1px solid rgba(255,255,255,.08);
        }

        .notice-success{
            background:rgba(40,167,69,.14);
            color:#94ffb0;
        }

        .notice-error{
            background:rgba(220,53,69,.16);
            color:#ffb1b1;
        }

        .table-panel{
            padding:14px;
            margin-bottom:22px;
        }

        .table-toolbar{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
            margin-bottom:10px;
        }

        .record-count{
            color:var(--muted);
            font-size:12px;
            text-transform:uppercase;
            letter-spacing:.7px;
        }

        .table-wrap{
            max-height:70vh;
            overflow:auto;
            border-radius:14px;
            border:1px solid rgba(255,255,255,.05);
            background:rgba(7,18,28,.42);
        }

        table{
            width:max-content;
            min-width:100%;
            border-collapse:collapse;
            table-layout:auto;
        }

        th,
        td{
            padding:8px 10px;
            font-size:12px;
            border-bottom:1px solid #1f4665;
            white-space:nowrap;
            text-align:left;
            vertical-align:middle;
        }

        th{
            background:#183a56;
            position:sticky;
            top:0;
            z-index:2;
            color:#dcecff;
        }

        tbody tr{
            transition:background-color .2s ease;
        }

        tbody tr:hover{
            background:rgba(255,255,255,.04);
        }

        .checkbox-col{
            width:44px;
            text-align:center;
        }

        .checkbox-cell{
            text-align:center;
        }

        .comment-cell{
            white-space:normal;
            min-width:220px;
            max-width:420px;
        }

        .actions-cell{
            display:flex;
            gap:6px;
            flex-wrap:wrap;
        }

        @media (max-width:900px){
            .logs-hero{
                grid-template-columns:1fr;
            }

            .logs-actions{
                justify-content:flex-start;
            }

            .filter-card .range-layout,
            .filter-card .list-range-layout{
                grid-template-columns:1fr;
                align-items:start;
            }
        }

        @media (max-width:700px){
            .logs-title{
                font-size:24px;
            }

            .logo-row img{
                height:78px;
            }
        }
    </style>
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
                <a class="btn" href="<?= h($config['add']) ?>">Add Record</a>
            <?php endif; ?>
            <a class="btn" href="csv_download.php?<?= h(http_build_query($csvParams)) ?>">Download CSV</a>
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
    </div>

    <?php if ($message !== ''): ?>
        <div class="notice-success"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" id="bulkDeleteForm">
        <input type="hidden" name="bulk_delete" value="1">

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
                                <tr>
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
    selectAllRows.addEventListener('change', function () {
        document.querySelectorAll('input[name="selected_ids[]"]').forEach(function (box) {
            box.checked = selectAllRows.checked;
        });
    });
}
</script>
</body>
</html>
