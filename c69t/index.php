<?php
session_start();

date_default_timezone_set('Australia/Adelaide');

/* =========================
   PAGE PROTECTION
   ========================= */
$loginPage = 'login.php';

$allowedRoles = ['admin', 'operator', 'viewer'];

if (!isset($_SESSION['user_id'])) {
    header("Location: {$loginPage}");
    exit;
}

$currentRole = $_SESSION['role'] ?? '';

if (!in_array($currentRole, $allowedRoles, true)) {
    http_response_code(403);
    die('Access denied.');
}

/* =========================
   DB
   ========================= */
$host = "mariadb";
$dbname = "myapp";
$user = "zack";
$pass = "Butcher69";

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('fmt')) {
    function fmt($value, $decimals = 0) {
        if ($value === null || $value === '') return '-';
        if (!is_numeric($value)) return h($value);
        return number_format((float)$value, $decimals, '.', '');
    }
}

if (!function_exists('numeric_series')) {
    function numeric_series(array $rows, string $key): array {
        $out = [];
        foreach (array_reverse($rows) as $row) {
            if (isset($row[$key]) && $row[$key] !== '' && is_numeric($row[$key])) {
                $out[] = (float)$row[$key];
            }
        }
        return $out;
    }
}

if (!function_exists('label_series')) {
    function label_series(array $rows): array {
        $out = [];
        foreach (array_reverse($rows) as $row) {
            $out[] = trim(($row['log_date'] ?? '') . ' ' . ($row['log_time'] ?? ''));
        }
        return $out;
    }
}

if (!function_exists('to_datetime_local_value')) {
    function to_datetime_local_value(?string $value): string {
        if (!$value) return '';
        $ts = strtotime($value);
        if ($ts === false) return '';
        return date('Y-m-d\TH:i', $ts);
    }
}

/* =========================
   RANGE FILTER
   ========================= */
$rangeStart = trim($_GET['start'] ?? '');
$rangeEnd   = trim($_GET['end'] ?? '');
$quickRange = trim($_GET['quick'] ?? '');

if ($quickRange !== '') {
    $now = time();

    switch ($quickRange) {
        case 'today':
            $rangeStart = date('Y-m-d 00:00', $now);
            $rangeEnd   = date('Y-m-d H:i', $now);
            break;

        case '12h':
            $rangeStart = date('Y-m-d H:i', strtotime('-12 hours', $now));
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
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $baseNozzleSql = "SELECT * FROM nozzle_logs";
    $baseTricanterSql = "SELECT * FROM tricanter_logs";

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

    $rowLimit = $rangeActive ? 500 : 30;

    $stmt = $pdo->prepare($baseNozzleSql . $whereSql . " ORDER BY id DESC LIMIT " . (int)$rowLimit);
    $stmt->execute($params);
    $nozzle = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare($baseTricanterSql . $whereSql . " ORDER BY id DESC LIMIT " . (int)$rowLimit);
    $stmt->execute($params);
    $tricanter = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $latestNozzle = $nozzle[0] ?? [];
    $latestTricanter = $tricanter[0] ?? [];

} catch (Throwable $e) {
    die("DB Error: " . h($e->getMessage()));
}

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

$systemStatus = (!empty($latestNozzle) || !empty($latestTricanter)) ? 'ONLINE' : 'NO DATA';
$lastNozzleStamp = trim(($latestNozzle['log_date'] ?? '-') . ' ' . ($latestNozzle['log_time'] ?? ''));
$lastTricanterStamp = trim(($latestTricanter['log_date'] ?? '-') . ' ' . ($latestTricanter['log_time'] ?? ''));

$recordsLoaded = count($nozzle) + count($tricanter);
$rangeSummary = 'Latest ' . ($rangeActive ? 'filtered data' : '30 nozzle + 30 tricanter max');

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
    grid-template-columns: 1.2fr 1fr 1fr 1fr 2.2fr;
    gap: 12px;
    margin-bottom: 15px;
}

.info-card {
    background: #122c44;
    padding: 12px;
    border-radius: 10px;
    min-width: 0;
}

.info-title {
    font-size: 12px;
    color: #9ec3df;
    text-transform: uppercase;
    letter-spacing: .8px;
    margin-bottom: 6px;
}

