<?php
session_start();

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

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $latestNozzle = $pdo->query("SELECT * FROM nozzle_logs ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    $latestTricanter = $pdo->query("SELECT * FROM tricanter_logs ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];

    $nozzle = $pdo->query("SELECT * FROM nozzle_logs ORDER BY id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
    $tricanter = $pdo->query("SELECT * FROM tricanter_logs ORDER BY id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die("DB Error: " . h($e->getMessage()));
}

$nozzleFlowSeries = numeric_series($nozzle, 'flow');
$nozzlePressureSeries = numeric_series($nozzle, 'pressure');
$nozzleMinDegSeries = numeric_series($nozzle, 'min_deg');
$nozzleMaxDegSeries = numeric_series($nozzle, 'max_deg');
$nozzleRpmSeries = numeric_series($nozzle, 'rpm');

$tricanterBowlSpeedSeries = numeric_series($tricanter, 'bowl_speed');
$tricanterScrewSpeedSeries = numeric_series($tricanter, 'screw_speed');
$tricanterBowlRpmSeries = numeric_series($tricanter, 'bowl_rpm');
$tricanterScrewRpmSeries = numeric_series($tricanter, 'screw_rpm');
$tricanterImpellerSeries = numeric_series($tricanter, 'impeller');
$tricanterFeedRateSeries = numeric_series($tricanter, 'feed_rate');
$tricanterTorqueSeries = numeric_series($tricanter, 'torque');
$tricanterTempSeries = numeric_series($tricanter, 'temp');
$tricanterPressureSeries = numeric_series($tricanter, 'pressure');

$systemStatus = (!empty($latestNozzle) || !empty($latestTricanter)) ? 'ONLINE' : 'NO DATA';
$lastNozzleStamp = trim(($latestNozzle['log_date'] ?? '-') . ' ' . ($latestNozzle['log_time'] ?? ''));
$lastTricanterStamp = trim(($latestTricanter['log_date'] ?? '-') . ' ' . ($latestTricanter['log_time'] ?? ''));
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
    grid-template-columns: 1.3fr 1fr 1fr 1fr;
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

@media (max-width: 1200px) {
    .topbar { grid-template-columns: 1fr 1fr; }
    .grid { grid-template-columns: 1fr; }
}

@media (max-width: 700px) {
    .topbar, .kpis { grid-template-columns: 1fr; }
    .info-value { font-size: 20px; }
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
        <div class="info-value"><?= count($nozzle) + count($tricanter) ?></div>
        <div class="info-sub">30 nozzle + 30 tricanter max</div>
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

function makeCombinedChart(canvasId, datasets) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const valid = datasets.filter(ds => Array.isArray(ds.data) && ds.data.length > 0);
    if (valid.length === 0) return;

    const maxLen = Math.max(...valid.map(ds => ds.data.length));
    const labels = Array.from({ length: maxLen }, (_, i) => i + 1);

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
                tooltip: { enabled: true }
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

makeCombinedChart('nozzleCombinedChart', [
    { label: 'Flow', data: <?= json_encode($nozzleFlowSeries) ?>, color: '#00ffff', axis: 'y1' },
    { label: 'Pressure', data: <?= json_encode($nozzlePressureSeries) ?>, color: '#ffd24d', axis: 'y2' },
    { label: 'Min Deg', data: <?= json_encode($nozzleMinDegSeries) ?>, color: '#6ee7a1', axis: 'y3' },
    { label: 'Max Deg', data: <?= json_encode($nozzleMaxDegSeries) ?>, color: '#c8a7ff', axis: 'y4' },
    { label: 'RPM', data: <?= json_encode($nozzleRpmSeries) ?>, color: '#ff7e67', axis: 'y5' }
]);

makeCombinedChart('tricanterCombinedChart', [
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