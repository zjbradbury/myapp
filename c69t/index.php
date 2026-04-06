<?php
session_start();

require_once "nav.php";

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
}

.info-title {
    font-size: 12px;
    color: #9ec3df;
    text-transform: uppercase;
    margin-bottom: 6px;
}

.info-value {
    font-size: 24px;
    font-weight: bold;
}

.info-sub {
    font-size: 12px;
    color: #b7ccdd;
}

.status-online { color: #7dffb2; }
.status-offline { color: #ffd36d; }

.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.panel {
    background: #122c44;
    padding: 10px;
    border-radius: 10px;
}

.kpis {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 10px;
}

.kpi {
    background: #163a59;
    padding: 8px;
    border-radius: 6px;
    text-align: center;
}

.kpi small { color: #b9c7d4; }
.kpi b { font-size: 18px; }

.chart-wrap {
    height: 220px;
}

.table {
    max-height: 320px;
    overflow: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 6px;
    font-size: 11px;
    border-bottom: 1px solid #1f4a6e;
}

th {
    background: #1f4a6e;
    position: sticky;
    top: 0;
}

.flash { animation: flash 2s 3; }

@keyframes flash {
    0% { background: yellow; color: black; }
    100% { background: inherit; }
}
</style>
</head>

<body>

<h1>SCADA Dashboard</h1>

<div class="topbar">
    <div class="info-card">
        <div class="info-title">System Status</div>
        <div class="info-value <?= $systemStatus === 'ONLINE' ? 'status-online' : 'status-offline' ?>">
            <?= h($systemStatus) ?>
        </div>
    </div>

    <div class="info-card">
        <div class="info-title">Latest Nozzle</div>
        <div class="info-value"><?= h($latestNozzle['id'] ?? '-') ?></div>
        <div class="info-sub"><?= h($lastNozzleStamp) ?></div>
    </div>

    <div class="info-card">
        <div class="info-title">Latest Tricanter</div>
        <div class="info-value"><?= h($latestTricanter['id'] ?? '-') ?></div>
        <div class="info-sub"><?= h($lastTricanterStamp) ?></div>
    </div>

    <div class="info-card">
        <div class="info-title">Records</div>
        <div class="info-value"><?= count($nozzle)+count($tricanter) ?></div>
    </div>
</div>

<div class="grid">

<div class="panel">
<h2>Nozzle</h2>

<div class="kpis">
<div class="kpi"><small>Flow</small><b><?= fmt($latestNozzle['flow'],1) ?></b></div>
<div class="kpi"><small>Pressure</small><b><?= fmt($latestNozzle['pressure'],2) ?></b></div>
<div class="kpi"><small>RPM</small><b><?= fmt($latestNozzle['rpm'],1) ?></b></div>
</div>

<div class="chart-wrap"><canvas id="nozzleCombinedChart"></canvas></div>
</div>

<div class="panel">
<h2>Tricanter</h2>

<div class="kpis">
<div class="kpi"><small>Bowl</small><b><?= fmt($latestTricanter['bowl_speed'],0) ?></b></div>
<div class="kpi"><small>Screw</small><b><?= fmt($latestTricanter['screw_speed'],2) ?></b></div>
<div class="kpi"><small>Torque</small><b><?= fmt($latestTricanter['torque'],1) ?></b></div>
</div>

<div class="chart-wrap"><canvas id="tricanterCombinedChart"></canvas></div>
</div>

</div>

<script>
function makeChart(id,data,label,color){
    new Chart(document.getElementById(id),{
        type:'line',
        data:{labels:data.map((_,i)=>i+1),
        datasets:[{label:label,data:data,borderColor:color,tension:0.2}]},
        options:{plugins:{legend:{labels:{color:'#fff'}}}}
    });
}

makeChart('nozzleCombinedChart',<?= json_encode($nozzleFlowSeries) ?>,'Flow','#00ffff');
makeChart('tricanterCombinedChart',<?= json_encode($tricanterTorqueSeries) ?>,'Torque','#ff7e67');
</script>

</body>
</html>