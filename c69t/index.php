<?php
require_once "config.php";
requireRole(['admin', 'operator', 'viewer']);

$range = get_range_filter_state();
$monitorData = buildMonitoringData($pdo);

try {
    $nozzle = fetch_log_rows($pdo, 'nozzle_logs', $range, 'id DESC');
    $tricanter = fetch_log_rows($pdo, 'tricanter_logs', $range, 'id DESC');
    $solidWaste = fetch_log_rows($pdo, 'solid_waste_logs', $range, 'id DESC');

    $latestNozzleOverall = fetch_latest_row($pdo, 'nozzle_logs') ?: [];
    $latestTricanterOverall = fetch_latest_row($pdo, 'tricanter_logs') ?: [];
    $latestSolidWasteOverall = fetch_latest_row($pdo, 'solid_waste_logs') ?: [];

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
        $solidWasteTotalAmount += (float) $r['amount'];
    }
}

$systemStatus = (!empty($latestNozzleOverall) || !empty($latestTricanterOverall) || !empty($latestSolidWasteOverall)) ? 'ONLINE' : 'NO DATA';

$lastNozzleStamp = trim(($latestNozzleOverall['log_date'] ?? '-') . ' ' . ($latestNozzleOverall['log_time'] ?? ''));
$lastTricanterStamp = trim(($latestTricanterOverall['log_date'] ?? '-') . ' ' . ($latestTricanterOverall['log_time'] ?? ''));
$lastSolidWasteStamp = trim(($latestSolidWasteOverall['log_date'] ?? '-') . ' ' . ($latestSolidWasteOverall['log_time'] ?? ''));

