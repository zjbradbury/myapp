<?php
$host = "mariadb";
$dbname = "myapp";
$user = "zack";
$pass = "Butcher69";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function toFloatOrNull($value) {
    if ($value === null || $value === '') {
        return null;
    }
    return is_numeric($value) ? (float)$value : null;
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

    $recentNozzle = $pdo->query("SELECT * FROM nozzle_logs ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    $recentTricanter = $pdo->query("SELECT * FROM tricanter_logs ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

    $trendNozzleRows = $pdo->query("SELECT id, log_date, log_time, nozzle, flow, pressure, min_deg, max_deg, rpm FROM nozzle_logs ORDER BY id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
    $trendTricanterRows = $pdo->query("SELECT id, log_date, log_time, bowl_speed, screw_speed, bowl_rpm, screw_rpm, feed_rate, torque, temp, pressure FROM tricanter_logs ORDER BY id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    die("Database error: " . h($e->getMessage()));
}

$trendNozzleRows = array_reverse($trendNozzleRows);
$trendTricanterRows = array_reverse($trendTricanterRows);

$nozzleLabels = [];
$nozzleFlow = [];
$nozzlePressure = [];
$nozzleRpm = [];
$nozzleMinDeg = [];
$nozzleMaxDeg = [];

foreach ($trendNozzleRows as $row) {
    $label = trim(($row['log_date'] ?? '') . ' ' . ($row['log_time'] ?? ''));
    $nozzleLabels[] = $label !== '' ? $label : ('ID ' . ($row['id'] ?? ''));
    $nozzleFlow[] = toFloatOrNull($row['flow'] ?? null);
    $nozzlePressure[] = toFloatOrNull($row['pressure'] ?? null);
    $nozzleRpm[] = toFloatOrNull($row['rpm'] ?? null);
    $nozzleMinDeg[] = toFloatOrNull($row['min_deg'] ?? null);
    $nozzleMaxDeg[] = toFloatOrNull($row['max_deg'] ?? null);
}

$tricanterLabels = [];
$tricanterFeedRate = [];
$tricanterTorque = [];
$tricanterTemp = [];
$tricanterPressure = [];
$tricanterBowlRpm = [];
$tricanterScrewRpm = [];

foreach ($trendTricanterRows as $row) {
    $label = trim(($row['log_date'] ?? '') . ' ' . ($row['log_time'] ?? ''));
    $tricanterLabels[] = $label !== '' ? $label : ('ID ' . ($row['id'] ?? ''));
    $tricanterFeedRate[] = toFloatOrNull($row['feed_rate'] ?? null);
    $tricanterTorque[] = toFloatOrNull($row['torque'] ?? null);
    $tricanterTemp[] = toFloatOrNull($row['temp'] ?? null);
    $tricanterPressure[] = toFloatOrNull($row['pressure'] ?? null);
    $tricanterBowlRpm[] = toFloatOrNull($row['bowl_rpm'] ?? null);
    $tricanterScrewRpm[] = toFloatOrNull($row['screw_rpm'] ?? null);
}

$systemStatus = (!empty($latestNozzle) || !empty($latestTricanter)) ? 'ONLINE' : 'NO DATA';
?><!DOCTYPE html><html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kleensafe SCADA Dashboard</title>
    <meta http-equiv="refresh" content="30">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #071521;
            --panel: #0d2336;
            --panel-2: #13324c;
            --line: #1e5078;
            --text: #e8f3ff;
            --muted: #93b6d3;
            --accent: #00d3ff;
            --good: #1ad66c;
            --warn: #ffd24d;
            --bad: #ff5f6d;
        }* { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: Arial, Helvetica, sans-serif;
        background: linear-gradient(180deg, #06101a 0%, #081b2a 100%);
        color: var(--text);
    }

    .wrap {
        max-width: 1800px;
        margin: 0 auto;
        padding: 18px;
    }

    .topbar {
        display: grid;
        grid-template-columns: 1.3fr 1fr 1fr 1fr;
        gap: 14px;
        margin-bottom: 18px;
    }

    .panel {
        background: linear-gradient(180deg, var(--panel) 0%, #0a1d2d 100%);
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 14px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.28);
    }

    .brand h1 {
        margin: 0;
        font-size: 30px;
        letter-spacing: 1px;
    }

    .subtle {
        color: var(--muted);
        font-size: 13px;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 12px;
        border-radius: 999px;
        background: rgba(26, 214, 108, 0.12);
        border: 1px solid rgba(26, 214, 108, 0.35);
        color: #b8ffd2;
        font-weight: 700;
        margin-top: 10px;
    }

    .status-pill.warn {
        background: rgba(255, 210, 77, 0.12);
        border-color: rgba(255, 210, 77, 0.35);
        color: #ffe9a1;
    }

    .dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: currentColor;
        box-shadow: 0 0 10px currentColor;
    }

    .metric-label {
        color: var(--muted);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 8px;
    }

    .metric-value {
        font-size: 30px;
        font-weight: 700;
    }

    .metric-unit {
        font-size: 14px;
        color: var(--muted);
        margin-left: 6px;
    }

    .main-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 18px;
        align-items: start;
    }

    .section-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .section-title h2 {
        margin: 0;
        font-size: 22px;
    }

    .live-tag {
        color: #9df3ff;
        font-size: 12px;
        border: 1px solid rgba(0, 211, 255, 0.3);
        padding: 5px 8px;
        border-radius: 999px;
        background: rgba(0, 211, 255, 0.08);
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 14px;
    }

    .kpi {
        background: rgba(19, 50, 76, 0.72);
        border: 1px solid rgba(30, 80, 120, 0.7);
        border-radius: 12px;
        padding: 12px;
    }

    .chart-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
        margin-bottom: 14px;
    }

    .chart-panel {
        background: rgba(19, 50, 76, 0.62);
        border: 1px solid rgba(30, 80, 120, 0.7);
        border-radius: 12px;
        padding: 12px;
        height: 260px;
        min-height: 260px;
        overflow: hidden;
    }

    .chart-panel h3 {
        margin: 0 0 10px 0;
        font-size: 15px;
        color: #c9eaff;
    }

    .table-wrap {
        max-height: 360px;
        overflow: auto;
        border-radius: 10px;
        border: 1px solid rgba(30, 80, 120, 0.7);
        width: 100%;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        table-layout: auto;
    }

    th, td {
        padding: 9px 10px;
        border-bottom: 1px solid rgba(30, 80, 120, 0.55);
        font-size: 12px;
        text-align: left;
        white-space: nowrap;
    }

    th {
        position: sticky;
        top: 0;
        background: #14334d;
        color: #d8eeff;
        z-index: 1;
    }

    tr:nth-child(even) {
        background: rgba(14, 35, 54, 0.88);
    }

    tr.flash {
        animation: flash-row 2.2s ease-in-out 0s 4;
    }

    @keyframes flash-row {
        0% { background: rgba(255, 210, 77, 0.85); color: #000; }
        50% { background: rgba(0, 211, 255, 0.18); color: var(--text); }
        100% { background: inherit; color: inherit; }
    }

    .footer-note {
        margin-top: 18px;
        color: var(--muted);
        font-size: 12px;
        text-align: center;
    }

    @media (max-width: 1200px) {
        .topbar { grid-template-columns: 1fr 1fr; }
        .main-grid { grid-template-columns: 1fr; }
        .table-wrap { max-height: 320px; }
    }

    @media (max-width: 760px) {
        .topbar, .kpi-grid { grid-template-columns: 1fr; }
        .brand h1 { font-size: 24px; }
        .metric-value { font-size: 24px; }
        .wrap { padding: 12px; }
        .chart-panel {
            height: 220px;
            min-height: 220px;
        }
        th, td {
            font-size: 11px;
            padding: 7px 8px;
        }
        .table-wrap {
            max-height: 280px;
        }
    }
</style>

</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div class="panel brand">
            <h1>KLEENSAFE SCADA</h1>
            <div class="subtle">Live process dashboard for nozzle and tricanter logs</div>
            <div class="status-pill <?= $systemStatus === 'ONLINE' ? '' : 'warn' ?>">
                <span class="dot"></span>
                <span>System <?= h($systemStatus) ?></span>
            </div>
        </div><div class="panel">
        <div class="metric-label">Latest Nozzle Log</div>
        <div class="metric-value"><?= h($latestNozzle['id'] ?? '-') ?></div>
        <div class="subtle"><?= h(($latestNozzle['log_date'] ?? '-') . ' ' . ($latestNozzle['log_time'] ?? '')) ?></div>
    </div>

    <div class="panel">
        <div class="metric-label">Latest Tricanter Log</div>
        <div class="metric-value"><?= h($latestTricanter['id'] ?? '-') ?></div>
        <div class="subtle"><?= h(($latestTricanter['log_date'] ?? '-') . ' ' . ($latestTricanter['log_time'] ?? '')) ?></div>
    </div>

    <div class="panel">
        <div class="metric-label">Page Refresh</div>
        <div class="metric-value">30<span class="metric-unit">sec</span></div>
        <div class="subtle">Newest records flash automatically</div>
    </div>
</div>

<div class="main-grid">
    <div class="panel">
        <div class="section-title">
            <h2>Nozzle</h2>
            <div class="live-tag">Live feed</div>
        </div>

        <div class="kpi-grid">
            <div class="kpi">
                <div class="metric-label">Flow</div>
                <div class="metric-value"><?= h($latestNozzle['flow'] ?? '-') ?><span class="metric-unit">m³/hr</span></div>
            </div>
            <div class="kpi">
                <div class="metric-label">Pressure</div>
                <div class="metric-value"><?= h($latestNozzle['pressure'] ?? '-') ?><span class="metric-unit">bar</span></div>
            </div>
            <div class="kpi">
                <div class="metric-label">Nozzle</div>
                <div class="metric-value"><?= h($latestNozzle['nozzle'] ?? '-') ?></div>
            </div>
            <div class="kpi">
                <div class="metric-label">Min Deg</div>
                <div class="metric-value"><?= h($latestNozzle['min_deg'] ?? '-') ?><span class="metric-unit">°</span></div>
            </div>
            <div class="kpi">
                <div class="metric-label">Max Deg</div>
                <div class="metric-value"><?= h($latestNozzle['max_deg'] ?? '-') ?><span class="metric-unit">°</span></div>
            </div>
            <div class="kpi">
                <div class="metric-label">RPM</div>
                <div class="metric-value"><?= h($latestNozzle['rpm'] ?? '-') ?></div>
            </div>
        </div>

        <div class="chart-grid">
            <div class="chart-panel">
                <h3>Nozzle Trend</h3>
                <canvas id="nozzleChart"></canvas>
            </div>
        </div>

        <div class="table-wrap" id="nozzleFeed">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Nozzle</th>
                    <th>Flow</th>
                    <th>Pressure</th>
                    <th>Min Deg</th>
                    <th>Max Deg</th>
                    <th>RPM</th>
                    <th>Comments</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentNozzle as $row): ?>
                    <tr class="nozzle-row" data-id="<?= (int)$row['id'] ?>">
                        <td><?= h($row['id']) ?></td>
                        <td><?= h($row['log_date']) ?></td>
                        <td><?= h($row['log_time']) ?></td>
                        <td><?= h($row['nozzle']) ?></td>
                        <td><?= h($row['flow']) ?></td>
                        <td><?= h($row['pressure']) ?></td>
                        <td><?= h($row['min_deg']) ?></td>
                        <td><?= h($row['max_deg']) ?></td>
                        <td><?= h($row['rpm']) ?></td>
                        <td><?= h($row['comments']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="section-title">
            <h2>Tricanter</h2>
            <div class="live-tag">Live feed</div>
        </div>

        <div class="kpi-grid">
            <div class="kpi">
                <div class="metric-label">Feed Rate</div>
                <div class="metric-value"><?= h($latestTricanter['feed_rate'] ?? '-') ?><span class="metric-unit">m³/hr</span></div>
            </div>
            <div class="kpi">
                <div class="metric-label">Torque</div>
                <div class="metric-value"><?= h($latestTricanter['torque'] ?? '-') ?><span class="metric-unit">%</span></div>
            </div>
            <div class="kpi">
                <div class="metric-label">Temp</div>
                <div class="metric-value"><?= h($latestTricanter['temp'] ?? '-') ?><span class="metric-unit">°C</span></div>
            </div>
            <div class="kpi">
                <div class="metric-label">Pressure</div>
                <div class="metric-value"><?= h($latestTricanter['pressure'] ?? '-') ?><span class="metric-unit">bar</span></div>
            </div>
            <div class="kpi">
                <div class="metric-label">Bowl RPM</div>
                <div class="metric-value"><?= h($latestTricanter['bowl_rpm'] ?? '-') ?></div>
            </div>
            <div class="kpi">
                <div class="metric-label">Screw RPM</div>
                <div class="metric-value"><?= h($latestTricanter['screw_rpm'] ?? '-') ?></div>
            </div>
        </div>

        <div class="chart-grid">
            <div class="chart-panel">
                <h3>Tricanter Trend</h3>
                <canvas id="tricanterChart"></canvas>
            </div>
        </div>

        <div class="table-wrap" id="tricanterFeed">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Bowl Speed</th>
                    <th>Screw Speed</th>
                    <th>Bowl RPM</th>
                    <th>Screw RPM</th>
                    <th>Impeller</th>
                    <th>Feed Rate</th>
                    <th>Torque</th>
                    <th>Temp</th>
                    <th>Pressure</th>
                    <th>Comments</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentTricanter as $row): ?>
                    <tr class="tricanter-row" data-id="<?= (int)$row['id'] ?>">
                        <td><?= h($row['id']) ?></td>
                        <td><?= h($row['log_date']) ?></td>
                        <td><?= h($row['log_time']) ?></td>
                        <td><?= h($row['bowl_speed']) ?></td>
                        <td><?= h($row['screw_speed']) ?></td>
                        <td><?= h($row['bowl_rpm']) ?></td>
                        <td><?= h($row['screw_rpm']) ?></td>
                        <td><?= h($row['impeller']) ?></td>
                        <td><?= h($row['feed_rate']) ?></td>
                        <td><?= h($row['torque']) ?></td>
                        <td><?= h($row['temp']) ?></td>
                        <td><?= h($row['pressure']) ?></td>
                        <td><?= h($row['comments']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="footer-note">Dashboard auto-refreshes every 30 seconds. Trending charts show the latest 30 records from each table.</div>

</div><script>
const nozzleLabels = <?= json_encode($nozzleLabels) ?>;
const nozzleFlow = <?= json_encode($nozzleFlow) ?>;
const nozzlePressure = <?= json_encode($nozzlePressure) ?>;
const nozzleRpm = <?= json_encode($nozzleRpm) ?>;
const nozzleMinDeg = <?= json_encode($nozzleMinDeg) ?>;
const nozzleMaxDeg = <?= json_encode($nozzleMaxDeg) ?>;

const tricanterLabels = <?= json_encode($tricanterLabels) ?>;
const tricanterFeedRate = <?= json_encode($tricanterFeedRate) ?>;
const tricanterTorque = <?= json_encode($tricanterTorque) ?>;
const tricanterTemp = <?= json_encode($tricanterTemp) ?>;
const tricanterPressure = <?= json_encode($tricanterPressure) ?>;
const tricanterBowlRpm = <?= json_encode($tricanterBowlRpm) ?>;
const tricanterScrewRpm = <?= json_encode($tricanterScrewRpm) ?>;

function baseChartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { labels: { color: '#dcecff' } }
        },
        scales: {
            x: {
                ticks: { color: '#9fc4e3', maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                grid: { color: 'rgba(159, 196, 227, 0.08)' }
            },
            y: {
                ticks: { color: '#9fc4e3' },
                grid: { color: 'rgba(159, 196, 227, 0.08)' }
            }
        }
    };
}

