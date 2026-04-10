<?php
require_once "config.php";
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['monitor_form'] ?? '';

    if ($form === 'master') {
        setSetting($pdo, 'monitor_master', isset($_POST['monitor_master']) ? '1' : '0');

        $refresh = isset($_POST['monitor_refresh_seconds']) ? (int) $_POST['monitor_refresh_seconds'] : 30;
        if ($refresh < 5) { $refresh = 5; }
        if ($refresh > 300) { $refresh = 300; }
        setSetting($pdo, 'monitor_refresh_seconds', (string) $refresh);
    }

    if ($form === 'item') {
        $key = trim($_POST['monitor_key'] ?? '');
        $allowed = ['nozzle', 'tricanter', 'solid_waste', 'sample', 'gas_test'];

        if (in_array($key, $allowed, true)) {
            setSetting($pdo, 'monitor_' . $key . '_enabled', isset($_POST['monitor_enabled']) ? '1' : '0');

            $minutes = isset($_POST['monitor_minutes']) ? (int) $_POST['monitor_minutes'] : 60;
            if ($minutes < 1) { $minutes = 1; }
            if ($minutes > 1440) { $minutes = 1440; }

            setSetting($pdo, 'monitor_' . $key . '_minutes', (string) $minutes);
        }
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

$range = get_range_filter_state();
$monitorData = buildMonitoringData($pdo);

try {
    $nozzle = fetch_log_rows($pdo, 'nozzle_logs', $range, 'id DESC');
    $tricanter = fetch_log_rows($pdo, 'tricanter_logs', $range, 'id DESC');
    $solidWaste = fetch_log_rows($pdo, 'solid_waste_logs', $range, 'id DESC');
    $sample = tableExists($pdo, 'sample_logs') ? fetch_log_rows($pdo, 'sample_logs', $range, 'id DESC') : [];
    $gasTest = tableExists($pdo, 'gas_test_logs') ? fetch_log_rows($pdo, 'gas_test_logs', $range, 'id DESC') : [];

    $latestNozzleOverall = fetch_latest_row($pdo, 'nozzle_logs') ?: [];
    $latestTricanterOverall = fetch_latest_row($pdo, 'tricanter_logs') ?: [];
    $latestSolidWasteOverall = fetch_latest_row($pdo, 'solid_waste_logs') ?: [];
    $latestSampleOverall = tableExists($pdo, 'sample_logs') ? (fetch_latest_row($pdo, 'sample_logs') ?: []) : [];
    $latestGasTestOverall = tableExists($pdo, 'gas_test_logs') ? (fetch_latest_row($pdo, 'gas_test_logs') ?: []) : [];

    $latestNozzle = $nozzle[0] ?? [];
    $latestTricanter = $tricanter[0] ?? [];
    $latestSolidWaste = $solidWaste[0] ?? [];
    $latestSample = $sample[0] ?? [];
    $latestGasTest = $gasTest[0] ?? [];
} catch (Throwable $e) {
    die("DB Error: " . h($e->getMessage()));
}

$solidWaste = solid_diff_minutes_rows($solidWaste);
$latestSolidWaste = $solidWaste[0] ?? [];

$nozzleLabels = label_series($nozzle);
$nozzleFlowSeries = numeric_series($nozzle, 'flow');
$nozzlePressureSeries = numeric_series($nozzle, 'pressure');
$nozzleMinDegSeries = numeric_series($nozzle, 'min_deg');
$nozzleMaxDegSeries = numeric_series($nozzle, 'max_deg');
$nozzleRpmSeries = numeric_series($nozzle, 'rpm');

$tricanterLabels = label_series($tricanter);
$tricanterBowlSpeedSeries = numeric_series($tricanter, 'bowl_speed');
$tricanterScrewSpeedSeries = numeric_series($tricanter, 'screw_speed');
$tricanterBowlRpmSeries = numeric_series($tricanter, 'bowl_rpm');
$tricanterScrewRpmSeries = numeric_series($tricanter, 'screw_rpm');
$tricanterImpellerSeries = numeric_series($tricanter, 'impeller');
$tricanterFeedRateSeries = numeric_series($tricanter, 'feed_rate');
$tricanterTorqueSeries = numeric_series($tricanter, 'torque');
$tricanterTempSeries = numeric_series($tricanter, 'temp');
$tricanterPressureSeries = numeric_series($tricanter, 'pressure');

$solidWasteLabels = label_series($solidWaste);
$solidWasteAmountSeries = numeric_series($solidWaste, 'amount');
$solidWasteDiffSeries = solid_diff_series($solidWaste);

$sampleLabels = label_series($sample);
$sampleFlowSeries = numeric_series($sample, 'flow');
$sampleMercurySeries = numeric_series($sample, 'mercury');
$sampleSolidsSeries = numeric_series($sample, 'solids');
$sampleWaterSeries = numeric_series($sample, 'water');
$sampleWaxSeries = numeric_series($sample, 'wax');

$gasTestLabels = label_series($gasTest);
$gasMercurySeries = numeric_series($gasTest, 'mercury');
$gasBenzeneSeries = numeric_series($gasTest, 'benzene');
$gasLelSeries = numeric_series($gasTest, 'lel');
$gasH2sSeries = numeric_series($gasTest, 'h2s');
$gasO2Series = numeric_series($gasTest, 'o2');

$solidWasteTotalAmount = 0.0;
foreach ($solidWaste as $r) {
    if (isset($r['amount']) && $r['amount'] !== '' && is_numeric($r['amount'])) {
        $solidWasteTotalAmount += (float) $r['amount'];
    }
}

$systemStatus = (
    !empty($latestNozzleOverall) ||
    !empty($latestTricanterOverall) ||
    !empty($latestSolidWasteOverall) ||
    !empty($latestSampleOverall) ||
    !empty($latestGasTestOverall)
) ? 'ONLINE' : 'NO DATA';

$lastNozzleStamp = trim(($latestNozzleOverall['log_date'] ?? '-') . ' ' . ($latestNozzleOverall['log_time'] ?? ''));
$lastTricanterStamp = trim(($latestTricanterOverall['log_date'] ?? '-') . ' ' . ($latestTricanterOverall['log_time'] ?? ''));
$lastSolidWasteStamp = trim(($latestSolidWasteOverall['log_date'] ?? '-') . ' ' . ($latestSolidWasteOverall['log_time'] ?? ''));
$lastSampleStamp = trim(($latestSampleOverall['log_date'] ?? '-') . ' ' . ($latestSampleOverall['log_time'] ?? ''));
$lastGasTestStamp = trim(($latestGasTestOverall['log_date'] ?? '-') . ' ' . ($latestGasTestOverall['log_time'] ?? ''));

$recordsLoaded = count($nozzle) + count($tricanter) + count($solidWaste) + count($sample) + count($gasTest);
$rangeSummary = range_summary_text($range, 'Current shift block');
$refreshSeconds = (int) ($monitorData['refresh_seconds'] ?? 30);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="indexstyle.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php if ($refreshSeconds > 0): ?>
        <meta http-equiv="refresh" content="<?= (int) $refreshSeconds ?>">
    <?php endif; ?>
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="container wide">
    <div class="topbar">
        <h2>Dashboard</h2>
    </div>

    <div class="dashboard-grid">
        <div class="card span-8">
            <h3 class="section-title">Monitoring</h3>

            <form method="post" class="monitor-master-form">
                <input type="hidden" name="monitor_form" value="master">
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-label">Master</div>
                        <label>
                            <input type="checkbox" name="monitor_master" <?= !empty($monitorData['master_enabled']) ? 'checked' : '' ?> onchange="this.form.submit()">
                            On
                        </label>
                    </div>

                    <div class="stat-box">
                        <div class="stat-label">Refresh (sec)</div>
                        <input type="number" name="monitor_refresh_seconds" min="5" max="300" value="<?= (int) $refreshSeconds ?>" onchange="this.form.submit()">
                    </div>

                    <div class="stat-box">
                        <div class="stat-label">Master Status</div>
                        <?php $masterClass = 'status-' . strtolower(str_replace(' ', '-', $monitorData['master_state'] ?? 'ok')); ?>
                        <span class="status-pill <?= h($masterClass) ?>"><?= h($monitorData['master_state'] ?? 'OK') ?></span>
                    </div>
                </div>
            </form>

            <div class="table-scroll">
                <table class="monitor-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>On</th>
                            <th>Last Entry</th>
                            <th>Since Last</th>
                            <th>Timer (min)</th>
                            <th>Countdown</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($monitorData['items'] ?? []) as $key => $item): ?>
                            <tr>
                                <td><?= h($item['label'] ?? $key) ?></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="monitor_form" value="item">
                                        <input type="hidden" name="monitor_key" value="<?= h($key) ?>">
                                        <input type="checkbox" name="monitor_enabled" <?= !empty($item['enabled']) ? 'checked' : '' ?> onchange="this.form.submit()">
                                        <input type="hidden" name="monitor_minutes" value="<?= (int) ($item['minutes'] ?? 60) ?>">
                                    </form>
                                </td>
                                <td><?= h($item['last_entry_display'] ?? 'No data') ?></td>
                                <td><?= h($item['since_text'] ?? 'No data') ?></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="monitor_form" value="item">
                                        <input type="hidden" name="monitor_key" value="<?= h($key) ?>">
                                        <input type="hidden" name="monitor_enabled" value="<?= !empty($item['enabled']) ? '1' : '0' ?>">
                                        <input type="number" name="monitor_minutes" min="1" max="1440" value="<?= (int) ($item['minutes'] ?? 60) ?>" onchange="this.form.submit()" class="monitor-minutes-input">
                                    </form>
                                </td>
                                <td><?= h($item['countdown'] ?? '--') ?></td>
                                <td>
                                    <?php $statusClass = 'status-' . strtolower(str_replace(' ', '-', $item['status'] ?? 'ok')); ?>
                                    <span class="status-pill <?= h($statusClass) ?>"><?= h($item['status'] ?? 'OK') ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card span-4">
            <h3 class="section-title">System Status</h3>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">System Status</div>
                    <div class="stat-value"><?= h($systemStatus) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Records Loaded</div>
                    <div class="stat-value"><?= (int) $recordsLoaded ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Auto Refresh</div>
                    <div class="stat-value"><?= (int) $refreshSeconds ?>s</div>
                </div>
            </div>

            <div class="mini-meta">
                <div><strong>Nozzle:</strong> <?= h($lastNozzleStamp) ?></div>
                <div><strong>Tricanter:</strong> <?= h($lastTricanterStamp) ?></div>
                <div><strong>Solid Waste:</strong> <?= h($lastSolidWasteStamp) ?></div>
                <div><strong>Sample:</strong> <?= h($lastSampleStamp) ?></div>
                <div><strong>Gas Test:</strong> <?= h($lastGasTestStamp) ?></div>
            </div>
        </div>

        <div class="card span-12">
            <?php render_dashboard_range_filter($range); ?>
            <div class="range-summary">
                <strong><?= h($rangeSummary) ?></strong><br>
                <?php if (!empty($range['active'])): ?>
                    <span>Filtering graphs and tables to selected range</span>
                <?php else: ?>
                    <span>Showing all available records</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="card span-12">
            <h3 class="section-title">Tricanter</h3>

            <div class="stats-grid">
                <div class="stat-box"><div class="stat-label">Bowl Speed</div><div class="stat-value"><?= isset($latestTricanter['bowl_speed']) ? fmt($latestTricanter['bowl_speed'], 0) . '%' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Screw Speed</div><div class="stat-value"><?= isset($latestTricanter['screw_speed']) ? fmt($latestTricanter['screw_speed'], 2) . '%' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Bowl RPM</div><div class="stat-value"><?= isset($latestTricanter['bowl_rpm']) ? fmt($latestTricanter['bowl_rpm'], 0) . ' RPM' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Screw RPM</div><div class="stat-value"><?= isset($latestTricanter['screw_rpm']) ? fmt($latestTricanter['screw_rpm'], 0) . ' RPM' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Impeller</div><div class="stat-value"><?= isset($latestTricanter['impeller']) ? fmt($latestTricanter['impeller'], 0) : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Feed Rate</div><div class="stat-value"><?= isset($latestTricanter['feed_rate']) ? fmt($latestTricanter['feed_rate'], 2) . ' M3/hr' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Torque</div><div class="stat-value"><?= isset($latestTricanter['torque']) ? fmt($latestTricanter['torque'], 1) . '%' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Temp</div><div class="stat-value"><?= isset($latestTricanter['temp']) ? fmt($latestTricanter['temp'], 1) . ' °C' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Pressure</div><div class="stat-value"><?= isset($latestTricanter['pressure']) ? fmt($latestTricanter['pressure'], 3) . ' BAR' : '-' ?></div></div>
            </div>

            <div class="chart-wrap">
                <canvas id="tricanterChart"></canvas>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Bowl Speed</th>
                            <th>Screw Speed</th>
                            <th>Bowl RPM</th>
                            <th>Screw RPM</th>
                            <th>Impeller</th>
                            <th>Feed</th>
                            <th>Torque</th>
                            <th>Temp</th>
                            <th>Pressure</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$tricanter): ?>
                            <tr><td colspan="11">No tricanter data in selected range.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tricanter as $row): ?>
                                <tr>
                                    <td><?= h($row['log_date'] ?? '') ?></td>
                                    <td><?= h($row['log_time'] ?? '') ?></td>
                                    <td><?= isset($row['bowl_speed']) ? fmt($row['bowl_speed'], 0) : '-' ?></td>
                                    <td><?= isset($row['screw_speed']) ? fmt($row['screw_speed'], 2) : '-' ?></td>
                                    <td><?= isset($row['bowl_rpm']) ? fmt($row['bowl_rpm'], 0) : '-' ?></td>
                                    <td><?= isset($row['screw_rpm']) ? fmt($row['screw_rpm'], 0) : '-' ?></td>
                                    <td><?= isset($row['impeller']) ? fmt($row['impeller'], 0) : '-' ?></td>
                                    <td><?= isset($row['feed_rate']) ? fmt($row['feed_rate'], 2) : '-' ?></td>
                                    <td><?= isset($row['torque']) ? fmt($row['torque'], 1) : '-' ?></td>
                                    <td><?= isset($row['temp']) ? fmt($row['temp'], 1) : '-' ?></td>
                                    <td><?= isset($row['pressure']) ? fmt($row['pressure'], 3) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card span-6">
            <h3 class="section-title">Nozzle</h3>

            <div class="stats-grid">
                <div class="stat-box"><div class="stat-label">Flow</div><div class="stat-value"><?= isset($latestNozzle['flow']) ? fmt($latestNozzle['flow'], 1) . ' M3/hr' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Pressure</div><div class="stat-value"><?= isset($latestNozzle['pressure']) ? fmt($latestNozzle['pressure'], 2) . ' BAR' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">RPM</div><div class="stat-value"><?= isset($latestNozzle['rpm']) ? fmt($latestNozzle['rpm'], 1) . ' RPM' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Min Deg</div><div class="stat-value"><?= isset($latestNozzle['min_deg']) ? fmt($latestNozzle['min_deg'], 0) . '°' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Max Deg</div><div class="stat-value"><?= isset($latestNozzle['max_deg']) ? fmt($latestNozzle['max_deg'], 0) . '°' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Nozzle</div><div class="stat-value"><?= h($latestNozzle['nozzle'] ?? '-') ?></div></div>
            </div>

            <div class="chart-wrap">
                <canvas id="nozzleChart"></canvas>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Nozzle</th>
                            <th>Flow</th>
                            <th>Pressure</th>
                            <th>Min</th>
                            <th>Max</th>
                            <th>RPM</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$nozzle): ?>
                            <tr><td colspan="8">No nozzle data in selected range.</td></tr>
                        <?php else: ?>
                            <?php foreach ($nozzle as $row): ?>
                                <tr>
                                    <td><?= h($row['log_date'] ?? '') ?></td>
                                    <td><?= h($row['log_time'] ?? '') ?></td>
                                    <td><?= h($row['nozzle'] ?? '') ?></td>
                                    <td><?= isset($row['flow']) ? fmt($row['flow'], 1) : '-' ?></td>
                                    <td><?= isset($row['pressure']) ? fmt($row['pressure'], 2) : '-' ?></td>
                                    <td><?= isset($row['min_deg']) ? fmt($row['min_deg'], 0) : '-' ?></td>
                                    <td><?= isset($row['max_deg']) ? fmt($row['max_deg'], 0) : '-' ?></td>
                                    <td><?= isset($row['rpm']) ? fmt($row['rpm'], 1) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card span-6">
            <h3 class="section-title">Sample</h3>

            <div class="stats-grid">
                <div class="stat-box"><div class="stat-label">Location</div><div class="stat-value"><?= h($latestSample['sample_location'] ?? '-') ?></div></div>
                <div class="stat-box"><div class="stat-label">Nozzle</div><div class="stat-value"><?= h($latestSample['nozzle'] ?? '-') ?></div></div>
                <div class="stat-box"><div class="stat-label">Flow</div><div class="stat-value"><?= isset($latestSample['flow']) ? fmt($latestSample['flow'], 2) . ' M3/hr' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Mercury</div><div class="stat-value"><?= isset($latestSample['mercury']) ? fmt($latestSample['mercury'], 3) . ' ppm' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Solids</div><div class="stat-value"><?= isset($latestSample['solids']) ? fmt($latestSample['solids'], 2) . '%' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Water</div><div class="stat-value"><?= isset($latestSample['water']) ? fmt($latestSample['water'], 2) . '%' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Wax</div><div class="stat-value"><?= isset($latestSample['wax']) ? fmt($latestSample['wax'], 2) . '%' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Operator</div><div class="stat-value"><?= h($latestSample['operator'] ?? '-') ?></div></div>
            </div>

            <div class="chart-wrap">
                <canvas id="sampleChart"></canvas>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Location</th>
                            <th>Nozzle</th>
                            <th>Flow</th>
                            <th>Mercury</th>
                            <th>Solids</th>
                            <th>Water</th>
                            <th>Wax</th>
                            <th>Operator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$sample): ?>
                            <tr><td colspan="10">No sample data in selected range.</td></tr>
                        <?php else: ?>
                            <?php foreach ($sample as $row): ?>
                                <tr>
                                    <td><?= h($row['log_date'] ?? '') ?></td>
                                    <td><?= h($row['log_time'] ?? '') ?></td>
                                    <td><?= h($row['sample_location'] ?? '') ?></td>
                                    <td><?= h($row['nozzle'] ?? '') ?></td>
                                    <td><?= isset($row['flow']) ? fmt($row['flow'], 2) : '-' ?></td>
                                    <td><?= isset($row['mercury']) ? fmt($row['mercury'], 3) : '-' ?></td>
                                    <td><?= isset($row['solids']) ? fmt($row['solids'], 2) : '-' ?></td>
                                    <td><?= isset($row['water']) ? fmt($row['water'], 2) : '-' ?></td>
                                    <td><?= isset($row['wax']) ? fmt($row['wax'], 2) : '-' ?></td>
                                    <td><?= h($row['operator'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card span-12">
            <h3 class="section-title">Gas Test</h3>

            <div class="stats-grid">
                <div class="stat-box"><div class="stat-label">Device</div><div class="stat-value"><?= h($latestGasTest['device'] ?? '-') ?></div></div>
                <div class="stat-box"><div class="stat-label">Operator</div><div class="stat-value"><?= h($latestGasTest['operator'] ?? '-') ?></div></div>
                <div class="stat-box"><div class="stat-label">Location</div><div class="stat-value"><?= h($latestGasTest['location'] ?? '-') ?></div></div>
                <div class="stat-box"><div class="stat-label">Mercury</div><div class="stat-value"><?= isset($latestGasTest['mercury']) ? fmt($latestGasTest['mercury'], 3) . ' ppm' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Benzene</div><div class="stat-value"><?= isset($latestGasTest['benzene']) ? fmt($latestGasTest['benzene'], 3) . ' ppm' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">LEL</div><div class="stat-value"><?= isset($latestGasTest['lel']) ? fmt($latestGasTest['lel'], 2) . '%' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">H2S</div><div class="stat-value"><?= isset($latestGasTest['h2s']) ? fmt($latestGasTest['h2s'], 2) . ' ppm' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">O2</div><div class="stat-value"><?= isset($latestGasTest['o2']) ? fmt($latestGasTest['o2'], 2) . '%' : '-' ?></div></div>
            </div>

            <div class="chart-wrap">
                <canvas id="gasTestChart"></canvas>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Device</th>
                            <th>Operator</th>
                            <th>Location</th>
                            <th>Area Details</th>
                            <th>Mercury</th>
                            <th>Benzene</th>
                            <th>LEL</th>
                            <th>H2S</th>
                            <th>O2</th>
                            <th>Product Details</th>
                            <th>Actions Taken</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$gasTest): ?>
                            <tr><td colspan="13">No gas test data in selected range.</td></tr>
                        <?php else: ?>
                            <?php foreach ($gasTest as $row): ?>
                                <tr>
                                    <td><?= h($row['log_date'] ?? '') ?></td>
                                    <td><?= h($row['log_time'] ?? '') ?></td>
                                    <td><?= h($row['device'] ?? '') ?></td>
                                    <td><?= h($row['operator'] ?? '') ?></td>
                                    <td><?= h($row['location'] ?? '') ?></td>
                                    <td><?= h($row['area_details'] ?? '') ?></td>
                                    <td><?= isset($row['mercury']) ? fmt($row['mercury'], 3) : '-' ?></td>
                                    <td><?= isset($row['benzene']) ? fmt($row['benzene'], 3) : '-' ?></td>
                                    <td><?= isset($row['lel']) ? fmt($row['lel'], 2) : '-' ?></td>
                                    <td><?= isset($row['h2s']) ? fmt($row['h2s'], 2) : '-' ?></td>
                                    <td><?= isset($row['o2']) ? fmt($row['o2'], 2) : '-' ?></td>
                                    <td><?= h($row['product_details'] ?? '') ?></td>
                                    <td><?= h($row['action_taken'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card span-12">
            <h3 class="section-title">Solid Waste</h3>

            <div class="stats-grid">
                <div class="stat-box"><div class="stat-label">Latest Amount</div><div class="stat-value"><?= isset($latestSolidWaste['amount']) ? fmt($latestSolidWaste['amount'], 2) . ' KG' : '-' ?></div></div>
                <div class="stat-box"><div class="stat-label">Total Amount</div><div class="stat-value"><?= fmt($solidWasteTotalAmount, 2) . ' KG' ?></div></div>
                <div class="stat-box"><div class="stat-label">Last Entry</div><div class="stat-value"><?= trim(($latestSolidWaste['log_date'] ?? '-') . ' ' . ($latestSolidWaste['log_time'] ?? '')) ?></div></div>
            </div>

            <div class="chart-wrap">
                <canvas id="solidWasteChart"></canvas>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Amount</th>
                            <th>Diff (min)</th>
                            <th>Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$solidWaste): ?>
                            <tr><td colspan="5">No solid waste data in selected range.</td></tr>
                        <?php else: ?>
                            <?php foreach ($solidWaste as $row): ?>
                                <tr>
                                    <td><?= h($row['log_date'] ?? '') ?></td>
                                    <td><?= h($row['log_time'] ?? '') ?></td>
                                    <td><?= isset($row['amount']) ? fmt($row['amount'], 2) : '-' ?></td>
                                    <td><?= isset($row['_diff_minutes']) && $row['_diff_minutes'] !== null ? fmt($row['_diff_minutes'], 2) : '-' ?></td>
                                    <td><?= h($row['comments'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function lineChart(id, labels, datasets) {
    const el = document.getElementById(id);
    if (!el) return;

    new Chart(el, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            elements: { point: { radius: 0 } },
            scales: {
                x: { display: false },
                y: { beginAtZero: false }
            }
        }
    });
}

lineChart('tricanterChart', <?= json_encode($tricanterLabels) ?>, [
    { label: 'Bowl Speed', data: <?= json_encode($tricanterBowlSpeedSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Screw Speed', data: <?= json_encode($tricanterScrewSpeedSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Bowl RPM', data: <?= json_encode($tricanterBowlRpmSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Screw RPM', data: <?= json_encode($tricanterScrewRpmSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Impeller', data: <?= json_encode($tricanterImpellerSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Feed Rate', data: <?= json_encode($tricanterFeedRateSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Torque', data: <?= json_encode($tricanterTorqueSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Temp', data: <?= json_encode($tricanterTempSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Pressure', data: <?= json_encode($tricanterPressureSeries) ?>, borderWidth: 2, tension: 0.25 }
]);

lineChart('nozzleChart', <?= json_encode($nozzleLabels) ?>, [
    { label: 'Flow', data: <?= json_encode($nozzleFlowSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Pressure', data: <?= json_encode($nozzlePressureSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Min Deg', data: <?= json_encode($nozzleMinDegSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Max Deg', data: <?= json_encode($nozzleMaxDegSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'RPM', data: <?= json_encode($nozzleRpmSeries) ?>, borderWidth: 2, tension: 0.25 }
]);

lineChart('sampleChart', <?= json_encode($sampleLabels) ?>, [
    { label: 'Flow', data: <?= json_encode($sampleFlowSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Mercury', data: <?= json_encode($sampleMercurySeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Solids', data: <?= json_encode($sampleSolidsSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Water', data: <?= json_encode($sampleWaterSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Wax', data: <?= json_encode($sampleWaxSeries) ?>, borderWidth: 2, tension: 0.25 }
]);

lineChart('gasTestChart', <?= json_encode($gasTestLabels) ?>, [
    { label: 'Mercury', data: <?= json_encode($gasMercurySeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Benzene', data: <?= json_encode($gasBenzeneSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'LEL', data: <?= json_encode($gasLelSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'H2S', data: <?= json_encode($gasH2sSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'O2', data: <?= json_encode($gasO2Series) ?>, borderWidth: 2, tension: 0.25 }
]);

lineChart('solidWasteChart', <?= json_encode($solidWasteLabels) ?>, [
    { label: 'Amount', data: <?= json_encode($solidWasteAmountSeries) ?>, borderWidth: 2, tension: 0.25 },
    { label: 'Diff (min)', data: <?= json_encode($solidWasteDiffSeries) ?>, borderWidth: 2, tension: 0.25 }
]);
</script>

</body>
</html>