$recordsLoaded = count($nozzle) + count($tricanter) + count($solidWaste);
$rangeSummary = range_summary_text($range, 'Current shift block');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <title>Dashboard</title>
    <link rel="stylesheet" href="indexStyle.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php require_once "nav.php"; ?>
    <h1>Dashboard</h1>

    <div class="monitor-shell">
        <div class="monitor-toolbar">
            <div class="monitor-toolbar-left">
                <div class="monitor-heading">Monitoring</div>
                <div id="master-status-badge" class="monitor-badge monitor-<?= strtolower(str_replace(' ', '-', $monitorData['master_state'])) ?>">
                    <?= h($monitorData['master_state']) ?>
                </div>
            </div>

            <div class="monitor-toolbar-right">
                <label class="switch-row">
                    <span>Master</span>
                    <input type="checkbox" id="monitor_master" <?= $monitorData['master_enabled'] ? 'checked' : '' ?>>
                </label>

                <label class="timer-row">
                    <span>Refresh (sec)</span>
                    <input type="number" id="monitor_refresh_seconds" min="5" max="300" value="<?= (int) $monitorData['refresh_seconds'] ?>">
                </label>
            </div>
        </div>

        <div class="monitor-grid" id="monitor-grid">
            <?php foreach ($monitorData['items'] as $key => $item): ?>
                <div class="monitor-item monitor-state-<?= strtolower(str_replace(' ', '-', $item['status'])) ?>" data-key="<?= h($key) ?>">
                    <div class="monitor-item-top">
                        <strong><?= h($item['label']) ?></strong>
                        <label class="switch-row small">
                            <span>On</span>
                            <input type="checkbox" class="monitor-enabled" data-key="<?= h($key) ?>" <?= $item['enabled'] ? 'checked' : '' ?>>
                        </label>
                    </div>

                    <div class="monitor-line">
                        <span class="monitor-label">Last Entry</span>
                        <span class="monitor-last-entry" id="last-entry-<?= h($key) ?>"><?= h($item['last_entry_display']) ?></span>
                    </div>

                    <div class="monitor-line">
                        <span class="monitor-label">Since Last</span>
                        <span class="monitor-since" id="since-<?= h($key) ?>" data-since-seconds="<?= $item['since_seconds'] === null ? '' : (int) $item['since_seconds'] ?>">
                            <?= h($item['since_text']) ?>
                        </span>
                    </div>

                    <div class="monitor-line">
                        <span class="monitor-label">Timer (min)</span>
                        <input type="number" class="monitor-minutes" data-key="<?= h($key) ?>" min="1" max="1440" value="<?= (int) $item['minutes'] ?>">
                    </div>

                    <div class="monitor-line">
                        <span class="monitor-label">Countdown</span>
                        <span class="monitor-countdown" id="countdown-<?= h($key) ?>" data-seconds="<?= $item['remaining_seconds'] === null ? '' : (int) $item['remaining_seconds'] ?>">
                            <?= h($item['countdown']) ?>
                        </span>
                    </div>

                    <div class="monitor-line">
                        <span class="monitor-label">Status</span>
                        <span class="monitor-status monitor-<?= strtolower(str_replace(' ', '-', $item['status'])) ?>" id="status-<?= h($key) ?>">
                            <?= h($item['status']) ?>
                        </span>
                    </div>
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

        let monitorIsSaving = false;
        let monitorReloadTimer = null;

        function formatCountdown(seconds) {
            if (seconds === null || seconds === '' || isNaN(seconds)) return '--';
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

        function formatElapsed(seconds) {
            if (seconds === null || seconds === '' || isNaN(seconds)) return 'No data';
            seconds = parseInt(seconds, 10);

            if (seconds < 60) return seconds + 's ago';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ' + (seconds % 60) + 's ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm ago';
            return Math.floor(seconds / 86400) + 'd ' + Math.floor((seconds % 86400) / 3600) + 'h ago';
        }

        function cleanClassesByPrefix(el, prefix) {
            [...el.classList].forEach(cls => {
                if (cls.startsWith(prefix)) {
                    el.classList.remove(cls);
                }
            });
        }

        function applyStatusClass(el, status) {
            cleanClassesByPrefix(el, 'monitor-');
            el.classList.add('monitor-' + status.toLowerCase().replace(/\s+/g, '-'));
        }

        function applyCardState(item, status) {
            cleanClassesByPrefix(item, 'monitor-state-');
            item.classList.remove('flash-yellow', 'flash-red');

            const state = status.toLowerCase().replace(/\s+/g, '-');
            item.classList.add('monitor-state-' + state);

            if (state === 'warning') {
                item.classList.add('flash-yellow');
            } else if (state === 'overdue' || state === 'alarm') {
                item.classList.add('flash-red');
            }
        }

        function recomputeMasterBadgeFromCards() {
            const masterToggle = document.getElementById('monitor_master');
            const badge = document.getElementById('master-status-badge');

            let state = masterToggle.checked ? 'OK' : 'MASTER OFF';

            if (masterToggle.checked) {
                document.querySelectorAll('.monitor-item').forEach(item => {
                    const status = item.querySelector('.monitor-status')?.textContent || 'OK';

                    if (status === 'OVERDUE') {
                        state = 'ALARM';
                    } else if ((status === 'WARNING' || status === 'NO DATA' || status === 'NOT SET UP') && state !== 'ALARM') {
                        state = 'WARNING';
                    }
                });
            }

            badge.textContent = state;
            badge.className = 'monitor-badge monitor-' + state.toLowerCase().replace(/\s+/g, '-');
        }

        function tickCountdowns() {
            document.querySelectorAll('.monitor-item').forEach(item => {
                const countdownEl = item.querySelector('.monitor-countdown');
                const sinceEl = item.querySelector('.monitor-since');
                const statusEl = item.querySelector('.monitor-status');

                if (sinceEl && sinceEl.dataset.sinceSeconds !== '') {
                    let since = parseInt(sinceEl.dataset.sinceSeconds, 10);
                    since++;
                    sinceEl.dataset.sinceSeconds = since;
                    sinceEl.textContent = formatElapsed(since);
                }

                if (!countdownEl || countdownEl.dataset.seconds === '') {
                    return;
                }

                let seconds = parseInt(countdownEl.dataset.seconds, 10);
                seconds--;
                countdownEl.dataset.seconds = seconds;
                countdownEl.textContent = formatCountdown(seconds);

                let status = 'OK';
                if (seconds <= 0) {
                    status = 'OVERDUE';
                } else if (seconds <= 300) {
                    status = 'WARNING';
                }

                statusEl.textContent = status;
                applyStatusClass(statusEl, status);
                applyCardState(item, status);
            });

            recomputeMasterBadgeFromCards();
        }

        function getMonitorPayload() {
            const payload = {
                monitor_master: document.getElementById('monitor_master').checked ? 1 : 0,
                monitor_refresh_seconds: document.getElementById('monitor_refresh_seconds').value || 30
            };

            document.querySelectorAll('.monitor-enabled').forEach(el => {
                payload['monitor_' + el.dataset.key + '_enabled'] = el.checked ? 1 : 0;
            });

            document.querySelectorAll('.monitor-minutes').forEach(el => {
                payload['monitor_' + el.dataset.key + '_minutes'] = el.value || 60;
            });

            return payload;
        }

        function updateMonitorUiImmediately() {
            const masterOn = document.getElementById('monitor_master').checked;

            document.querySelectorAll('.monitor-item').forEach(item => {
                const enabledEl = item.querySelector('.monitor-enabled');
                const countdownEl = item.querySelector('.monitor-countdown');
                const statusEl = item.querySelector('.monitor-status');

                let status = 'OK';

                if (!masterOn) {
                    status = 'MASTER OFF';
                    countdownEl.dataset.seconds = '';
                    countdownEl.textContent = '--';
                } else if (enabledEl && !enabledEl.checked) {
                    status = 'OFF';
                    countdownEl.dataset.seconds = '';
                    countdownEl.textContent = '--';
                } else {
                    const seconds = countdownEl.dataset.seconds;
                    if (seconds === '') {
                        status = statusEl.textContent || 'NO DATA';
                    } else {
                        const n = parseInt(seconds, 10);
                        if (n <= 0) status = 'OVERDUE';
                        else if (n <= 300) status = 'WARNING';
                        else status = 'OK';
                    }
                }

                statusEl.textContent = status;
                applyStatusClass(statusEl, status);
                applyCardState(item, status);
            });

            recomputeMasterBadgeFromCards();
        }

        async function loadMonitorStatus() {
            if (monitorIsSaving) return;

            try {
                const res = await fetch('monitor_status.php?_=' + Date.now());
                const data = await res.json();

                const badge = document.getElementById('master-status-badge');
                badge.textContent = data.master_state;
                badge.className = 'monitor-badge monitor-' + data.master_state.toLowerCase().replace(/\s+/g, '-');

                document.getElementById('monitor_master').checked = !!data.master_enabled;
                document.getElementById('monitor_refresh_seconds').value = data.refresh_seconds;

                Object.entries(data.items).forEach(([key, item]) => {
                    const cardEl = document.querySelector('.monitor-item[data-key="' + key + '"]');
                    const lastEntryEl = document.getElementById('last-entry-' + key);
                    const sinceEl = document.getElementById('since-' + key);
                    const countdownEl = document.getElementById('countdown-' + key);
                    const statusEl = document.getElementById('status-' + key);

                    const enabledEl = document.querySelector('.monitor-enabled[data-key="' + key + '"]');
                    const minutesEl = document.querySelector('.monitor-minutes[data-key="' + key + '"]');

                    if (enabledEl) enabledEl.checked = !!item.enabled;
                    if (minutesEl) minutesEl.value = item.minutes;
                    if (lastEntryEl) lastEntryEl.textContent = item.last_entry_display || 'No data';

                    if (sinceEl) {
                        sinceEl.dataset.sinceSeconds = item.since_seconds === null ? '' : item.since_seconds;
                        sinceEl.textContent = item.since_text || 'No data';
                    }

                    if (countdownEl) {
                        countdownEl.dataset.seconds = item.remaining_seconds === null ? '' : item.remaining_seconds;
                        countdownEl.textContent = item.countdown;
                    }

                    if (statusEl) {
                        statusEl.textContent = item.status;
                        statusEl.className = 'monitor-status monitor-' + item.status.toLowerCase().replace(/\s+/g, '-');
                    }

                    if (cardEl) applyCardState(cardEl, item.status);
                });
            } catch (err) {
                console.error('Monitor refresh failed', err);
            }
        }

        async function saveMonitorSettings() {
            monitorIsSaving = true;

            try {
                const res = await fetch('monitor_save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(getMonitorPayload())
                });

                const data = await res.json();

                if (!data.ok) {
                    alert('Failed to save monitoring settings');
                } else {
                    await loadMonitorStatus();
                }
            } catch (err) {
                console.error(err);
                alert('Failed to save monitoring settings');
            } finally {
                monitorIsSaving = false;
            }
        }

        function saveMonitorSettingsDebounced() {
            updateMonitorUiImmediately();

            if (monitorReloadTimer) {
                clearTimeout(monitorReloadTimer);
            }

            monitorReloadTimer = setTimeout(() => {
                saveMonitorSettings();
            }, 250);
        }

        document.getElementById('monitor_master').addEventListener('change', saveMonitorSettingsDebounced);
        document.getElementById('monitor_refresh_seconds').addEventListener('input', saveMonitorSettingsDebounced);
        document.getElementById('monitor_refresh_seconds').addEventListener('change', saveMonitorSettingsDebounced);

        document.querySelectorAll('.monitor-enabled').forEach(el => {
            el.addEventListener('change', saveMonitorSettingsDebounced);
        });

        document.querySelectorAll('.monitor-minutes').forEach(el => {
            el.addEventListener('input', saveMonitorSettingsDebounced);
            el.addEventListener('change', saveMonitorSettingsDebounced);
        });

        loadMonitorStatus();
        setInterval(tickCountdowns, 1000);
        setInterval(loadMonitorStatus, 15000);
    </script>

</body>

</html>