<?php
$host = "mariadb";
$dbname = "myapp";
$user = "zack";
$pass = "Butcher69";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fmt($value, $decimals = 0) {
    if ($value === null || $value === '') return '-';
    if (!is_numeric($value)) return h($value);
    return number_format((float)$value, $decimals, '.', '');
}

function numeric_series(array $rows, string $key): array {
    $out = [];
    foreach (array_reverse($rows) as $row) {
        if (isset($row[$key]) && $row[$key] !== '' && is_numeric($row[$key])) {
            $out[] = (float)$row[$key];
        }
    }
    return $out;
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

    $nozzle = $pdo->query("SELECT * FROM nozzle_logs ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    $tricanter = $pdo->query("SELECT * FROM tricanter_logs ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    die("DB Error: " . h($e->getMessage()));
}

$nozzleFlowSeries = numeric_series($nozzle, 'flow');
$tricanterFeedSeries = numeric_series($tricanter, 'feed_rate');
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
    margin: 0 0 15px;
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

.chart-wrap {
    position: relative;
    width: 100%;
    height: 180px;
    margin-bottom: 10px;
    background: #10273c;
    border-radius: 8px;
    overflow: hidden;
}

.chart-wrap canvas {
    width: 100% !important;
    height: 100% !important;
    display: block;
}

.table {
    max-height: 300px;
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

@media (max-width: 900px) {
    .grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<h1>SCADA Dashboard</h1>

<div class="grid">

    <div class="panel">
        <h2>Nozzle</h2>

        <div class="kpis">
            <div class="kpi"><small>Flow</small><b><?= fmt($latestNozzle['flow'] ?? null, 1) ?></b></div>
            <div class="kpi"><small>Pressure</small><b><?= fmt($latestNozzle['pressure'] ?? null, 2) ?></b></div>
            <div class="kpi"><small>RPM</small><b><?= fmt($latestNozzle['rpm'] ?? null, 1) ?></b></div>
            <div class="kpi"><small>Min Deg</small><b><?= fmt($latestNozzle['min_deg'] ?? null, 0) ?></b></div>
            <div class="kpi"><small>Max Deg</small><b><?= fmt($latestNozzle['max_deg'] ?? null, 0) ?></b></div>
        </div>

        <div class="chart-wrap">
            <canvas id="nozzleChart"></canvas>
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
            <div class="kpi"><small>Feed</small><b><?= fmt($latestTricanter['feed_rate'] ?? null, 2) ?></b></div>
            <div class="kpi"><small>Torque</small><b><?= fmt($latestTricanter['torque'] ?? null, 1) ?></b></div>
            <div class="kpi"><small>Temp</small><b><?= fmt($latestTricanter['temp'] ?? null, 1) ?></b></div>
            <div class="kpi"><small>Pressure</small><b><?= fmt($latestTricanter['pressure'] ?? null, 3) ?></b></div>
            <div class="kpi"><small>Bowl RPM</small><b><?= fmt($latestTricanter['bowl_rpm'] ?? null, 0) ?></b></div>
            <div class="kpi"><small>Screw RPM</small><b><?= fmt($latestTricanter['screw_rpm'] ?? null, 2) ?></b></div>
        </div>

        <div class="chart-wrap">
            <canvas id="triChart"></canvas>
        </div>

        <div class="table">
            <table>
                <tr>
                    <th>ID</th><th>Date</th><th>Time</th><th>Feed</th><th>Torque</th><th>Temp</th><th>Pressure</th>
                </tr>
                <?php foreach ($tricanter as $r): ?>
                <tr class="tri-row" data-id="<?= (int)$r['id'] ?>">
                    <td><?= h($r['id']) ?></td>
                    <td><?= h($r['log_date']) ?></td>
                    <td><?= h($r['log_time']) ?></td>
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

function makeSimpleChart(canvasId, series, color) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    if (!Array.isArray(series) || series.length === 0) {
        return;
    }

    const labels = series.map((_, i) => i + 1);

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                data: series,
                borderColor: color,
                backgroundColor: 'transparent',
                borderWidth: 2,
                tension: 0.25,
                pointRadius: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: true }
            },
            scales: {
                x: { display: false },
                y: { display: false }
            }
        }
    });
}

makeSimpleChart('nozzleChart', <?= json_encode($nozzleFlowSeries) ?>, '#00ffff');
makeSimpleChart('triChart', <?= json_encode($tricanterFeedSeries) ?>, '#00ff88');
</script>

</body>
</html>