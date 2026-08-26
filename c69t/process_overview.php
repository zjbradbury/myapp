<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function latestProcessRow(PDO $pdo, string $table): ?array
{
    try {
        $row = $pdo->query("SELECT * FROM `{$table}` ORDER BY log_date DESC, log_time DESC LIMIT 1")->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function processTimestamp(?array $row): ?DateTimeImmutable
{
    if (!$row || empty($row['log_date']) || empty($row['log_time'])) return null;
    try {
        return new DateTimeImmutable($row['log_date'] . ' ' . $row['log_time'], new DateTimeZone('Australia/Adelaide'));
    } catch (Throwable $e) {
        return null;
    }
}

function pv(?array $row, string $key, int $decimals = 1, string $unit = ''): string
{
    $value = $row[$key] ?? null;
    if ($value === null || $value === '') return '—';
    if (!is_numeric($value)) return h($value);
    return number_format((float)$value, $decimals) . ($unit === '' ? '' : ' ' . $unit);
}

function processStatus(?array $row, string $key): array
{
    $value = $row[$key] ?? null;
    if ($value === null || $value === '' || !is_numeric($value)) return ['No data', 'unknown'];
    return match ((int)$value) {
        1 => ['Running', 'running'],
        2 => ['Fault', 'fault'],
        default => ['Stopped', 'stopped'],
    };
}

function statusLamp(array $status): string
{
    return '<span class="lamp ' . $status[1] . '"></span><span>' . h($status[0]) . '</span>';
}

$data = [
    'pump' => latestProcessRow($pdo, 'pump_values_logs'),
    'tricanter' => latestProcessRow($pdo, 'tricanter_logs'),
    'flow' => latestProcessRow($pdo, 'project_flow_logs'),
    'nitrogen' => latestProcessRow($pdo, 'nitrogen_logs'),
    'solid' => latestProcessRow($pdo, 'solid_waste_logs'),
    'waterPump' => latestProcessRow($pdo, 'recovered_water_pump_logs'),
    'oilPump' => latestProcessRow($pdo, 'recovered_oil_pump_logs'),
];

$timestamps = array_values(array_filter(array_map('processTimestamp', $data)));
$latest = $timestamps ? max($timestamps) : null;
$now = new DateTimeImmutable('now', new DateTimeZone('Australia/Adelaide'));
$age = $latest ? max(0, $now->getTimestamp() - $latest->getTimestamp()) : null;
$online = $age !== null && $age <= 600;
$updated = $latest ? $latest->format('d/m/Y g:i:s A') : 'No database data';

$sp1 = processStatus($data['pump'], 'suction_pump_1_status');
$sp2 = processStatus($data['pump'], 'suction_pump_2_status');
$sp3 = processStatus($data['pump'], 'suction_pump_3_status');
$feed = processStatus($data['pump'], 'feed_pump_status');
$boost = processStatus($data['pump'], 'booster_pump_status');
$tri = (isset($data['tricanter']['tricanter_status']) && (int)$data['tricanter']['tricanter_status'] === 5)
    ? ['Running', 'running']
    : ['Offline', 'stopped'];
$nitrogenActive = (int)($data['nitrogen']['nitrogen_active'] ?? 0) === 1;
$nitrogenTrip = (int)($data['nitrogen']['trip_status'] ?? 0) === 1;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <link rel="icon" href="c69t.ico" type="image/x-icon">
    <title>Process Overview</title>
    <link rel="stylesheet" href="scrollbar.css">
    <link rel="stylesheet" href="process_overview.css">
</head>
<body>
<main class="hmi-shell">
    <header class="hmi-header">
        <div class="brand"><span>C69T</span><div><h1>Process Overview</h1><p>Tricanter separation system</p></div></div>
        <div class="system-card <?= $online ? 'online' : 'offline' ?>">
            <span class="lamp <?= $online ? 'running' : 'fault' ?>"></span>
            <div><strong>System <?= $online ? 'online' : 'offline' ?></strong><small>Latest data <?= h($updated) ?></small></div>
        </div>
    </header>

    <section class="process-canvas" aria-label="Tricanter process flow">
        <div class="process-pipe main-pipe"></div>

        <article class="unit suction-one-unit pump-unit">
            <div class="unit-title">Suction pump 1</div>
            <div class="large-pump"><span class="pump-icon <?= $sp1[1] ?>"></span><div><?= statusLamp($sp1) ?></div></div>
        </article>

        <article class="unit suction-two-unit pump-unit">
            <div class="unit-title">Suction pump 2</div>
            <div class="large-pump"><span class="pump-icon <?= $sp2[1] ?>"></span><div><?= statusLamp($sp2) ?></div></div>
            <div class="readings two">
                <div><label>Feedback</label><output><?= pv($data['pump'], 'suction_pump_2_feedback', 1, '%') ?></output></div>
                <div><label>Speed out</label><output><?= pv($data['pump'], 'suction_pump_2_speed_out', 1, '%') ?></output></div>
                <div><label>Inlet</label><output><?= pv($data['pump'], 'suction_pump_2_inlet_pressure', 2, 'bar') ?></output></div>
                <div><label>Outlet</label><output><?= pv($data['pump'], 'suction_pump_2_outlet_pressure', 2, 'bar') ?></output></div>
            </div>
        </article>

        <article class="unit suction-three-unit pump-unit">
            <div class="unit-title">Suction pump 3</div>
            <div class="large-pump"><span class="pump-icon <?= $sp3[1] ?>"></span><div><?= statusLamp($sp3) ?></div></div>
        </article>

        <article class="unit feed-unit pump-unit">
            <div class="unit-title">Feed pump</div>
            <div class="large-pump"><span class="pump-icon <?= $feed[1] ?>"></span><div><?= statusLamp($feed) ?></div></div>
            <div class="readings two">
                <div><label>Feedback</label><output><?= pv($data['pump'], 'feed_pump_feedback', 1, '%') ?></output></div>
                <div><label>Speed out</label><output><?= pv($data['pump'], 'feed_pump_speed_out', 1, '%') ?></output></div>
                <div><label>Inlet</label><output><?= pv($data['pump'], 'feed_pump_inlet_pressure', 2, 'bar') ?></output></div>
                <div><label>Outlet</label><output><?= pv($data['pump'], 'feed_pump_outlet_pressure', 2, 'bar') ?></output></div>
            </div>
        </article>

        <article class="unit booster-unit pump-unit">
            <div class="unit-title">Booster pump</div>
            <div class="large-pump"><span class="pump-icon <?= $boost[1] ?>"></span><div><?= statusLamp($boost) ?></div></div>
            <div class="readings two">
                <div><label>Feedback</label><output><?= pv($data['pump'], 'booster_pump_feedback', 1, '%') ?></output></div>
                <div><label>Speed out</label><output><?= pv($data['pump'], 'booster_pump_speed_out', 1, '%') ?></output></div>
                <div><label>Inlet</label><output><?= pv($data['pump'], 'booster_pump_inlet_pressure', 2, 'bar') ?></output></div>
                <div><label>Outlet</label><output><?= pv($data['pump'], 'booster_pump_outlet_pressure', 2, 'bar') ?></output></div>
            </div>
        </article>

        <article class="unit tricanter-unit">
            <div class="unit-title"><span>Tricanter</span><span class="inline-status"><?= statusLamp($tri) ?></span></div>
            <div class="tricanter-machine" aria-hidden="true"><span class="motor"></span><span class="drum"></span><span class="screw"></span><span class="outlet water-outlet"></span><span class="outlet oil-outlet"></span></div>
            <div class="readings eight">
                <div><label>Bowl speed</label><output><?= pv($data['tricanter'], 'bowl_speed', 1, '%') ?></output></div>
                <div><label>Screw speed</label><output><?= pv($data['tricanter'], 'screw_speed', 1, '%') ?></output></div>
                <div><label>Bowl</label><output><?= pv($data['tricanter'], 'bowl_rpm', 0, 'RPM') ?></output></div>
                <div><label>Screw</label><output><?= pv($data['tricanter'], 'screw_rpm', 2, 'RPM') ?></output></div>
                <div><label>Torque</label><output><?= pv($data['tricanter'], 'torque', 1, '%') ?></output></div>
                <div><label>Feed rate</label><output><?= pv($data['tricanter'], 'feed_rate', 2, 'm³/hr') ?></output></div>
                <div><label>Temperature</label><output><?= pv($data['tricanter'], 'temp', 1, '°C') ?></output></div>
                <div><label>Pressure</label><output><?= pv($data['tricanter'], 'pressure', 2, 'bar') ?></output></div>
            </div>
        </article>

        <article class="unit outputs-unit">
            <div class="unit-title">Recovered products</div>
            <div class="vessels">
                <div class="product solid"><div class="vessel"><span class="material"></span><b><?= pv($data['solid'], 'amount', 1, 'kg') ?></b></div><h3>Solid waste</h3><p>Total <?= pv($data['flow'], 'total_solid_waste', 1, 'kg') ?></p></div>
                <div class="product water"><div class="vessel"><span class="material"></span><b><?= pv($data['flow'], 'total_recovered_water', 2, 'm³') ?></b></div><h3>Recovered water</h3><p>Pump <?= pv($data['waterPump'], 'start_level', 1, '%') ?> / <?= pv($data['waterPump'], 'stop_level', 1, '%') ?></p></div>
                <div class="product oil"><div class="vessel"><span class="material"></span><b><?= pv($data['flow'], 'total_recovered_oil', 2, 'm³') ?></b></div><h3>Recovered oil</h3><p>Project total</p></div>
            </div>
        </article>

        <aside class="side-column">
            <article class="side-panel overview-values">
                <div class="unit-title">Process totals</div>
                <div class="readings one">
                    <div><label>Tricanter total</label><output><?= pv($data['flow'], 'total_tricanter', 2, 'm³') ?></output></div>
                    <div><label>Nozzle total</label><output><?= pv($data['flow'], 'total_nozzle', 2, 'm³') ?></output></div>
                    <div><label>Tank internal O₂</label><output><?= pv($data['nitrogen'], 'tank_internal_o2', 1, '%') ?></output></div>
                </div>
            </article>
            <article class="side-panel nitrogen-panel">
                <div class="unit-title">Nitrogen generator</div>
                <div class="generator-state"><span class="lamp <?= $nitrogenActive ? 'running' : 'stopped' ?>"></span><b><?= $nitrogenActive ? 'Running' : 'Inactive' ?></b><small class="trip <?= $nitrogenTrip ? 'bad' : '' ?>"><?= $nitrogenTrip ? 'Trip active' : 'Trip OK' ?></small></div>
                <div class="readings one">
                    <div><label>Outlet flow</label><output><?= pv($data['nitrogen'], 'outlet_flow', 1, 'Nm³/hr') ?></output></div>
                    <div><label>Outlet purity (O₂)</label><output><?= pv($data['nitrogen'], 'outlet_purity', 1, '%') ?></output></div>
                    <div><label>Inlet pressure</label><output><?= pv($data['nitrogen'], 'inlet_pressure', 2, 'bar') ?></output></div>
                    <div><label>Outlet pressure</label><output><?= pv($data['nitrogen'], 'outlet_pressure', 2, 'bar') ?></output></div>
                    <div><label>Pre-heat temperature</label><output><?= pv($data['nitrogen'], 'pre_heat_temp', 1, '°C') ?></output></div>
                    <div><label>Post-heat temperature</label><output><?= pv($data['nitrogen'], 'post_heat_temp', 1, '°C') ?></output></div>
                    <div><label>Container interior O₂</label><output><?= pv($data['nitrogen'], 'interior_o2', 1, '%') ?></output></div>
                </div>
            </article>
        </aside>
    </section>
    <footer>Read-only process display · Values refresh from the latest database entries every 30 seconds</footer>
</main>
</body>
</html>
