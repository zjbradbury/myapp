<?php
require_once "config.php";
requireRole(['admin', 'operator', 'viewer']);

$canEdit = in_array(currentRole(), ['admin', 'operator'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['monitor_form'])) {
    $form = $_POST['monitor_form'] ?? '';

    if ($form === 'master') {
        setSetting($pdo, 'monitor_master', isset($_POST['monitor_master']) ? '1' : '0');

        $refresh = isset($_POST['monitor_refresh_seconds']) ? (int) $_POST['monitor_refresh_seconds'] : 30;
        if ($refresh < 5) {
            $refresh = 5;
        }
        if ($refresh > 300) {
            $refresh = 300;
        }

        setSetting($pdo, 'monitor_refresh_seconds', (string) $refresh);
    }

    if ($form === 'item') {
        $key = trim($_POST['monitor_key'] ?? '');
        $allowed = ['nozzle', 'tricanter', 'solid_waste', 'sample', 'gas_test', 'project_flow'];

        if (in_array($key, $allowed, true)) {
            setSetting($pdo, 'monitor_' . $key . '_enabled', isset($_POST['monitor_enabled']) ? '1' : '0');

            $minutes = isset($_POST['monitor_minutes']) ? (int) $_POST['monitor_minutes'] : 60;
            if ($minutes < 1) {
                $minutes = 1;
            }
            if ($minutes > 1440) {
                $minutes = 1440;
            }

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
    $projectFlow = tableExists($pdo, 'project_flow_logs') ? fetch_log_rows($pdo, 'project_flow_logs', $range, 'id DESC') : [];

    $latestNozzleOverall = fetch_latest_row($pdo, 'nozzle_logs') ?: [];
    $latestTricanterOverall = fetch_latest_row($pdo, 'tricanter_logs') ?: [];
    $latestSolidWasteOverall = fetch_latest_row($pdo, 'solid_waste_logs') ?: [];
    $latestSampleOverall = tableExists($pdo, 'sample_logs') ? (fetch_latest_row($pdo, 'sample_logs') ?: []) : [];
    $latestGasTestOverall = tableExists($pdo, 'gas_test_logs') ? (fetch_latest_row($pdo, 'gas_test_logs') ?: []) : [];
    $latestProjectFlowOverall = tableExists($pdo, 'project_flow_logs') ? (fetch_latest_row($pdo, 'project_flow_logs') ?: []) : [];

    $latestNozzle = $nozzle[0] ?? [];
    $latestTricanter = $tricanter[0] ?? [];
    $latestSolidWaste = $solidWaste[0] ?? [];
    $latestSample = $sample[0] ?? [];
    $latestGasTest = $gasTest[0] ?? [];
    $latestProjectFlow = $projectFlow[0] ?? [];
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

$gasMercurySeries = numeric_series($gasTest, 'mercury');
$gasBenzeneSeries = numeric_series($gasTest, 'benzene');
$gasLelSeries = numeric_series($gasTest, 'lel');
$gasH2sSeries = numeric_series($gasTest, 'h2s');
$gasO2Series = numeric_series($gasTest, 'o2');
$gasLabels = label_series($gasTest);

$projectFlowKpis = get_project_flow_kpis($pdo, $range);

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
    !empty($latestGasTestOverall) ||
    !empty($latestProjectFlowOverall)
) ? 'ONLINE' : 'NO DATA';

$lastNozzleStamp = trim(($latestNozzleOverall['log_date'] ?? '-') . ' ' . ($latestNozzleOverall['log_time'] ?? ''));
$lastTricanterStamp = trim(($latestTricanterOverall['log_date'] ?? '-') . ' ' . ($latestTricanterOverall['log_time'] ?? ''));
$lastSolidWasteStamp = trim(($latestSolidWasteOverall['log_date'] ?? '-') . ' ' . ($latestSolidWasteOverall['log_time'] ?? ''));
$lastSampleStamp = trim(($latestSampleOverall['log_date'] ?? '-') . ' ' . ($latestSampleOverall['log_time'] ?? ''));
$lastGasTestStamp = trim(($latestGasTestOverall['log_date'] ?? '-') . ' ' . ($latestGasTestOverall['log_time'] ?? ''));
$lastProjectFlowStamp = trim(($latestProjectFlowOverall['log_date'] ?? '-') . ' ' . ($latestProjectFlowOverall['log_time'] ?? ''));

$recordsLoaded = count($nozzle) + count($tricanter) + count($solidWaste) + count($sample) + count($gasTest) + count($projectFlow);
$rangeSummary = range_summary_text($range, 'Current shift block');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link rel="stylesheet" href="indexStyle.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php require_once "nav.php"; ?>

<div class="logo-row">
    <img src="MoombaTankCleaningLogoTransparent.PNG">
    <img src="Contract69TanksLogoTransparent.png">
</div>

    <div class="monitor-shell">
        <div class="monitor-toolbar">
            <div class="monitor-toolbar-left">
                <div class="monitor-heading">Monitoring</div>
                <div class="monitor-badge monitor-<?= strtolower(str_replace(' ', '-', $monitorData['master_state'])) ?>">
                    <?= h($monitorData['master_state']) ?>
                </div>
            </div>

            <form method="post" class="monitor-toolbar-right">
                <input type="hidden" name="monitor_form" value="master">

                <label class="switch-row">
                    <span>Master</span>
                    <input type="checkbox"
                        name="monitor_master"
                        <?= !empty($monitorData['master_enabled']) ? 'checked' : '' ?>
                        onchange="this.form.submit()">
                </label>

                <label class="timer-row">
                    <span>Refresh (sec)</span>
                    <input type="number"
                        name="monitor_refresh_seconds"
                        min="5"
                        max="300"
                        value="<?= (int) $monitorData['refresh_seconds'] ?>"
                        onchange="this.form.submit()"
                        onblur="this.form.submit()">
                </label>
            </form>
        </div>

        <div class="monitor-grid">
            <?php foreach ($monitorData['items'] as $key => $item): ?>
                <div class="monitor-item monitor-state-<?= strtolower(str_replace(' ', '-', $item['status'])) ?>">
                    <form method="post">
                        <input type="hidden" name="monitor_form" value="item">
                        <input type="hidden" name="monitor_key" value="<?= h($key) ?>">

                        <div class="monitor-item-top">
                            <strong><?= h($item['label']) ?></strong>

                            <label class="switch-row small">
                                <span>On</span>
                                <input type="checkbox"
                                    name="monitor_enabled"
                                    <?= !empty($item['enabled']) ? 'checked' : '' ?>
                                    onchange="this.form.submit()">
                            </label>
                        </div>

                        <div class="monitor-line">
                            <span class="monitor-label">Last Entry</span>
                            <span class="monitor-last-entry"><?= h($item['last_entry_display']) ?></span>
                        </div>

                        <div class="monitor-line">
                            <span class="monitor-label">Since Last</span>
                            <span class="monitor-since"
                                data-since-seconds="<?= $item['since_seconds'] === null ? '' : (int) $item['since_seconds'] ?>">
                                <?= h($item['since_text']) ?>
                            </span>
                        </div>

                        <div class="monitor-line">
                            <span class="monitor-label">Timer (min)</span>
                            <input type="number"
                                class="monitor-minutes"
                                name="monitor_minutes"
                                min="1"
                                max="1440"
                                value="<?= (int) $item['minutes'] ?>"
                                onchange="this.form.submit()"
                                onblur="this.form.submit()">
                        </div>

                        <div class="monitor-line">
                            <span class="monitor-label">Countdown</span>
                            <span class="monitor-countdown"
                                data-remaining-seconds="<?= $item['remaining_seconds'] === null ? '' : (int) $item['remaining_seconds'] ?>">
                                <?= h($item['countdown']) ?>
                            </span>
                        </div>

                        <div class="monitor-line">
                            <span class="monitor-label">Status</span>
                            <span class="monitor-status monitor-<?= strtolower(str_replace(' ', '-', $item['status'])) ?>">
                                <?= h($item['status']) ?>
                            </span>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="topbar">
        <div class="info-card">
            <div class="info-title">System Status</div>
            <div class="status-row">
                <div class="info-value <?= $systemStatus === 'ONLINE' ? 'status-online' : 'status-offline' ?>">
                    <?= h($systemStatus) ?>
                </div>

                <div class="last-entry-inline">
                    <div class="last-entry-heading">Last Entry</div>
                    <div class="last-entry-value">Nozzle: <?= h($lastNozzleStamp) ?></div>
                    <div class="last-entry-value">Tricanter: <?= h($lastTricanterStamp) ?></div>
                    <div class="last-entry-value">Solid Waste: <?= h($lastSolidWasteStamp) ?></div>
                    <div class="last-entry-value">Sample: <?= h($lastSampleStamp) ?></div>
                    <div class="last-entry-value">Gas Test: <?= h($lastGasTestStamp) ?></div>
                    <div class="last-entry-value">Project Flow: <?= h($lastProjectFlowStamp) ?></div>
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

            <?php render_dashboard_range_filter($range); ?>

            <?php if (($range['error'] ?? '') !== ''): ?>
                <div class="range-error"><?= h($range['error'] ?? '') ?></div>
            <?php elseif (!empty($range['used_default_shift'])): ?>
                <div class="range-active">Showing current 12 hour shift block</div>
            <?php elseif (!empty($range['active'])): ?>
                <div class="range-active">Filtering graphs and tables to selected range</div>
            <?php else: ?>
                <div class="range-active">Showing all available records</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid">

        <div class="panel">
            <div class="panel-head">
                <h2>Tricanter</h2>
                <div class="panel-actions">
                    <a class="btn" href="tricanter_list.php">View List</a>
                    <?php if ($canEdit): ?>
                        <a class="btn" href="tricanter_add.php">Add Record</a>
                    <?php endif; ?>
                </div>
            </div>

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
                    <?php if (!$tricanter): ?>
                        <tr>
                            <td colspan="11">No tricanter data in selected range.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tricanter as $r): ?>
                            <tr class="tri-row" data-id="<?= (int) $r['id'] ?>">
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
            <div class="panel-head">
                <h2>Solid Waste</h2>
                <div class="panel-actions">
                    <a class="btn" href="solid_waste_list.php">View List</a>
                    <?php if ($canEdit): ?>
                        <a class="btn" href="solid_waste_add.php">Add Record</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="kpis">
                <div class="kpi"><small>Latest Amount</small><b><?= fmt($latestSolidWaste['amount'] ?? null, 0) ?> KG</b></div>
                <div class="kpi"><small>Total Amount</small><b><?= fmt($solidWasteTotalAmount, 0) ?> KG</b></div>
                <div class="kpi"><small>Last Entry</small><b><?= !empty($latestSolidWaste['log_time']) ? h(date('H:i', strtotime($latestSolidWaste['log_time']))) : '-' ?></b></div>
            </div>

            <div class="chart-card">
                <div class="chart-title">Solid Waste Trends</div>
                <div class="chart-wrap"><canvas id="solidWasteCombinedChart"></canvas></div>
            </div>

            <div class="table">
                <table>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Amount</th>
                        <th>Diff (min)</th>
                        <th>Comments</th>
                    </tr>
                    <?php if (!$solidWaste): ?>
                        <tr>
                            <td colspan="5">No solid waste data in selected range.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($solidWaste as $r): ?>
                            <tr class="solid-row" data-id="<?= (int) $r['id'] ?>">
                                <td><?= h($r['log_date']) ?></td>
                                <td><?= h($r['log_time']) ?></td>
                                <td><?= fmt($r['amount'] ?? null, 0) ?> KG</td>
                                <td><?= isset($r['_diff_minutes']) && $r['_diff_minutes'] !== null ? fmt($r['_diff_minutes'], 0) : '-' ?></td>
                                <td><?= h($r['comments'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Nozzle</h2>
                <div class="panel-actions">
                    <a class="btn" href="nozzle_list.php">View List</a>
                    <?php if ($canEdit): ?>
                        <a class="btn" href="nozzle_add.php">Add Record</a>
                    <?php endif; ?>
                </div>
            </div>

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
                        <th>Date</th>
                        <th>Time</th>
                        <th>Nozzle</th>
                        <th>Flow</th>
                        <th>Pressure</th>
                        <th>Min</th>
                        <th>Max</th>
                        <th>RPM</th>
                    </tr>
                    <?php if (!$nozzle): ?>
                        <tr>
                            <td colspan="8">No nozzle data in selected range.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($nozzle as $r): ?>
                            <tr class="nozzle-row" data-id="<?= (int) $r['id'] ?>">
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

        <div class="panel">
            <div class="panel-head">
                <h2>Sample</h2>
                <div class="panel-actions">
                    <a class="btn" href="sample_list.php">View List</a>
                    <?php if ($canEdit): ?>
                        <a class="btn" href="sample_add.php">Add Record</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="kpis">
                <div class="kpi"><small>Location</small><b><?= h($latestSample['sample_location'] ?? '-') ?></b></div>
                <div class="kpi"><small>Nozzle</small><b><?= h($latestSample['nozzle'] ?? '-') ?></b></div>
                <div class="kpi"><small>Flow</small><b><?= fmt($latestSample['flow'] ?? null, 2) ?> M3/hr</b></div>
                <div class="kpi"><small>Mercury</small><b><?= fmt($latestSample['mercury'] ?? null, 3) ?> %</b></div>
                <div class="kpi"><small>Solids</small><b><?= fmt($latestSample['solids'] ?? null, 2) ?> %</b></div>
                <div class="kpi"><small>Water</small><b><?= fmt($latestSample['water'] ?? null, 2) ?> %</b></div>
                <div class="kpi"><small>Wax</small><b><?= fmt($latestSample['wax'] ?? null, 2) ?> %</b></div>
                <div class="kpi"><small>Operator</small><b><?= h($latestSample['operator'] ?? '-') ?></b></div>
            </div>

            <div class="table">
                <table>
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
                    <?php if (!$sample): ?>
                        <tr>
                            <td colspan="10">No sample data in selected range.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sample as $r): ?>
                            <tr class="sample-row" data-id="<?= (int) $r['id'] ?>">
                                <td><?= h($r['log_date']) ?></td>
                                <td><?= h($r['log_time']) ?></td>
                                <td><?= h($r['sample_location'] ?? '') ?></td>
                                <td><?= h($r['nozzle'] ?? '') ?></td>
                                <td><?= fmt($r['flow'] ?? null, 2) ?> M3/hr</td>
                                <td><?= fmt($r['mercury'] ?? null, 3) ?> %</td>
                                <td><?= fmt($r['solids'] ?? null, 2) ?> %</td>
                                <td><?= fmt($r['water'] ?? null, 2) ?> %</td>
                                <td><?= fmt($r['wax'] ?? null, 2) ?> %</td>
                                <td><?= h($r['operator'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="panel wide-panel">
            <div class="panel-head">
                <h2>Gas Test</h2>
                <div class="panel-actions">
                    <a class="btn" href="gas_test_list.php">View List</a>
                    <?php if ($canEdit): ?>
                        <a class="btn" href="gas_test_add.php">Add Record</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="kpis">
                <div class="kpi"><small>Device</small><b><?= h($latestGasTest['device'] ?? '-') ?></b></div>
                <div class="kpi"><small>Operator</small><b><?= h($latestGasTest['operator'] ?? '-') ?></b></div>
                <div class="kpi"><small>Location</small><b><?= h($latestGasTest['location'] ?? '-') ?></b></div>
                <div class="kpi"><small>Mercury</small><b><?= fmt($latestGasTest['mercury'] ?? null, 3) ?> µg/m³</b></div>
                <div class="kpi"><small>Benzene</small><b><?= fmt($latestGasTest['benzene'] ?? null, 2) ?> ppm</b></div>
                <div class="kpi"><small>LEL</small><b><?= fmt($latestGasTest['lel'] ?? null, 1) ?> %</b></div>
                <div class="kpi"><small>H2S</small><b><?= fmt($latestGasTest['h2s'] ?? null, 1) ?> ppm</b></div>
                <div class="kpi"><small>O2</small><b><?= fmt($latestGasTest['o2'] ?? null, 1) ?> %</b></div>
                <div class="kpi"><small>Area</small><b><?= h($latestGasTest['area_details'] ?? '-') ?></b></div>
            </div>

            <div class="chart-card">
                <div class="chart-title">Gas Test Trends</div>
                <div class="chart-wrap"><canvas id="gasTestCombinedChart"></canvas></div>
            </div>

            <div class="table">
                <table>
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
                    <?php if (!$gasTest): ?>
                        <tr>
                            <td colspan="13">No gas test data in selected range.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($gasTest as $r): ?>
                            <tr class="gas-row" data-id="<?= (int) $r['id'] ?>">
                                <td><?= h($r['log_date']) ?></td>
                                <td><?= h($r['log_time']) ?></td>
                                <td><?= h($r['device'] ?? '') ?></td>
                                <td><?= h($r['operator'] ?? '') ?></td>
                                <td><?= h($r['location'] ?? '') ?></td>
                                <td><?= h($r['area_details'] ?? '') ?></td>
                                <td><?= fmt($r['mercury'] ?? null, 3) ?> µg/m³</td>
                                <td><?= fmt($r['benzene'] ?? null, 2) ?> ppm</td>
                                <td><?= fmt($r['lel'] ?? null, 1) ?> %</td>
                                <td><?= fmt($r['h2s'] ?? null, 1) ?> ppm</td>
                                <td><?= fmt($r['o2'] ?? null, 1) ?> %</td>
                                <td><?= h($r['product_details'] ?? '') ?></td>
                                <td><?= h($r['action_taken'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>
        </div>


        <div class="panel wide-panel">
            <div class="panel-head">
                <h2>Project Flow</h2>
                <div class="panel-actions">
                    <a class="btn" href="project_flow_list.php">View List</a>
                    <?php if ($canEdit): ?>
                        <a class="btn" href="project_flow_add.php">Add Record</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="kpis">
                <div class="kpi"><small>Records</small><b><?= fmt($projectFlowKpis['count'] ?? 0, 0) ?></b></div>
                <div class="kpi"><small>Recovered Oil</small><b><?= fmt($projectFlowKpis['oil'] ?? null, 4) ?></b></div>
                <div class="kpi"><small>Recovered Water</small><b><?= fmt($projectFlowKpis['water'] ?? null, 4) ?></b></div>
                <div class="kpi"><small>Solid Waste</small><b><?= fmt($projectFlowKpis['solid_waste'] ?? null, 4) ?></b></div>
                <div class="kpi"><small>Tricanter</small><b><?= fmt($projectFlowKpis['tricanter'] ?? null, 4) ?></b></div>
                <div class="kpi"><small>Nozzle</small><b><?= fmt($projectFlowKpis['nozzle'] ?? null, 4) ?></b></div>
            </div>

            <div class="table">
                <table>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Recovered Oil</th>
                        <th>Recovered Water</th>
                        <th>Solid Waste</th>
                        <th>Tricanter</th>
                        <th>Nozzle</th>
                        <th>Comments</th>
                    </tr>
                    <?php if (!$projectFlow): ?>
                        <tr>
                            <td colspan="8">No project flow data in selected range.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($projectFlow as $r): ?>
                            <tr class="project-flow-row" data-id="<?= (int) $r['id'] ?>">
                                <td><?= h($r['log_date']) ?></td>
                                <td><?= h($r['log_time']) ?></td>
                                <td><?= fmt($r['total_recovered_oil'] ?? null, 4) ?></td>
                                <td><?= fmt($r['total_recovered_water'] ?? null, 4) ?></td>
                                <td><?= fmt($r['total_solid_waste'] ?? null, 4) ?></td>
                                <td><?= fmt($r['total_tricanter'] ?? null, 4) ?></td>
                                <td><?= fmt($r['total_nozzle'] ?? null, 4) ?></td>
                                <td><?= h($r['comments'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>
        </div>

    </div>

    <script>
        const dashboardCharts = {};

        function flashRows(selector, storageKey) {
    let last = parseInt(localStorage.getItem(storageKey) || '0', 10);
    let max = last;

    const rows = Array.from(document.querySelectorAll(selector));

    // detect scroll container
    const table = rows[0]?.closest('.table');
    const container = table || null;
    const userAtTop = container ? container.scrollTop < 10 : true;

    rows.forEach(row => {
        const id = parseInt(row.dataset.id || '0', 10);

        if (id > last) {

            // 🔥 APPLY NEW ANIMATION
            row.classList.add('row-new');

            requestAnimationFrame(() => {
                row.classList.add('expand');
            });
        }

        if (id > max) max = id;
    });

    // 🔥 AUTO SCROLL ONLY IF USER IS AT TOP
    if (container && userAtTop) {
        container.scrollTop = 0;
    }

    localStorage.setItem(storageKey, String(max));
}

        function runFlashers() {
            flashRows('.nozzle-row', 'nLast');
            flashRows('.tri-row', 'tLast');
            flashRows('.solid-row', 'sLast');
            flashRows('.sample-row', 'sampleLast');
            flashRows('.gas-row', 'gasLast');
            flashRows('.project-flow-row', 'projectFlowLast');
        }

        function destroyExistingCharts() {
            Object.keys(dashboardCharts).forEach(key => {
                if (dashboardCharts[key]) {
                    dashboardCharts[key].destroy();
                    dashboardCharts[key] = null;
                }
            });
        }

        function makeCombinedChart(canvasId, labels, datasets) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            const valid = datasets.filter(ds => Array.isArray(ds.data) && ds.data.length > 0);
            if (valid.length === 0) return;

            if (dashboardCharts[canvasId]) {
                dashboardCharts[canvasId].destroy();
            }

            dashboardCharts[canvasId] = new Chart(canvas, {
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
                                font: {
                                    size: 11
                                }
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

        function initCharts() {
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

            makeCombinedChart('gasTestCombinedChart', <?= json_encode($gasLabels) ?>, [
                { label: 'Mercury', data: <?= json_encode($gasMercurySeries) ?>, color: '#00ffff', axis: 'y1' },
                { label: 'Benzene', data: <?= json_encode($gasBenzeneSeries) ?>, color: '#ffd24d', axis: 'y2' },
                { label: 'LEL', data: <?= json_encode($gasLelSeries) ?>, color: '#6ee7a1', axis: 'y3' },
                { label: 'H2S', data: <?= json_encode($gasH2sSeries) ?>, color: '#c8a7ff', axis: 'y4' },
                { label: 'O2', data: <?= json_encode($gasO2Series) ?>, color: '#ff7e67', axis: 'y5' }
            ]);
        }

        function formatSince(seconds) {
            if (seconds === '' || seconds === null || isNaN(seconds)) return 'No data';
            seconds = parseInt(seconds, 10);

            if (seconds < 60) return seconds + 's ago';

            if (seconds < 3600) {
                const mins = Math.floor(seconds / 60);
                const secs = seconds % 60;
                return mins + 'm ' + secs + 's ago';
            }

            if (seconds < 86400) {
                const hrs = Math.floor(seconds / 3600);
                const mins = Math.floor((seconds % 3600) / 60);
                return hrs + 'h ' + mins + 'm ago';
            }

            const days = Math.floor(seconds / 86400);
            const hrs = Math.floor((seconds % 86400) / 3600);
            return days + 'd ' + hrs + 'h ago';
        }

        function formatCountdown(seconds) {
            if (seconds === '' || seconds === null || isNaN(seconds)) return '--';
            seconds = parseInt(seconds, 10);

            if (seconds <= 0) return 'OVERDUE';

            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;

            if (hours > 0) {
                return String(hours).padStart(2, '0') + ':' +
                    String(minutes).padStart(2, '0') + ':' +
                    String(secs).padStart(2, '0');
            }

            return String(minutes).padStart(2, '0') + ':' +
                String(secs).padStart(2, '0');
        }

        function clearMonitorStateClasses(card) {
            card.classList.remove(
                'monitor-state-ok',
                'monitor-state-warning',
                'monitor-state-overdue',
                'monitor-state-alarm',
                'flash-yellow',
                'flash-red'
            );
        }

        function updateMonitorCardState(card, remaining) {
            const statusEl = card.querySelector('.monitor-status');
            if (!statusEl) return;

            const fixedStates = ['MASTER OFF', 'OFF', 'NO DATA', 'NOT SET UP'];
            const currentText = statusEl.textContent.trim();

            clearMonitorStateClasses(card);

            if (fixedStates.includes(currentText)) {
                if (currentText === 'NO DATA') {
                    card.classList.add('monitor-state-warning', 'flash-yellow');
                }
                return;
            }

            let status = 'OK';
            let statusClass = 'monitor-ok';

            if (remaining <= 0) {
                status = 'OVERDUE';
                statusClass = 'monitor-overdue';
                card.classList.add('monitor-state-overdue', 'flash-red');
            } else if (remaining <= 300) {
                status = 'WARNING';
                statusClass = 'monitor-warning';
                card.classList.add('monitor-state-warning', 'flash-yellow');
            } else {
                card.classList.add('monitor-state-ok');
            }

            statusEl.textContent = status;
            statusEl.className = 'monitor-status ' + statusClass;
        }

        function updateMonitorTimers() {
            document.querySelectorAll('.monitor-item').forEach(card => {
                const sinceEl = card.querySelector('.monitor-since');
                const countdownEl = card.querySelector('.monitor-countdown');

                if (sinceEl && sinceEl.dataset.sinceSeconds !== '') {
                    let since = parseInt(sinceEl.dataset.sinceSeconds, 10);
                    if (!isNaN(since)) {
                        since++;
                        sinceEl.dataset.sinceSeconds = since;
                        sinceEl.textContent = formatSince(since);
                    }
                }

                if (countdownEl && countdownEl.dataset.remainingSeconds !== '') {
                    let remaining = parseInt(countdownEl.dataset.remainingSeconds, 10);
                    if (!isNaN(remaining)) {
                        remaining--;
                        countdownEl.dataset.remainingSeconds = remaining;
                        countdownEl.textContent = formatCountdown(remaining);
                        updateMonitorCardState(card, remaining);
                    }
                } else {
                    updateMonitorCardState(card, null);
                }
            });
        }

        let liveRefreshInFlight = false;

        async function refreshDashboardLive() {
            if (liveRefreshInFlight) return;
            liveRefreshInFlight = true;

            try {
                const url = new URL(window.location.href);
                url.searchParams.set('_live_refresh', Date.now().toString());

                const response = await fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Cache-Control': 'no-cache'
                    },
                    cache: 'no-store'
                });

                if (!response.ok) {
                    throw new Error('Refresh failed');
                }

                const html = await response.text();
                const parser = new DOMParser();
                const nextDoc = parser.parseFromString(html, 'text/html');

                const nextMonitor = nextDoc.querySelector('.monitor-shell');
                const nextTopbar = nextDoc.querySelector('.topbar');
                const nextGrid = nextDoc.querySelector('.grid');

                const currentMonitor = document.querySelector('.monitor-shell');
                const currentTopbar = document.querySelector('.topbar');
                const currentGrid = document.querySelector('.grid');

                if (!nextMonitor || !nextTopbar || !nextGrid || !currentMonitor || !currentTopbar || !currentGrid) {
                    throw new Error('Live refresh selectors missing');
                }

                currentMonitor.replaceWith(nextMonitor);
                currentTopbar.replaceWith(nextTopbar);

                destroyExistingCharts();
                currentGrid.replaceWith(nextGrid);

                initCharts();
                runFlashers();
                updateMonitorTimers();
            } catch (error) {
                console.error(error);
            } finally {
                liveRefreshInFlight = false;
            }
        }

        runFlashers();
        initCharts();
        updateMonitorTimers();
        setInterval(updateMonitorTimers, 1000);
        setInterval(refreshDashboardLive, 30000);
    </script>

</body>

</html>