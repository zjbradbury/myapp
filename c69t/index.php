<?php
require_once "config.php";
requireRole(['admin', 'operator', 'viewer']);

/* =========================
   RANGE FILTER
   ========================= */
$rangeStart = trim($_GET['start'] ?? '');
$rangeEnd   = trim($_GET['end'] ?? '');
$quickRange = trim($_GET['quick'] ?? '');

$usedDefaultShift = false;

if ($quickRange !== '') {
    $now = time();

    switch ($quickRange) {
        case 'current_shift':
            [$rangeStart, $rangeEnd] = get_current_shift_range($now);
            break;

        case 'today':
            $rangeStart = date('Y-m-d 00:00', $now);
            $rangeEnd   = date('Y-m-d H:i', $now);
            break;

        case '24h':
            $rangeStart = date('Y-m-d H:i', strtotime('-24 hours', $now));
            $rangeEnd   = date('Y-m-d H:i', $now);
            break;

        case '7d':
            $rangeStart = date('Y-m-d H:i', strtotime('-7 days', $now));
            $rangeEnd   = date('Y-m-d H:i', $now);
            break;

        case 'clear':
            $rangeStart = '';
            $rangeEnd = '';
            break;
    }
}

if ($rangeStart === '' && $rangeEnd === '' && $quickRange === '') {
    [$rangeStart, $rangeEnd] = get_current_shift_range();
    $usedDefaultShift = true;
}

$startSql = null;
$endSql = null;
$rangeError = '';

if ($rangeStart !== '') {
    $ts = strtotime($rangeStart);
    if ($ts === false) {
        $rangeError = 'Invalid start date/time.';
    } else {
        $startSql = date('Y-m-d H:i:s', $ts);
    }
}

if ($rangeEnd !== '') {
    $ts = strtotime($rangeEnd);
    if ($ts === false) {
        $rangeError = 'Invalid end date/time.';
    } else {
        $endSql = date('Y-m-d H:i:s', $ts);
    }
}

if ($rangeError === '' && $startSql !== null && $endSql !== null && strtotime($startSql) > strtotime($endSql)) {
    $rangeError = 'Start date/time must be earlier than end date/time.';
}

$rangeActive = ($rangeStart !== '' || $rangeEnd !== '');

