<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

date_default_timezone_set('Australia/Adelaide');

// Support the common config.php patterns used by the site.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('getPDO')) {
        $pdo = getPDO();
    } elseif (function_exists('db')) {
        $pdo = db();
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection was not found. Expected $pdo, getPDO(), or db() from config.php.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function latestRow(PDO $pdo, string $table): ?array
{
    // Table names are hard-coded by this page and never come from user input.
    $sql = "SELECT * FROM `{$table}` ORDER BY log_date DESC, log_time DESC LIMIT 1";
    try {
        $row = $pdo->query($sql)->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function rowTimestamp(?array $row): ?DateTimeImmutable
{
    if (!$row || empty($row['log_date']) || empty($row['log_time'])) {
        return null;
    }

    try {
        return new DateTimeImmutable($row['log_date'] . ' ' . $row['log_time'], new DateTimeZone('Australia/Adelaide'));
    } catch (Throwable $e) {
        return null;
    }
}

function displayValue(?array $row, string $key, int $decimals = 1, string $suffix = ''): string
{
    if (!$row || !array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
        return '—';
    }

    if (is_numeric($row[$key])) {
        return number_format((float)$row[$key], $decimals) . $suffix;
    }

    return htmlspecialchars((string)$row[$key], ENT_QUOTES, 'UTF-8');
}

function pumpStatus(?array $row, string $key): array
{
    if (!$row || !isset($row[$key]) || !is_numeric($row[$key])) {
        return ['label' => 'NO DATA', 'class' => 'unknown'];
    }

    return match ((int)$row[$key]) {
        1 => ['label' => 'RUNNING', 'class' => 'running'],
        2 => ['label' => 'ERROR', 'class' => 'error'],
        default => ['label' => 'OFF', 'class' => 'off'],
    };
}

$rows = [
    'pump'       => latestRow($pdo, 'pump_values_logs'),
    'tricanter'  => latestRow($pdo, 'tricanter_logs'),
    'flow'       => latestRow($pdo, 'project_flow_logs'),
    'nitrogen'   => latestRow($pdo, 'nitrogen_logs'),
    'solid'      => latestRow($pdo, 'solid_waste_logs'),
];

$latestTimestamp = null;
foreach ($rows as $row) {
    $ts = rowTimestamp($row);
    if ($ts && (!$latestTimestamp || $ts > $latestTimestamp)) {
        $latestTimestamp = $ts;
    }
}

$now = new DateTimeImmutable('now', new DateTimeZone('Australia/Adelaide'));
$ageSeconds = $latestTimestamp ? max(0, $now->getTimestamp() - $latestTimestamp->getTimestamp()) : null;
$systemOnline = $ageSeconds !== null && $ageSeconds <= 600;

$sp1 = pumpStatus($rows['pump'], 'suction_pump_1_status');
$sp2 = pumpStatus($rows['pump'], 'suction_pump_2_status');
$sp3 = pumpStatus($rows['pump'], 'suction_pump_3_status');
$fp  = pumpStatus($rows['pump'], 'feed_pump_status');
$bp  = pumpStatus($rows['pump'], 'booster_pump_status');

$lastUpdated = $latestTimestamp ? $latestTimestamp->format('d/m/Y H:i:s') : 'No data';
$ageText = $ageSeconds === null ? 'No database entries found' : ($ageSeconds < 60 ? $ageSeconds . ' sec ago' : floor($ageSeconds / 60) . ' min ago');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <title>Process Overview</title>
    <link rel="stylesheet" href="process_overview.css">
</head>
<body>
<main class="overview-shell">
    <header class="overview-header">
        <div>
            <p class="eyebrow">Contract 69 Tanks</p>
            <h1>Process Overview</h1>
        </div>
        <div class="system-state <?= $systemOnline ? 'online' : 'offline' ?>">
            <span class="state-dot"></span>
            <div>
                <strong>SYSTEM <?= $systemOnline ? 'ONLINE' : 'OFFLINE' ?></strong>
                <small>Last entry: <?= htmlspecialchars($lastUpdated) ?> · <?= htmlspecialchars($ageText) ?></small>
            </div>
        </div>
    </header>

    <section class="process-grid">
        <article class="panel suction-panel">
            <div class="panel-title">Tank Suction</div>
            <div class="pump-row">
                <div class="pump-card">
                    <div class="pump-heading"><span class="status-dot <?= $sp1['class'] ?>"></span> Suction Pump 1</div>
                    <div class="status-text <?= $sp1['class'] ?>"><?= $sp1['label'] ?></div>
                </div>
                <div class="pump-card">
                    <div class="pump-heading"><span class="status-dot <?= $sp2['class'] ?>"></span> Suction Pump 2</div>
                    <div class="status-text <?= $sp2['class'] ?>"><?= $sp2['label'] ?></div>
                    <div class="value-grid two">
                        <div><span>Speed</span><b><?= displayValue($rows['pump'], 'suction_pump_2_speed_out', 1, ' %') ?></b></div>
                        <div><span>Feedback</span><b><?= displayValue($rows['pump'], 'suction_pump_2_feedback', 1, ' %') ?></b></div>
                        <div><span>Inlet</span><b><?= displayValue($rows['pump'], 'suction_pump_2_inlet_pressure', 2, ' bar') ?></b></div>
                        <div><span>Outlet</span><b><?= displayValue($rows['pump'], 'suction_pump_2_outlet_pressure', 2, ' bar') ?></b></div>
                    </div>
                </div>
                <div class="pump-card">
                    <div class="pump-heading"><span class="status-dot <?= $sp3['class'] ?>"></span> Suction Pump 3</div>
                    <div class="status-text <?= $sp3['class'] ?>"><?= $sp3['label'] ?></div>
                </div>
            </div>
        </article>

        <div class="flow-line horizontal line-a"></div>

        <article class="panel feed-panel">
            <div class="panel-title">Feed Pump</div>
            <div class="pump-heading"><span class="status-dot <?= $fp['class'] ?>"></span> <?= $fp['label'] ?></div>
            <div class="value-grid two">
                <div><span>Speed</span><b><?= displayValue($rows['pump'], 'feed_pump_speed_out', 1, ' %') ?></b></div>
                <div><span>Feedback</span><b><?= displayValue($rows['pump'], 'feed_pump_feedback', 1, ' %') ?></b></div>
                <div><span>Inlet</span><b><?= displayValue($rows['pump'], 'feed_pump_inlet_pressure', 2, ' bar') ?></b></div>
                <div><span>Outlet</span><b><?= displayValue($rows['pump'], 'feed_pump_outlet_pressure', 2, ' bar') ?></b></div>
            </div>
        </article>

        <div class="flow-line horizontal line-b"></div>

        <article class="panel booster-panel">
            <div class="panel-title">Booster Pump</div>
            <div class="pump-heading"><span class="status-dot <?= $bp['class'] ?>"></span> <?= $bp['label'] ?></div>
            <div class="value-grid two">
                <div><span>Speed</span><b><?= displayValue($rows['pump'], 'booster_pump_speed_out', 1, ' %') ?></b></div>
                <div><span>Feedback</span><b><?= displayValue($rows['pump'], 'booster_pump_feedback', 1, ' %') ?></b></div>
                <div><span>Inlet</span><b><?= displayValue($rows['pump'], 'booster_pump_inlet_pressure', 2, ' bar') ?></b></div>
                <div><span>Outlet</span><b><?= displayValue($rows['pump'], 'booster_pump_outlet_pressure', 2, ' bar') ?></b></div>
            </div>
        </article>

        <article class="panel tricanter-panel equipment-panel">
            <div class="panel-title">Tricanter</div>
            <div class="equipment-art tricanter-art"><span></span></div>
            <div class="value-grid four">
                <div><span>Bowl speed</span><b><?= displayValue($rows['tricanter'], 'bowl_speed', 1, ' %') ?></b></div>
                <div><span>Screw speed</span><b><?= displayValue($rows['tricanter'], 'screw_speed', 1, ' %') ?></b></div>
                <div><span>Bowl RPM</span><b><?= displayValue($rows['tricanter'], 'bowl_rpm', 0, ' RPM') ?></b></div>
                <div><span>Screw RPM</span><b><?= displayValue($rows['tricanter'], 'screw_rpm', 2, ' RPM') ?></b></div>
                <div><span>Torque</span><b><?= displayValue($rows['tricanter'], 'torque', 1, ' %') ?></b></div>
                <div><span>Feed rate</span><b><?= displayValue($rows['tricanter'], 'feed_rate', 2, ' m³/hr') ?></b></div>
                <div><span>Temperature</span><b><?= displayValue($rows['tricanter'], 'temp', 1, ' °C') ?></b></div>
                <div><span>Pressure</span><b><?= displayValue($rows['tricanter'], 'pressure', 2, ' bar') ?></b></div>
            </div>
        </article>

        <article class="panel flow-panel">
            <div class="panel-title">Project Flow</div>
            <div class="value-grid two">
                <div><span>Flow rate</span><b><?= displayValue($rows['flow'], 'flow_rate', 2, ' m³/hr') ?></b></div>
                <div><span>Nozzle</span><b><?= displayValue($rows['flow'], 'nozzle', 0) ?></b></div>
                <div><span>Nozzle vertical</span><b><?= displayValue($rows['flow'], 'nozzle_vertical_deg', 1, '°') ?></b></div>
                <div><span>Recovered water</span><b><?= displayValue($rows['flow'], 'recovered_water_level', 1, ' %') ?></b></div>
                <div><span>Recovered oil</span><b><?= displayValue($rows['flow'], 'recovered_oil_level', 1, ' %') ?></b></div>
                <div><span>Solid waste</span><b><?= displayValue($rows['flow'], 'solid_waste_level', 1, ' %') ?></b></div>
            </div>
        </article>

        <article class="panel outputs-panel">
            <div class="panel-title">Recovered Products</div>
            <div class="tank-row">
                <div class="tank-card solids">
                    <div class="tank-vessel"><div class="tank-fill" style="height: <?= max(0, min(100, (float)($rows['flow']['solid_waste_level'] ?? 0))) ?>%"></div><span><?= displayValue($rows['flow'], 'solid_waste_level', 0, '%') ?></span></div>
                    <strong>Solid Waste</strong>
                    <small>Latest amount: <?= displayValue($rows['solid'], 'amount', 1, ' kg') ?></small>
                </div>
                <div class="tank-card water">
                    <div class="tank-vessel"><div class="tank-fill" style="height: <?= max(0, min(100, (float)($rows['flow']['recovered_water_level'] ?? 0))) ?>%"></div><span><?= displayValue($rows['flow'], 'recovered_water_level', 0, '%') ?></span></div>
                    <strong>Recovered Water</strong>
                </div>
                <div class="tank-card oil">
                    <div class="tank-vessel"><div class="tank-fill" style="height: <?= max(0, min(100, (float)($rows['flow']['recovered_oil_level'] ?? 0))) ?>%"></div><span><?= displayValue($rows['flow'], 'recovered_oil_level', 0, '%') ?></span></div>
                    <strong>Recovered Oil</strong>
                </div>
            </div>
        </article>

        <article class="panel nitrogen-panel">
            <div class="panel-title">Nitrogen Generator</div>
            <div class="nitrogen-status">
                <span class="status-dot <?= ((int)($rows['nitrogen']['nitrogen_active'] ?? 0) === 1) ? 'running' : 'off' ?>"></span>
                <?= ((int)($rows['nitrogen']['nitrogen_active'] ?? 0) === 1) ? 'ACTIVE' : 'INACTIVE' ?>
            </div>
            <div class="value-grid two">
                <div><span>Outlet flow</span><b><?= displayValue($rows['nitrogen'], 'outlet_flow', 1, ' Nm³/hr') ?></b></div>
                <div><span>Purity O₂</span><b><?= displayValue($rows['nitrogen'], 'outlet_purity', 2, ' %') ?></b></div>
                <div><span>Inlet pressure</span><b><?= displayValue($rows['nitrogen'], 'inlet_pressure', 2, ' bar') ?></b></div>
                <div><span>Outlet pressure</span><b><?= displayValue($rows['nitrogen'], 'outlet_pressure', 2, ' bar') ?></b></div>
                <div><span>Pre-heat</span><b><?= displayValue($rows['nitrogen'], 'pre_heat_temp', 1, ' °C') ?></b></div>
                <div><span>Post-heat</span><b><?= displayValue($rows['nitrogen'], 'post_heat_temp', 1, ' °C') ?></b></div>
                <div><span>Interior O₂</span><b><?= displayValue($rows['nitrogen'], 'interior_o2', 2, ' %') ?></b></div>
                <div><span>Trip status</span><b><?= htmlspecialchars((string)($rows['nitrogen']['trip_status'] ?? '—')) ?></b></div>
            </div>
        </article>
    </section>

    <footer class="overview-footer">
        Values refresh every 30 seconds. System is offline when the newest database entry is more than 10 minutes old.
    </footer>
</main>
</body>
</html>