new Chart(document.getElementById('nozzleChart'), {
    type: 'line',
    data: {
        labels: nozzleLabels,
        datasets: [
            { label: 'Flow', data: nozzleFlow, borderColor: '#00d3ff', backgroundColor: 'rgba(0,211,255,0.1)', tension: 0.25 },
            { label: 'Pressure', data: nozzlePressure, borderColor: '#ffd24d', backgroundColor: 'rgba(255,210,77,0.1)', tension: 0.25 },
            { label: 'RPM', data: nozzleRpm, borderColor: '#ff7e67', backgroundColor: 'rgba(255,126,103,0.1)', tension: 0.25 },
            { label: 'Min Deg', data: nozzleMinDeg, borderColor: '#6ee7a1', backgroundColor: 'rgba(110,231,161,0.1)', tension: 0.25 },
            { label: 'Max Deg', data: nozzleMaxDeg, borderColor: '#c8a7ff', backgroundColor: 'rgba(200,167,255,0.1)', tension: 0.25 }
        ]
    },
    options: {
        ...baseChartOptions(),
        plugins: {
            legend: {
                labels: {
                    color: '#dcecff',
                    boxWidth: 10,
                    padding: 10,
                    font: { size: 11 }
                }
            }
        }
    }
});

new Chart(document.getElementById('tricanterChart'), {
    type: 'line',
    data: {
        labels: tricanterLabels,
        datasets: [
            { label: 'Feed Rate', data: tricanterFeedRate, borderColor: '#00d3ff', backgroundColor: 'rgba(0,211,255,0.1)', tension: 0.25 },
            { label: 'Torque', data: tricanterTorque, borderColor: '#ffd24d', backgroundColor: 'rgba(255,210,77,0.1)', tension: 0.25 },
            { label: 'Temp', data: tricanterTemp, borderColor: '#ff7e67', backgroundColor: 'rgba(255,126,103,0.1)', tension: 0.25 },
            { label: 'Pressure', data: tricanterPressure, borderColor: '#6ee7a1', backgroundColor: 'rgba(110,231,161,0.1)', tension: 0.25 },
            { label: 'Bowl RPM', data: tricanterBowlRpm, borderColor: '#c8a7ff', backgroundColor: 'rgba(200,167,255,0.1)', tension: 0.25 },
            { label: 'Screw RPM', data: tricanterScrewRpm, borderColor: '#ff9bd6', backgroundColor: 'rgba(255,155,214,0.1)', tension: 0.25 }
        ]
    },
    options: {
        ...baseChartOptions(),
        plugins: {
            legend: {
                labels: {
                    color: '#dcecff',
                    boxWidth: 10,
                    padding: 10,
                    font: { size: 11 }
                }
            }
        }
    }
});

function setupFeed(rowSelector, storageKey, feedId) {
    const rows = document.querySelectorAll(rowSelector);
    const feed = document.getElementById(feedId);

    let lastSeenId = parseInt(localStorage.getItem(storageKey) || '0', 10);
    let maxIdOnPage = lastSeenId;
    let firstNewRow = null;

    rows.forEach((row) => {
        const rowId = parseInt(row.dataset.id || '0', 10);
        if (rowId > lastSeenId) {
            row.classList.add('flash');
            if (!firstNewRow) {
                firstNewRow = row;
            }
        }
        if (rowId > maxIdOnPage) {
            maxIdOnPage = rowId;
        }
    });

    if (feed) {
        if (firstNewRow) {
            firstNewRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            feed.scrollTop = 0;
        }
    }

    localStorage.setItem(storageKey, String(maxIdOnPage));
}

setupFeed('.nozzle-row', 'scada_last_nozzle_id', 'nozzleFeed');
setupFeed('.tricanter-row', 'scada_last_tricanter_id', 'tricanterFeed');
</script></body>
</html>