try {
    $baseNozzleSql = "SELECT * FROM nozzle_logs";
    $baseTricanterSql = "SELECT * FROM tricanter_logs";
    $baseSolidWasteSql = "SELECT * FROM solid_waste_logs";

    $where = [];
    $params = [];

    if ($rangeError === '') {
        if ($startSql !== null) {
            $where[] = "TIMESTAMP(log_date, log_time) >= :start_dt";
            $params[':start_dt'] = $startSql;
        }

        if ($endSql !== null) {
            $where[] = "TIMESTAMP(log_date, log_time) <= :end_dt";
            $params[':end_dt'] = $endSql;
        }
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare($baseNozzleSql . $whereSql . " ORDER BY id DESC");
    $stmt->execute($params);
    $nozzle = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare($baseTricanterSql . $whereSql . " ORDER BY id DESC");
    $stmt->execute($params);
    $tricanter = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare($baseSolidWasteSql . $whereSql . " ORDER BY id DESC");
    $stmt->execute($params);
    $solidWaste = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT * FROM nozzle_logs ORDER BY id DESC LIMIT 1");
    $latestNozzleOverall = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->query("SELECT * FROM tricanter_logs ORDER BY id DESC LIMIT 1");
    $latestTricanterOverall = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->query("SELECT * FROM solid_waste_logs ORDER BY id DESC LIMIT 1");
    $latestSolidWasteOverall = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $latestNozzle = $nozzle[0] ?? [];
    $latestTricanter = $tricanter[0] ?? [];
    $latestSolidWaste = $solidWaste[0] ?? [];

} catch (Throwable $e) {
    die("DB Error: " . h($e->getMessage()));
}

$solidWaste = solid_diff_minutes_rows($solidWaste);
$latestSolidWaste = $solidWaste[0] ?? [];

$nozzleFlowSeries = numeric_series($nozzle, 'flow');
$nozzlePressureSeries = numeric_series($nozzle, 'pressure');
$nozzleMinDegSeries = numeric_series($nozzle, 'min_deg');
$nozzleMaxDegSeries = numeric_series($nozzle, 'max_deg');
$nozzleRpmSeries = numeric_series($nozzle, 'rpm');
$nozzleLabels = label_series($nozzle);

$tricanterBowlSpeedSeries = numeric_series($tricanter, 'bowl_speed');
$tricanterScrewSpeedSeries = numeric_series($tricanter, 'screw_speed');
$tricanterBowlRpmSeries = numeric_series($tricanter, 'bowl_rpm');
$tricanterScrewRpmSeries = numeric_series($tricanter, 'screw_rpm');
$tricanterImpellerSeries = numeric_series($tricanter, 'impeller');
$tricanterFeedRateSeries = numeric_series($tricanter, 'feed_rate');
$tricanterTorqueSeries = numeric_series($tricanter, 'torque');
$tricanterTempSeries = numeric_series($tricanter, 'temp');
$tricanterPressureSeries = numeric_series($tricanter, 'pressure');
$tricanterLabels = label_series($tricanter);

$solidWasteLabels = label_series($solidWaste);
$solidWasteAmountSeries = numeric_series($solidWaste, 'amount');
$solidWasteDiffSeries = solid_diff_series($solidWaste);

$solidWasteTotalAmount = 0.0;
foreach ($solidWaste as $r) {
    if (isset($r['amount']) && $r['amount'] !== '' && is_numeric($r['amount'])) {
        $solidWasteTotalAmount += (float)$r['amount'];
    }
}

$systemStatus = (!empty($latestNozzleOverall) || !empty($latestTricanterOverall) || !empty($latestSolidWasteOverall)) ? 'ONLINE' : 'NO DATA';

$lastNozzleStamp = trim(($latestNozzleOverall['log_date'] ?? '-') . ' ' . ($latestNozzleOverall['log_time'] ?? ''));
$lastTricanterStamp = trim(($latestTricanterOverall['log_date'] ?? '-') . ' ' . ($latestTricanterOverall['log_time'] ?? ''));
$lastSolidWasteStamp = trim(($latestSolidWasteOverall['log_date'] ?? '-') . ' ' . ($latestSolidWasteOverall['log_time'] ?? ''));

$recordsLoaded = count($nozzle) + count($tricanter) + count($solidWaste);
$rangeSummary = 'Current shift block';

if ($rangeStart !== '' || $rangeEnd !== '') {
    $fromText = $rangeStart !== '' ? date('d/m/Y H:i', strtotime($rangeStart)) : 'Beginning';
    $toText   = $rangeEnd !== '' ? date('d/m/Y H:i', strtotime($rangeEnd)) : 'Now';
    $rangeSummary = $fromText . ' → ' . $toText;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="30">
<title>SCADA Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
    background: #0b1e2d;
    color: #fff;
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 15px;
}

h1 {
    text-align: center;
    margin: 0 0 14px;
}

.topbar {
    display: grid;
    grid-template-columns: 1.35fr 0.8fr 2.2fr;
    gap: 12px;
    margin-bottom: 15px;
    align-items: stretch;
}

.info-card {
    background: #122c44;
    padding: 10px 12px;
    border-radius: 10px;
    min-width: 0;
}

.info-title {
    font-size: 11px;
    color: #9ec3df;
    text-transform: uppercase;
    letter-spacing: .8px;
    margin-bottom: 6px;
}

.info-value {
    font-size: 22px;
    font-weight: bold;
    line-height: 1.1;
}

.info-sub {
    font-size: 12px;
    color: #b7ccdd;
    margin-top: 4px;
}

.status-online {
    color: #7dffb2;
}

.status-offline {
    color: #ffd36d;
}

.status-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 2px;
}

.last-entry-inline {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
    text-align: right;
    min-width: 0;
}

.last-entry-heading {
    font-size: 10px;
    color: #9ec3df;
    text-transform: uppercase;
    letter-spacing: .7px;
}

.last-entry-value {
    font-size: 11px;
    color: #dcecff;
    line-height: 1.25;
    white-space: nowrap;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0,1fr));
    gap: 15px;
}

.panel {
    background: #122c44;
    padding: 10px;
    border-radius: 10px;
    min-width: 0;
}

.nozzle-panel {
    grid-column: 1 / -1;
}

.kpis {
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 8px;
    margin-bottom: 10px;
}

.kpi {
    background: #163a59;
    padding: 8px;
    border-radius: 6px;
    text-align: center;
}

.kpi small {
    color: #b9c7d4;
    display: block;
    margin-bottom: 4px;
}