.info-value {
    font-size: 24px;
    font-weight: bold;
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

.grid {
    display: grid;
    grid-template-columns: minmax(0,1fr) minmax(0,1fr);
    gap: 15px;
}

.panel {
    background: #122c44;
    padding: 10px;
    border-radius: 10px;
    min-width: 0;
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
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 6px;
    font-size: 11px;
    border-bottom: 1px solid #1f4a6e;
    white-space: nowrap;
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

.filter-form {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.filter-form label {
    font-size: 11px;
    color: #b7ccdd;
    display: block;
    margin-bottom: 4px;
}

.filter-form input[type="datetime-local"] {
    width: 100%;
    box-sizing: border-box;
    background: #0d2234;
    color: #fff;
    border: 1px solid #2a5377;
    border-radius: 6px;
    padding: 8px;
    font-size: 12px;
}

.filter-actions {
    grid-column: 1 / -1;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 2px;
}

.quick-actions {
    grid-column: 1 / -1;
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 2px;
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

@media (max-width: 1400px) {
    .topbar { grid-template-columns: 1fr 1fr; }
    .grid { grid-template-columns: 1fr; }
}

@media (max-width: 700px) {
    .topbar, .kpis, .filter-form { grid-template-columns: 1fr; }
    .info-value { font-size: 20px; }
    .filter-actions, .quick-actions { flex-direction: column; }
}
</style>
</head>
<body>
<?php require_once "nav.php"; ?>
<h1>SCADA Dashboard</h1>

<div class="topbar">
    <div class="info-card">
        <div class="info-title">System Status</div>
        <div class="info-value <?= $systemStatus === 'ONLINE' ? 'status-online' : 'status-offline' ?>"><?= h($systemStatus) ?></div>
        <div class="info-sub">Auto refresh every 30 seconds</div>
    </div>

    <div class="info-card">
        <div class="info-title">Latest Nozzle Log</div>
        <div class="info-value"><?= h($latestNozzle['id'] ?? '-') ?></div>
        <div class="info-sub"><?= h($lastNozzleStamp) ?></div>
    </div>

    <div class="info-card">
        <div class="info-title">Latest Tricanter Log</div>
        <div class="info-value"><?= h($latestTricanter['id'] ?? '-') ?></div>
        <div class="info-sub"><?= h($lastTricanterStamp) ?></div>
    </div>

    <div class="info-card">
        <div class="info-title">Records Loaded</div>
        <div class="info-value"><?= $recordsLoaded ?></div>
        <div class="info-sub"><?= h($rangeSummary) ?></div>
    </div>

    <div class="info-card">
        <div class="info-title">Date / Time Range</div>

        <form method="get" class="filter-form">
            <div>
                <label for="start">From</label>
                <input
                    type="datetime-local"
                    id="start"
                    name="start"
                    value="<?= h(to_datetime_local_value($rangeStart)) ?>"
                >
            </div>

            <div>
                <label for="end">To</label>
                <input
                    type="datetime-local"
                    id="end"
                    name="end"
                    value="<?= h(to_datetime_local_value($rangeEnd)) ?>"
                >
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn">Apply Range</button>
                <a href="<?= h($_SERVER['PHP_SELF']) ?>" class="btn">Clear</a>
            </div>

            <div class="quick-actions">
                <button type="submit" name="quick" value="today" class="btn btn-quick">Today</button>
                <button type="submit" name="quick" value="12h" class="btn btn-quick">Last 12 Hours</button>
                <button type="submit" name="quick" value="24h" class="btn btn-quick">Last 24 Hours</button>
                <button type="submit" name="quick" value="7d" class="btn btn-quick">Last 7 Days</button>
                <button type="submit" name="quick" value="clear" class="btn btn-quick">Clear</button>
            </div>
        </form>

        <?php if ($rangeError !== ''): ?>
            <div class="range-error"><?= h($rangeError) ?></div>
        <?php elseif ($rangeActive): ?>
            <div class="range-active">Filtering graphs and tables to selected range</div>
        <?php else: ?>
            <div class="range-active">Showing latest records</div>
        <?php endif; ?>
    </div>
</div>

<div class="grid">

<div class="panel">
    <h2>Nozzle</h2>

    <div class="kpis">
        <div class="kpi"><small>Flow</small><b><?= fmt($latestNozzle['flow'] ?? null, 1) ?></b></div>
        <div class="kpi"><small>Pressure</small><b><?= fmt($latestNozzle['pressure'] ?? null, 2) ?></b></div>
        <div class="kpi"><small>RPM</small><b><?= fmt($latestNozzle['rpm'] ?? null, 1) ?></b></div>
        <div class="kpi"><small>Min Deg</small><b><?= fmt($latestNozzle['min_deg'] ?? null, 0) ?></b></div>
        <div class="kpi"><small>Max Deg</small><b><?= fmt($latestNozzle['max_deg'] ?? null, 0) ?></b></div>
        <div class="kpi"><small>Nozzle</small><b><?= h($latestNozzle['nozzle'] ?? '-') ?></b></div>
    </div>

    <div class="chart-card">
        <div class="chart-title">Nozzle Trends</div>
        <div class="chart-wrap"><canvas id="nozzleCombinedChart"></canvas></div>
    </div>

    <div class="table">
        <table>
            <tr>
                <th>ID</th><th>Date</th><th>Time</th><th>Nozzle</th><th>Flow</th><th>Pressure</th><th>Min</th><th>Max</th><th>RPM</th>
            </tr>
            <?php if (!$nozzle): ?>
                <tr><td colspan="9">No nozzle data in selected range.</td></tr>
            <?php else: ?>
                <?php foreach ($nozzle as $r): ?>
                <tr class="nozzle-row" data-id="<?= (int)$r['id'] ?>">
                    <td><?= h($r['id']) ?></td>
                    <td><?= h($r['log_date']) ?></td>
                    <td><?= h($r['log_time']) ?></td>
                    <td><?= h($r['nozzle']) ?></td>
                    <td><?= fmt($r['flow'] ?? null, 1) ?></td>
                    <td><?= fmt($r['pressure'] ?? null, 2) ?></td>
                    <td><?= fmt($r['min_deg'] ?? null, 0) ?></td>
                    <td><?= fmt($r['max_deg'] ?? null, 0) ?></td>
                    <td><?= fmt($r['rpm'] ?? null, 1) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="panel">
    <h2>Tricanter</h2>

    <div class="kpis">
        <div class="kpi"><small>Bowl Speed</small><b><?= fmt($latestTricanter['bowl_speed'] ?? null, 0) ?></b></div>
        <div class="kpi"><small>Screw Speed</small><b><?= fmt($latestTricanter['screw_speed'] ?? null, 2) ?></b></div>
        <div class="kpi"><small>Bowl RPM</small><b><?= fmt($latestTricanter['bowl_rpm'] ?? null, 0) ?></b></div>
        <div class="kpi"><small>Screw RPM</small><b><?= fmt($latestTricanter['screw_rpm'] ?? null, 2) ?></b></div>
        <div class="kpi"><small>Impeller</small><b><?= fmt($latestTricanter['impeller'] ?? null, 0) ?></b></div>
        <div class="kpi"><small>Feed Rate</small><b><?= fmt($latestTricanter['feed_rate'] ?? null, 2) ?></b></div>
        <div class="kpi"><small>Torque</small><b><?= fmt($latestTricanter['torque'] ?? null, 1) ?></b></div>
        <div class="kpi"><small>Temp</small><b><?= fmt($latestTricanter['temp'] ?? null, 1) ?></b></div>
        <div class="kpi"><small>Pressure</small><b><?= fmt($latestTricanter['pressure'] ?? null, 3) ?></b></div>
    </div>

    <div class="chart-card">
        <div class="chart-title">Tricanter Trends</div>
        <div class="chart-wrap"><canvas id="tricanterCombinedChart"></canvas></div>
    </div>

    <div class="table">
        <table>
            <tr>
                <th>ID</th><th>Date</th><th>Time</th><th>Bowl Speed</th><th>Screw Speed</th><th>Bowl RPM</th><th>Screw RPM</th><th>Impeller</th><th>Feed</th><th>Torque</th><th>Temp</th><th>Pressure</th>
            </tr>
            <?php if (!$tricanter): ?>
                <tr><td colspan="12">No tricanter data in selected range.</td></tr>
            <?php else: ?>
                <?php foreach ($tricanter as $r): ?>
                <tr class="tri-row" data-id="<?= (int)$r['id'] ?>">
                    <td><?= h($r['id']) ?></td>
                    <td><?= h($r['log_date']) ?></td>
                    <td><?= h($r['log_time']) ?></td>
                    <td><?= fmt($r['bowl_speed'] ?? null, 0) ?></td>
                    <td><?= fmt($r['screw_speed'] ?? null, 2) ?></td>
                    <td><?= fmt($r['bowl_rpm'] ?? null, 0) ?></td>
                    <td><?= fmt($r['screw_rpm'] ?? null, 2) ?></td>
                    <td><?= fmt($r['impeller'] ?? null, 0) ?></td>
                    <td><?= fmt($r['feed_rate'] ?? null, 2) ?></td>
                    <td><?= fmt($r['torque'] ?? null, 1) ?></td>
                    <td><?= fmt($r['temp'] ?? null, 1) ?></td>
                    <td><?= fmt($r['pressure'] ?? null, 3) ?></td>
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
</script>

</body>
</html>