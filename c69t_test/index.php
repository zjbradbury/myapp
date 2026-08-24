<?php
require_once "config.php";
requireRole(['admin', 'operator', 'viewer']);

$canEdit = in_array(currentRole(), ['admin', 'operator'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['monitor_form'])) {
    if (!$canEdit) {
        http_response_code(403);
        die('Access denied. Operator or administrator access required.');
    }

    $form = $_POST['monitor_form'] ?? '';

    if ($form === 'master') {
        setSetting($pdo, 'monitor_master', isset($_POST['monitor_master']) ? '1' : '0');

        $refresh = isset($_POST['monitor_refresh_seconds']) ? (int) $_POST['monitor_refresh_seconds'] : 30;
        $refresh = max(5, min(300, $refresh));
        setSetting($pdo, 'monitor_refresh_seconds', (string) $refresh);
    }

    if ($form === 'item') {
        $key = trim($_POST['monitor_key'] ?? '');
        $allowed = ['nozzle', 'tricanter', 'solid_waste', 'sample', 'gas_test', 'project_flow', 'pump_values', 'nitrogen'];

        if (in_array($key, $allowed, true)) {
            setSetting($pdo, 'monitor_' . $key . '_enabled', isset($_POST['monitor_enabled']) ? '1' : '0');

            $minutes = isset($_POST['monitor_minutes']) ? (int) $_POST['monitor_minutes'] : 60;
            $minutes = max(1, min(1440, $minutes));
            setSetting($pdo, 'monitor_' . $key . '_minutes', (string) $minutes);
        }
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

define('C69T_DASHBOARD_LIBRARY_ONLY', true);
require_once __DIR__ . '/dashboard_data.php';

$range = get_range_filter_state();
$dashboard = build_dashboard_data($pdo, $range);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="c69t.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link rel="stylesheet" href="indexStyle.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="index.css">
</head>

<body>
    <?php require_once "nav.php"; ?>

    <div class="dashboard-shell">
        <div class="logo-row">
            <img src="MoombaTankCleaningLogoTransparent.PNG" alt="Moomba Tank Cleaning">
            <img src="Contract69TanksLogoTransparent.png" alt="Contract 69 Tanks">
        </div>

        <div id="monitorShell"><?= render_monitor_shell($dashboard['monitor']) ?></div>
        <div id="topbarWrap"><?= render_topbar($dashboard) ?></div>

        <div class="grid">
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Tricanter</h2>
                        <div class="panel-sub">Live process trend and latest operating values</div>
                    </div>
                    <div class="panel-actions">
                        <a class="btn" href="logs.php?table=tricanter">View Logs</a>
                        <?php if ($canEdit): ?><a class="btn" href="record.php?action=add&table=tricanter">Add Record</a><?php endif; ?>
                    </div>
                </div>

                <div id="tricanter-kpis" class="kpis"><?= $dashboard['panels']['tricanter']['kpis_html'] ?></div>

                <div class="chart-card">
                    <div class="chart-title">Tricanter Trends</div>
                    <div class="chart-wrap"><canvas id="tricanterCombinedChart"></canvas></div>
                </div>

                <div class="table">
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
                        <tbody id="tricanter-tbody"><?= $dashboard['panels']['tricanter']['rows_html'] ?></tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Solid Waste</h2>
                        <div class="panel-sub">Amount, cycle spacing, and live row updates</div>
                    </div>
                    <div class="panel-actions">
                        <a class="btn" href="logs.php?table=solid_waste">View Logs</a>
                        <?php if ($canEdit): ?><a class="btn" href="record.php?action=add&table=solid_waste">Add Record</a><?php endif; ?>
                    </div>
                </div>

                <div id="solid-waste-kpis" class="kpis"><?= $dashboard['panels']['solid_waste']['kpis_html'] ?></div>

                <div class="chart-card">
                    <div class="chart-title">Solid Waste Trends</div>
                    <div class="chart-wrap"><canvas id="solidWasteCombinedChart"></canvas></div>
                </div>

                <div class="table">
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
                        <tbody id="solid-waste-tbody"><?= $dashboard['panels']['solid_waste']['rows_html'] ?></tbody>
                    </table>
                </div>
            </div>


            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Recovered Water Pump</h2>
                        <div class="panel-sub">Pump start and stop levels with time between every result</div>
                    </div>
                    <div class="panel-actions">
                        <a class="btn" href="logs.php?table=recovered_water">View Logs</a>
                        <?php if ($canEdit): ?><a class="btn" href="record.php?action=add&table=recovered_water">Add Record</a><?php endif; ?>
                    </div>
                </div>

                <div id="recovered-water-kpis" class="kpis"><?= $dashboard['panels']['recovered_water']['kpis_html'] ?></div>

                <div class="chart-card">
                    <div class="chart-title">Recovered Water Pump Trends</div>
                    <div class="chart-wrap"><canvas id="recoveredWaterCombinedChart"></canvas></div>
                </div>

                <div class="table">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Start Level</th>
                                <th>Stop Level</th>
                                <th>Diff (min)</th>
                                <th>Comments</th>
                            </tr>
                        </thead>
                        <tbody id="recovered-water-tbody"><?= $dashboard['panels']['recovered_water']['rows_html'] ?></tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Nozzle</h2>
                        <div class="panel-sub">Live nozzle pressure, flow, angle, and RPM trend</div>
                    </div>
                    <div class="panel-actions">
                        <a class="btn" href="logs.php?table=nozzle">View Logs</a>
                        <?php if ($canEdit): ?><a class="btn" href="record.php?action=add&table=nozzle">Add Record</a><?php endif; ?>
                    </div>
                </div>

                <div id="nozzle-kpis" class="kpis"><?= $dashboard['panels']['nozzle']['kpis_html'] ?></div>

                <div class="chart-card">
                    <div class="chart-title">Nozzle Trends</div>
                    <div class="chart-wrap"><canvas id="nozzleCombinedChart"></canvas></div>
                </div>

                <div class="table">
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
                        <tbody id="nozzle-tbody"><?= $dashboard['panels']['nozzle']['rows_html'] ?></tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Sample</h2>
                        <div class="panel-sub">Latest field sample snapshot and filtered records</div>
                    </div>
                    <div class="panel-actions">
                        <a class="btn" href="logs.php?table=sample">View Logs</a>
                        <?php if ($canEdit): ?><a class="btn" href="record.php?action=add&table=sample">Add Record</a><?php endif; ?>
                    </div>
                </div>

                <div id="sample-kpis" class="kpis"><?= $dashboard['panels']['sample']['kpis_html'] ?></div>

                <div class="table">
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
                        <tbody id="sample-tbody"><?= $dashboard['panels']['sample']['rows_html'] ?></tbody>
                    </table>
                </div>
            </div>

            <div class="panel wide-panel">
                <div class="panel-head">
                    <div>
                        <h2>Gas Test</h2>
                        <div class="panel-sub">Gas readings and filtered table</div>
                    </div>
                    <div class="panel-actions">
                        <a class="btn" href="logs.php?table=gas_test">View Logs</a>
                        <?php if ($canEdit): ?><a class="btn" href="record.php?action=add&table=gas_test">Add Record</a><?php endif; ?>
                    </div>
                </div>

                <div id="gas-test-kpis" class="kpis"><?= $dashboard['panels']['gas_test']['kpis_html'] ?></div>


                <div class="table">
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
                        <tbody id="gas-test-tbody"><?= $dashboard['panels']['gas_test']['rows_html'] ?></tbody>
                    </table>
                </div>
            </div>

            <div class="panel wide-panel">
                <div class="panel-head">
                    <div>
                        <h2>Project Flow</h2>
                        <div class="panel-sub">Totals for selected date/time range</div>
                    </div>
                    <div class="panel-actions">
                        <a class="btn" href="logs.php?table=project_flow">View Logs</a>
                        <?php if ($canEdit): ?><a class="btn" href="record.php?action=add&table=project_flow">Add Record</a><?php endif; ?>
                    </div>
                </div>

                <div id="project-flow-kpis" class="kpis"><?= $dashboard['panels']['project_flow']['kpis_html'] ?></div>

                <div class="table">
                    <table>
                        <thead>
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
                        </thead>
                        <tbody id="project-flow-tbody"><?= $dashboard['panels']['project_flow']['rows_html'] ?></tbody>
                    </table>
                </div>
            </div>

            <div class="panel wide-panel">
                <div class="panel-head">
                    <div>
                        <h2>Pump Values</h2>
                        <div class="panel-sub">Pump statuses, feedback, and live pressure trends</div>
                    </div>
                    <div class="panel-actions">
                        <a class="btn" href="logs.php?table=pump_values">View Logs</a>
                        <?php if ($canEdit): ?><a class="btn" href="record.php?action=add&table=pump_values">Add Record</a><?php endif; ?>
                    </div>
                </div>

                <div id="pump-values-kpis" class="kpis"><?= $dashboard['panels']['pump_values']['kpis_html'] ?></div>

                <div class="chart-card">
                    <div class="chart-title">Pump Pressure Trends</div>
                    <div class="chart-wrap"><canvas id="pumpValuesPressureChart"></canvas></div>
                </div>

                <div class="table">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>SP1 Status</th>
                                <th>SP2 Status</th>
                                <th>SP3 Status</th>
                                <th>SP2 Feedback</th>
                                <th>SP2 Inlet</th>
                                <th>SP2 Outlet</th>
                                <th>FP Status</th>
                                <th>FP Feedback</th>
                                <th>FP Inlet</th>
                                <th>FP Outlet</th>
                                <th>BP Status</th>
                                <th>BP Feedback</th>
                                <th>BP Inlet</th>
                                <th>BP Outlet</th>
                            </tr>
                        </thead>
                        <tbody id="pump-values-tbody"><?= $dashboard['panels']['pump_values']['rows_html'] ?></tbody>
                    </table>
                </div>
            </div>

            <div class="panel wide-panel">
                <div class="panel-head">
                    <div>
                        <h2>Nitrogen</h2>
                        <div class="panel-sub">Nitrogen generator status, purity, pressure, temperature, and O2 readings</div>
                    </div>
                    <div class="panel-actions">
                        <a class="btn" href="logs.php?table=nitrogen">View Logs</a>
                        <?php if ($canEdit): ?><a class="btn" href="record.php?action=add&table=nitrogen">Add Record</a><?php endif; ?>
                    </div>
                </div>

                <div id="nitrogen-kpis" class="kpis"><?= $dashboard['panels']['nitrogen']['kpis_html'] ?></div>

                <div class="chart-card">
                    <div class="chart-title">Nitrogen Trends</div>
                    <div class="chart-wrap"><canvas id="nitrogenCombinedChart"></canvas></div>
                </div>

                <div class="table">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Active</th>
                                <th>Trip</th>
                                <th>Outlet Flow</th>
                                <th>Outlet Purity</th>
                                <th>Inlet Pressure</th>
                                <th>Outlet Pressure</th>
                                <th>Pre Heat Temp</th>
                                <th>Post Heat Temp</th>
                                <th>Interior O2</th>
                                <th>Tank Internal O2</th>
                                <th>Comments</th>
                            </tr>
                        </thead>
                        <tbody id="nitrogen-tbody"><?= $dashboard['panels']['nitrogen']['rows_html'] ?></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const chartPalette = {
            'Flow': '#00e5ff',
            'Pressure': '#ffd24d',
            'Min Deg': '#6ee7a1',
            'Max Deg': '#c8a7ff',
            'RPM': '#ff7e67',
            'Bowl Speed': '#00e5ff',
            'Screw Speed': '#ffd24d',
            'Bowl RPM': '#c8a7ff',
            'Screw RPM': '#ff9bd6',
            'Impeller': '#b6ff7a',
            'Feed Rate': '#00ff88',
            'Torque': '#ff7e67',
            'Temp': '#ffb36b',
            'Amount': '#00ff88',
            'Start Level': '#00e5ff',
            'Stop Level': '#ff9bd6',
            'Diff (min)': '#ffd24d',
            'Mercury': '#00e5ff',
            'Benzene': '#ffd24d',
            'LEL': '#6ee7a1',
            'H2S': '#c8a7ff',
            'O2': '#ff7e67',
            'Suction Inlet Pressure': '#00e5ff',
            'Suction Outlet Pressure': '#7dd3fc',
            'Feed Inlet Pressure': '#ffd24d',
            'Feed Outlet Pressure': '#f59e0b',
            'Booster Inlet Pressure': '#6ee7a1',
            'Booster Outlet Pressure': '#22c55e',
            'Outlet Flow': '#00e5ff',
            'Outlet Purity': '#ffd24d',
            'Inlet Pressure': '#7dd3fc',
            'Outlet Pressure': '#f59e0b',
            'Pre Heat Temp': '#ffb36b',
            'Post Heat Temp': '#ff7e67',
            'Interior O2': '#c8a7ff',
            'Tank Internal O2': '#ec4899'
        };

        const initialPanels = <?= json_encode($dashboard['panels'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const charts = {};

        const tricanterStatusHighlightPlugin = {
            id: 'tricanterStatusHighlight',
            beforeDatasetsDraw(chart, args, pluginOptions) {
                const statusData = pluginOptions?.statusData || [];
                if (!statusData.length || !chart.chartArea) return;

                const {
                    ctx,
                    chartArea,
                    scales
                } = chart;
                const xScale = scales.x;
                if (!xScale) return;

                ctx.save();
                ctx.fillStyle = 'rgba(239, 68, 68, 0.22)';

                statusData.forEach((status, index) => {
                    if (status === null || status === '' || typeof status === 'undefined') return;
                    const alertStatus = Number(pluginOptions?.alertStatus ?? 1);
                    const statusMatches = Number(status) === alertStatus;
                    const shouldAlert = pluginOptions?.alertWhenNotEqual ? !statusMatches : statusMatches;
                    if (!shouldAlert) return;

                    const center = xScale.getPixelForValue(index);
                    const previous = index > 0 ?
                        xScale.getPixelForValue(index - 1) :
                        center - ((xScale.getPixelForValue(index + 1) || center + 12) - center);
                    const next = index < statusData.length - 1 ?
                        xScale.getPixelForValue(index + 1) :
                        center + (center - previous);

                    const left = Math.max(chartArea.left, center - Math.abs(center - previous) / 2);
                    const right = Math.min(chartArea.right, center + Math.abs(next - center) / 2);

                    ctx.fillRect(left, chartArea.top, Math.max(2, right - left), chartArea.bottom - chartArea.top);
                });

                ctx.restore();
            }
        };

        Chart.register(tricanterStatusHighlightPlugin);

        function validDatasets(datasets) {
            return (datasets || []).filter(ds => Array.isArray(ds.data) && ds.data.length > 0);
        }

        function normaliseSeries(data) {
            const cleaned = (data || []).map(value => {
                if (
                    value === null ||
                    typeof value === 'undefined' ||
                    value === '' ||
                    String(value).trim().toLowerCase() === 'null' ||
                    String(value).trim().toLowerCase() === 'nan' ||
                    Number.isNaN(Number(value))
                ) {
                    return null;
                }

                return Number(value);
            });

            const numeric = cleaned.filter(value => value !== null);

            if (!numeric.length) {
                return cleaned;
            }

            const min = Math.min(...numeric);
            const max = Math.max(...numeric);

            if (max === min) {
                return cleaned.map(value => value === null ? null : 50);
            }

            return cleaned.map(value => {
                if (value === null) {
                    return null;
                }

                return ((value - min) / (max - min)) * 100;
            });
        }

        function chartDatasetObject(ds) {
            return {
                label: ds.label,
                data: normaliseSeries(ds.data),
                rawData: ds.data,
                borderColor: chartPalette[ds.label] || '#8fd3ff',
                backgroundColor: 'transparent',
                borderWidth: 2,
                tension: 0.25,
                pointRadius: 1,
                pointHoverRadius: 4,
                pointHitRadius: 12,
                spanGaps: false
            };
        }

        function makeChart(canvasId, config) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return null;

            const usable = validDatasets(config.datasets || []);
            if (!usable.length) return null;

            return new Chart(canvas, {
                type: 'line',
                data: {
                    labels: config.labels || [],
                    datasets: usable.map(chartDatasetObject)
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: {
                        mode: 'nearest',
                        intersect: false
                    },
                    plugins: {
                        tricanterStatusHighlight: {
                            statusData: config.status || [],
                            alertStatus: config.alertStatus ?? 1,
                            alertWhenNotEqual: config.alertWhenNotEqual ?? false
                        },
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
                                label: function(context) {
                                    const ds = context.dataset || {};
                                    const rawData = ds.rawData || [];
                                    const idx = context.dataIndex;
                                    const rawValue = rawData[idx];

                                    if (rawValue === null || rawValue === '' || typeof rawValue === 'undefined') {
                                        return ds.label + ': -';
                                    }

                                    return ds.label + ': ' + rawValue;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: false,
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            display: false,
                            min: 0,
                            max: 100,
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        function updateChart(chart, config) {
            if (!chart || !config) return;
            const usable = validDatasets(config.datasets || []);
            chart.data.labels = config.labels || [];
            chart.data.datasets = usable.map(chartDatasetObject);
            chart.options.plugins.tricanterStatusHighlight.statusData = config.status || [];
            chart.options.plugins.tricanterStatusHighlight.alertStatus = config.alertStatus ?? 1;
            chart.options.plugins.tricanterStatusHighlight.alertWhenNotEqual = config.alertWhenNotEqual ?? false;
            chart.update('none');
        }

        function updateContainer(id, html) {
            const el = document.getElementById(id);
            if (el && typeof html === 'string') {
                el.innerHTML = html;
            }
        }

        function markNewRows(tbodyId, storageKey) {
            const tbody = document.getElementById(tbodyId);
            if (!tbody) return;

            let lastSeen = parseInt(localStorage.getItem(storageKey) || '0', 10);
            let maxSeen = lastSeen;

            tbody.querySelectorAll('tr[data-id]').forEach(row => {
                const id = parseInt(row.dataset.id || '0', 10);
                if (id > lastSeen) {
                    row.classList.add('row-new');
                }
                if (id > maxSeen) {
                    maxSeen = id;
                }
            });

            localStorage.setItem(storageKey, String(maxSeen));
        }

        function updateTbody(id, html, storageKey) {
            const tbody = document.getElementById(id);
            if (!tbody || typeof html !== 'string') return;
            tbody.innerHTML = html;
            markNewRows(id, storageKey);
        }

        function formatSince(seconds) {
            if (seconds === '' || seconds === null || Number.isNaN(Number(seconds))) return 'No data';
            seconds = parseInt(seconds, 10);

            if (seconds < 60) return seconds + 's ago';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ' + (seconds % 60) + 's ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm ago';
            return Math.floor(seconds / 86400) + 'd ' + Math.floor((seconds % 86400) / 3600) + 'h ago';
        }

        function formatCountdown(seconds) {
            if (seconds === '' || seconds === null || Number.isNaN(Number(seconds))) return '--';
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

            return String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
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

            const currentText = statusEl.textContent.trim();
            const fixedStates = ['MASTER OFF', 'OFF', 'NO DATA', 'NOT SET UP'];

            clearMonitorStateClasses(card);

            if (fixedStates.includes(currentText)) {
                if (currentText === 'NO DATA') {
                    card.classList.add('monitor-state-warning', 'flash-yellow');
                }
                return;
            }

            let status = 'OK';
            let className = 'monitor-status monitor-ok';

            if (remaining <= 0) {
                status = 'OVERDUE';
                className = 'monitor-status monitor-overdue';
                card.classList.add('monitor-state-overdue', 'flash-red');
            } else if (remaining <= 300) {
                status = 'WARNING';
                className = 'monitor-status monitor-warning';
                card.classList.add('monitor-state-warning', 'flash-yellow');
            } else {
                card.classList.add('monitor-state-ok');
            }

            statusEl.textContent = status;
            statusEl.className = className;
        }

        function tickMonitorTimers() {
            document.querySelectorAll('.monitor-item').forEach(card => {
                const sinceEl = card.querySelector('.monitor-since');
                const countdownEl = card.querySelector('.monitor-countdown');

                if (sinceEl && sinceEl.dataset.sinceSeconds !== '') {
                    let since = parseInt(sinceEl.dataset.sinceSeconds, 10);
                    if (!Number.isNaN(since)) {
                        since += 1;
                        sinceEl.dataset.sinceSeconds = String(since);
                        sinceEl.textContent = formatSince(since);
                    }
                }

                if (countdownEl && countdownEl.dataset.remainingSeconds !== '') {
                    let remaining = parseInt(countdownEl.dataset.remainingSeconds, 10);
                    if (!Number.isNaN(remaining)) {
                        remaining -= 1;
                        countdownEl.dataset.remainingSeconds = String(remaining);
                        countdownEl.textContent = formatCountdown(remaining);
                        updateMonitorCardState(card, remaining);
                    }
                } else {
                    updateMonitorCardState(card, null);
                }
            });
        }

        function buildAjaxUrl() {
            const current = new URL(window.location.href);
            const url = new URL('dashboard_data.php', current.origin + current.pathname.replace(/[^/]*$/, ''));

            const start = current.searchParams.get('start');
            const end = current.searchParams.get('end');
            const quick = current.searchParams.get('quick');

            if (start !== null && start !== '') url.searchParams.set('start', start);
            if (end !== null && end !== '') url.searchParams.set('end', end);
            if (quick !== null && quick !== '') url.searchParams.set('quick', quick);

            return url.toString();
        }

        async function fetchDashboardUpdate() {
            const response = await fetch(buildAjaxUrl(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-store'
            });

            if (!response.ok) throw new Error('Refresh failed');
            return response.json();
        }

        function applyPayload(payload) {
            if (!payload || !payload.ok) return;

            updateContainer('monitorShell', payload.monitor_html);
            updateContainer('topbarWrap', payload.topbar_html);

            if (payload.panels?.tricanter) {
                updateContainer('tricanter-kpis', payload.panels.tricanter.kpis_html);
                updateTbody('tricanter-tbody', payload.panels.tricanter.rows_html, 'triLastSeen');
                updateChart(charts.tricanter, payload.panels.tricanter.chart);
            }

            if (payload.panels?.solid_waste) {
                updateContainer('solid-waste-kpis', payload.panels.solid_waste.kpis_html);
                updateTbody('solid-waste-tbody', payload.panels.solid_waste.rows_html, 'solidLastSeen');
                updateChart(charts.solidWaste, payload.panels.solid_waste.chart);
            }

            if (payload.panels?.recovered_water) {
                updateContainer('recovered-water-kpis', payload.panels.recovered_water.kpis_html);
                updateTbody('recovered-water-tbody', payload.panels.recovered_water.rows_html, 'recoveredWaterLastSeen');
                updateChart(charts.recoveredWater, payload.panels.recovered_water.chart);
            }

            if (payload.panels?.nozzle) {
                updateContainer('nozzle-kpis', payload.panels.nozzle.kpis_html);
                updateTbody('nozzle-tbody', payload.panels.nozzle.rows_html, 'nozzleLastSeen');
                updateChart(charts.nozzle, payload.panels.nozzle.chart);
            }

            if (payload.panels?.sample) {
                updateContainer('sample-kpis', payload.panels.sample.kpis_html);
                updateTbody('sample-tbody', payload.panels.sample.rows_html, 'sampleLastSeen');
            }

            if (payload.panels?.gas_test) {
                updateContainer('gas-test-kpis', payload.panels.gas_test.kpis_html);
                updateTbody('gas-test-tbody', payload.panels.gas_test.rows_html, 'gasLastSeen');
            }

            if (payload.panels?.project_flow) {
                updateContainer('project-flow-kpis', payload.panels.project_flow.kpis_html);
                updateTbody('project-flow-tbody', payload.panels.project_flow.rows_html, 'projectFlowLastSeen');
            }

            if (payload.panels?.pump_values) {
                updateContainer('pump-values-kpis', payload.panels.pump_values.kpis_html);
                updateTbody('pump-values-tbody', payload.panels.pump_values.rows_html, 'pumpValuesLastSeen');
                updateChart(charts.pumpValues, payload.panels.pump_values.chart);
            }

            if (payload.panels?.nitrogen) {
                updateContainer('nitrogen-kpis', payload.panels.nitrogen.kpis_html);
                updateTbody('nitrogen-tbody', payload.panels.nitrogen.rows_html, 'nitrogenLastSeen');
                updateChart(charts.nitrogen, payload.panels.nitrogen.chart);
            }
        }

        let refreshTimer = null;
        let refreshInFlight = false;

        function getRefreshSeconds() {
            const shell = document.querySelector('#monitorShell [data-refresh-seconds]');
            const secs = shell ? parseInt(shell.dataset.refreshSeconds || '30', 10) : 30;
            return Number.isNaN(secs) ? 30 : Math.max(5, secs);
        }

        function schedulePolling() {
            if (refreshTimer) {
                clearInterval(refreshTimer);
            }

            const ms = getRefreshSeconds() * 1000;

            refreshTimer = setInterval(async () => {
                if (refreshInFlight) return;
                refreshInFlight = true;

                try {
                    const payload = await fetchDashboardUpdate();
                    applyPayload(payload);
                } catch (err) {
                    console.error(err);
                } finally {
                    refreshInFlight = false;
                }
            }, ms);
        }

        charts.nozzle = makeChart('nozzleCombinedChart', initialPanels.nozzle.chart);
        charts.tricanter = makeChart('tricanterCombinedChart', initialPanels.tricanter.chart);
        charts.solidWaste = makeChart('solidWasteCombinedChart', initialPanels.solid_waste.chart);
        charts.recoveredWater = makeChart('recoveredWaterCombinedChart', initialPanels.recovered_water.chart);
        charts.pumpValues = makeChart('pumpValuesPressureChart', initialPanels.pump_values.chart);
        charts.nitrogen = makeChart('nitrogenCombinedChart', initialPanels.nitrogen.chart);

        markNewRows('tricanter-tbody', 'triLastSeen');
        markNewRows('solid-waste-tbody', 'solidLastSeen');
        markNewRows('recovered-water-tbody', 'recoveredWaterLastSeen');
        markNewRows('nozzle-tbody', 'nozzleLastSeen');
        markNewRows('sample-tbody', 'sampleLastSeen');
        markNewRows('gas-test-tbody', 'gasLastSeen');
        markNewRows('project-flow-tbody', 'projectFlowLastSeen');
        markNewRows('pump-values-tbody', 'pumpValuesLastSeen');
        markNewRows('nitrogen-tbody', 'nitrogenLastSeen');

        tickMonitorTimers();
        setInterval(tickMonitorTimers, 1000);
        schedulePolling();
    </script>
</body>

</html>