.kpi b {
    font-size: 18px;
}

.chart-card {
    background: #10273c;
    border-radius: 8px;
    padding: 8px;
    margin-bottom: 10px;
}

.chart-title {
    font-size: 11px;
    color: #b9c7d4;
    margin-bottom: 6px;
}

.chart-wrap {
    position: relative;
    width: 100%;
    height: 220px;
    overflow: hidden;
}

.chart-wrap canvas {
    width: 100% !important;
    height: 100% !important;
    display: block;
}

.table {
    max-height: 320px;
    overflow: auto;
    border-radius: 8px;
}

table {
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
    table-layout: auto;
}

th, td {
    padding: 6px 10px;
    font-size: 11px;
    border-bottom: 1px solid #1f4a6e;
    white-space: nowrap;
    text-align: left;
    vertical-align: middle;
}

th {
    background: #1f4a6e;
    position: sticky;
    top: 0;
}

.flash {
    animation: flash 2s 3;
}

@keyframes flash {
    0% { background: yellow; color: black; }
    100% { background: inherit; color: inherit; }
}

.range-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: center;
}

.filter-form {
    display: contents;
}

.range-inputs {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.range-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.range-row label {
    width: 38px;
    min-width: 38px;
    margin-bottom: 0;
    font-size: 11px;
    color: #b7ccdd;
}

.filter-form input[type="datetime-local"] {
    width: 195px;
    max-width: 100%;
    box-sizing: border-box;
    background: #0d2234;
    color: #fff;
    border: 1px solid #2a5377;
    border-radius: 6px;
    padding: 7px 8px;
    font-size: 12px;
    height: 34px;
}

.range-buttons {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.filter-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.quick-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.btn {
    background: #1f4a6e;
    color: #fff;
    border: 0;
    border-radius: 6px;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 12px;
    text-decoration: none;
    display: inline-block;
    line-height: 1.2;
}

.btn:hover {
    background: #295d89;
}

.btn-quick {
    background: #163a59;
    padding: 7px 10px;
    font-size: 11px;
}

.btn-quick:hover {
    background: #214d74;
}

.range-error {
    margin-top: 8px;
    color: #ff9d9d;
    font-size: 12px;
}

.range-active {
    margin-top: 8px;
    color: #9ed0f2;
    font-size: 12px;
}

@media (max-width: 1600px) {
    .grid { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 1400px) {
    .topbar { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 900px) {
    .range-layout {
        grid-template-columns: 1fr;
        align-items: start;
    }

    .range-buttons,
    .filter-actions,
    .quick-actions {
        align-items: flex-start;
        justify-content: flex-start;
    }
}

@media (max-width: 700px) {
    .topbar, .kpis, .grid {
        grid-template-columns: 1fr;
    }

    .nozzle-panel {
        grid-column: auto;
    }

    .info-value {
        font-size: 20px;
    }

    .status-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .last-entry-inline {
        align-items: flex-start;
        text-align: left;
    }

    .range-row {
        flex-wrap: wrap;
    }

    .filter-form input[type="datetime-local"] {
        width: 100%;
    }
}
</style>
</head>
<body>
<?php require_once "nav.php"; ?>
<h1>SCADA Dashboard</h1>

<div class="topbar">
    <div class="info-card">
        <div class="info-title">System Status</div>
        <div class="status-row">
            <div class="info-value <?= $systemStatus === 'ONLINE' ? 'status-online' : 'status-offline' ?>"><?= h($systemStatus) ?></div>

            <div class="last-entry-inline">
                <div class="last-entry-heading">Last Entry</div>
                <div class="last-entry-value">Nozzle: <?= h($lastNozzleStamp) ?></div>
                <div class="last-entry-value">Tricanter: <?= h($lastTricanterStamp) ?></div>
                <div class="last-entry-value">Solid Waste: <?= h($lastSolidWasteStamp) ?></div>
            </div>
        </div>
        <div class="info-sub">Auto refresh every 30 seconds</div>
    </div>

    <div class="info-card">
        <div class="info-title">Records Loaded</div>
        <div class="info-value"><?= $recordsLoaded ?></div>
        <div class="info-sub"><?= h($rangeSummary) ?></div>
    </div>

    <div class="info-card">
        <div class="info-title">Date / Time Range</div>

        <form method="get" class="filter-form">
            <div class="range-layout">
                <div class="range-inputs">
                    <div class="range-row">
                        <label for="start">From</label>
                        <input
                            type="datetime-local"
                            id="start"
                            name="start"
                            value="<?= h(to_datetime_local_value($rangeStart)) ?>"
                        >
                    </div>

                    <div class="range-row">
                        <label for="end">To</label>
                        <input
                            type="datetime-local"
                            id="end"
                            name="end"
                            value="<?= h(to_datetime_local_value($rangeEnd)) ?>"
                        >
                    </div>
                </div>

                <div class="range-buttons">
                    <div class="filter-actions">
                        <button type="submit" class="btn">Apply Range</button>
                        <a href="<?= h($_SERVER['PHP_SELF']) ?>" class="btn">Clear</a>
                    </div>

                    <div class="quick-actions">
                        <button type="submit" name="quick" value="current_shift" class="btn btn-quick">Current Shift</button>
                        <button type="submit" name="quick" value="today" class="btn btn-quick">Today</button>
                        <button type="submit" name="quick" value="24h" class="btn btn-quick">Last 24 Hours</button>
                        <button type="submit" name="quick" value="7d" class="btn btn-quick">Last 7 Days</button>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($rangeError !== ''): ?>
            <div class="range-error"><?= h($rangeError) ?></div>
        <?php elseif ($rangeActive && $usedDefaultShift): ?>
            <div class="range-active">Showing current 12 hour shift block</div>
        <?php elseif ($rangeActive): ?>
            <div class="range-active">Filtering graphs and tables to selected range</div>
        <?php else: ?>
            <div class="range-active">Showing all available records</div>
        <?php endif; ?>
    </div>
</div>

<div class="grid">

<div class="panel">
    <h2>Tricanter</h2>

    <div class="kpis">
        <div class="kpi"><small>Bowl Speed</small><b><?= fmt($latestTricanter['bowl_speed'] ?? null, 0) ?> %</b></div>
        <div class="kpi"><small>Screw Speed</small><b><?= fmt($latestTricanter['screw_speed'] ?? null, 2) ?> %</b></div>
        <div class="kpi"><small>Bowl RPM</small><b><?= fmt($latestTricanter['bowl_rpm'] ?? null, 0) ?> RPM</b></div>
        <div class="kpi"><small>Screw RPM</small><b><?= fmt($latestTricanter['screw_rpm'] ?? null, 2) ?> RPM</b></div>
        <div class="kpi"><small>Impeller</small><b><?= fmt($latestTricanter['impeller'] ?? null, 0) ?></b></div>
        <div class="kpi"><small>Feed Rate</small><b><?= fmt($latestTricanter['feed_rate'] ?? null, 2) ?> M3/hr</b></div>
        <div class="kpi"><small>Torque</small><b><?= fmt($latestTricanter['torque'] ?? null, 1) ?> %</b></div>
        <div class="kpi"><small>Temp</small><b><?= fmt($latestTricanter['temp'] ?? null, 1) ?> °C</b></div>
        <div class="kpi"><small>Pressure</small><b><?= fmt($latestTricanter['pressure'] ?? null, 3) ?> BAR</b></div>
    </div>

    <div class="chart-card">
        <div class="chart-title">Tricanter Trends</div>
        <div class="chart-wrap"><canvas id="tricanterCombinedChart"></canvas></div>
    </div>

    <div class="table">
        <table>
            <tr>
                <th>Date</th><th>Time</th><th>Bowl Speed</th><th>Screw Speed</th><th>Bowl RPM</th><th>Screw RPM</th><th>Impeller</th><th>Feed</th><th>Torque</th><th>Temp</th><th>Pressure</th>
            </tr>
            <?php if (!$tricanter): ?>
                <tr><td colspan="11">No tricanter data in selected range.</td></tr>
            <?php else: ?>
                <?php foreach ($tricanter as $r): ?>
                <tr class="tri-row" data-id="<?= (int)$r['id'] ?>">
                    <td><?= h($r['log_date']) ?></td>
                    <td><?= h($r['log_time']) ?></td>
                    <td><?= fmt($r['bowl_speed'] ?? null, 0) ?> %</td>
                    <td><?= fmt($r['screw_speed'] ?? null, 2) ?> %</td>
                    <td><?= fmt($r['bowl_rpm'] ?? null, 0) ?> RPM</td>
                    <td><?= fmt($r['screw_rpm'] ?? null, 2) ?> RPM</td>
                    <td><?= fmt($r['impeller'] ?? null, 0) ?></td>
                    <td><?= fmt($r['feed_rate'] ?? null, 2) ?> M3/hr</td>
                    <td><?= fmt($r['torque'] ?? null, 1) ?> %</td>
                    <td><?= fmt($r['temp'] ?? null, 1) ?> °C</td>
                    <td><?= fmt($r['pressure'] ?? null, 3) ?> BAR</td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="panel">
    <h2>Solid Waste</h2>

    <div class="kpis">
        <div class="kpi"><small>Latest Amount</small><b><?= fmt($latestSolidWaste['amount'] ?? null, 2) ?> KG</b></div>
        <div class="kpi"><small>Total Amount</small><b><?= fmt($solidWasteTotalAmount, 2) ?> KG</b></div>
        <div class="kpi"><small>Last Time</small><b><?= !empty($latestSolidWaste['log_time']) ? h(date('H:i', strtotime($latestSolidWaste['log_time']))) : '-' ?></b></div>
    </div>

    <div class="chart-card">
        <div class="chart-title">Solid Waste Trends</div>
        <div class="chart-wrap"><canvas id="solidWasteCombinedChart"></canvas></div>
    </div>

    <div class="table">
        <table>
            <tr>
                <th>Date</th><th>Time</th><th>Amount</th><th>Diff (min)</th><th>Comments</th>
            </tr>
            <?php if (!$solidWaste): ?>
                <tr><td colspan="5">No solid waste data in selected range.</td></tr>
            <?php else: ?>
                <?php foreach ($solidWaste as $r): ?>
                <tr class="solid-row" data-id="<?= (int)$r['id'] ?>">
                    <td><?= h($r['log_date']) ?></td>
                    <td><?= h($r['log_time']) ?></td>
                    <td><?= fmt($r['amount'] ?? null, 2) ?> KG</td>
                    <td><?= isset($r['_diff_minutes']) && $r['_diff_minutes'] !== null ? fmt($r['_diff_minutes'], 2) : '-' ?></td>
                    <td><?= h($r['comments'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="panel nozzle-panel">
    <h2>Nozzle</h2>

    <div class="kpis">
        <div class="kpi"><small>Flow</small><b><?= fmt($latestNozzle['flow'] ?? null, 1) ?> M3/hr</b></div>
        <div class="kpi"><small>Pressure</small><b><?= fmt($latestNozzle['pressure'] ?? null, 2) ?> BAR</b></div>
        <div class="kpi"><small>RPM</small><b><?= fmt($latestNozzle['rpm'] ?? null, 1) ?> RPM</b></div>
        <div class="kpi"><small>Min Deg</small><b><?= fmt($latestNozzle['min_deg'] ?? null, 0) ?> °</b></div>
        <div class="kpi"><small>Max Deg</small><b><?= fmt($latestNozzle['max_deg'] ?? null, 0) ?> °</b></div>
        <div class="kpi"><small>Nozzle</small><b>N<?= h($latestNozzle['nozzle'] ?? '-') ?></b></div>
    </div>

    <div class="chart-card">
        <div class="chart-title">Nozzle Trends</div>
        <div class="chart-wrap"><canvas id="nozzleCombinedChart"></canvas></div>
    </div>

    <div class="table">
        <table>
            <tr>
                <th>Date</th><th>Time</th><th>Nozzle</th><th>Flow</th><th>Pressure</th><th>Min</th><th>Max</th><th>RPM</th>
            </tr>
            <?php if (!$nozzle): ?>
                <tr><td colspan="8">No nozzle data in selected range.</td></tr>
            <?php else: ?>
                <?php foreach ($nozzle as $r): ?>
                <tr class="nozzle-row" data-id="<?= (int)$r['id'] ?>">
                    <td><?= h($r['log_date']) ?></td>
                    <td><?= h($r['log_time']) ?></td>
                    <td>N<?= h($r['nozzle']) ?></td>
                    <td><?= fmt($r['flow'] ?? null, 1) ?> M3/hr</td>
                    <td><?= fmt($r['pressure'] ?? null, 2) ?> BAR</td>
                    <td><?= fmt($r['min_deg'] ?? null, 0) ?> °</td>
                    <td><?= fmt($r['max_deg'] ?? null, 0) ?> °</td>
                    <td><?= fmt($r['rpm'] ?? null, 1) ?> RPM</td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</div>

</div>

<script>
function flashRows(selector, storageKey) {
    let last = parseInt(localStorage.getItem(storageKey) || '0', 10);
    let max = last;

    document.querySelectorAll(selector).forEach(row => {
        const id = parseInt(row.dataset.id || '0', 10);
        if (id > last) row.classList.add('flash');
        if (id > max) max = id;
    });

    localStorage.setItem(storageKey, String(max));
}

flashRows('.nozzle-row', 'nLast');
flashRows('.tri-row', 'tLast');
flashRows('.solid-row', 'sLast');

function makeCombinedChart(canvasId, labels, datasets) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const valid = datasets.filter(ds => Array.isArray(ds.data) && ds.data.length > 0);
    if (valid.length === 0) return;

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: valid.map(ds => ({
                label: ds.label,
                data: ds.data,
                borderColor: ds.color,
                backgroundColor: 'transparent',
                borderWidth: 2,
                tension: 0.25,
                pointRadius: 0,
                spanGaps: true,
                yAxisID: ds.axis
            }))
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: '#dcecff',
                        boxWidth: 10,
                        padding: 10,
                        font: { size: 11 }
                    }
                },
                tooltip: {
                    enabled: true,
                    callbacks: {
                        title: function(context) {
                            return context[0]?.label || '';
                        }
                    }
                }
            },
            scales: {
                x: { display: false },
                y1: { display: false },
                y2: { display: false },
                y3: { display: false },
                y4: { display: false },
                y5: { display: false },
                y6: { display: false },
                y7: { display: false },
                y8: { display: false },
                y9: { display: false }
            }
        }
    });
}

makeCombinedChart('nozzleCombinedChart', <?= json_encode($nozzleLabels) ?>, [
    { label: 'Flow', data: <?= json_encode($nozzleFlowSeries) ?>, color: '#00ffff', axis: 'y1' },
    { label: 'Pressure', data: <?= json_encode($nozzlePressureSeries) ?>, color: '#ffd24d', axis: 'y2' },
    { label: 'Min Deg', data: <?= json_encode($nozzleMinDegSeries) ?>, color: '#6ee7a1', axis: 'y3' },
    { label: 'Max Deg', data: <?= json_encode($nozzleMaxDegSeries) ?>, color: '#c8a7ff', axis: 'y4' },
    { label: 'RPM', data: <?= json_encode($nozzleRpmSeries) ?>, color: '#ff7e67', axis: 'y5' }
]);

makeCombinedChart('tricanterCombinedChart', <?= json_encode($tricanterLabels) ?>, [
    { label: 'Bowl Speed', data: <?= json_encode($tricanterBowlSpeedSeries) ?>, color: '#00ffff', axis: 'y1' },
    { label: 'Screw Speed', data: <?= json_encode($tricanterScrewSpeedSeries) ?>, color: '#ffd24d', axis: 'y2' },
    { label: 'Bowl RPM', data: <?= json_encode($tricanterBowlRpmSeries) ?>, color: '#c8a7ff', axis: 'y3' },
    { label: 'Screw RPM', data: <?= json_encode($tricanterScrewRpmSeries) ?>, color: '#ff9bd6', axis: 'y4' },
    { label: 'Impeller', data: <?= json_encode($tricanterImpellerSeries) ?>, color: '#b6ff7a', axis: 'y5' },
    { label: 'Feed Rate', data: <?= json_encode($tricanterFeedRateSeries) ?>, color: '#00ff88', axis: 'y6' },
    { label: 'Torque', data: <?= json_encode($tricanterTorqueSeries) ?>, color: '#ff7e67', axis: 'y7' },
    { label: 'Temp', data: <?= json_encode($tricanterTempSeries) ?>, color: '#ffb36b', axis: 'y8' },
    { label: 'Pressure', data: <?= json_encode($tricanterPressureSeries) ?>, color: '#8fd3ff', axis: 'y9' }
]);

makeCombinedChart('solidWasteCombinedChart', <?= json_encode($solidWasteLabels) ?>, [
    { label: 'Amount', data: <?= json_encode($solidWasteAmountSeries) ?>, color: '#00ff88', axis: 'y1' },
    { label: 'Diff (min)', data: <?= json_encode($solidWasteDiffSeries) ?>, color: '#ffd24d', axis: 'y2' }
]);
</script>

</body>
</html>