<?php
require_once "config.php";
requireRole(['admin', 'operator', 'viewer']);

$canEdit = in_array(currentRole(), ['admin', 'operator'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['monitor_form'])) {
    $form = $_POST['monitor_form'] ?? '';

    if ($form === 'master') {
        setSetting($pdo, 'monitor_master', isset($_POST['monitor_master']) ? '1' : '0');

        $refresh = isset($_POST['monitor_refresh_seconds']) ? (int) $_POST['monitor_refresh_seconds'] : 30;
        $refresh = max(5, min(300, $refresh));
        setSetting($pdo, 'monitor_refresh_seconds', (string) $refresh);
    }

    if ($form === 'item') {
        $key = trim($_POST['monitor_key'] ?? '');
        $allowed = ['nozzle', 'tricanter', 'solid_waste', 'sample', 'gas_test', 'project_flow', 'pump_values'];

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

function row_stamp(array $row): string
{
    $date = trim((string)($row['log_date'] ?? ''));
    $time = trim((string)($row['log_time'] ?? ''));
    return trim($date . ' ' . $time) ?: '-';
}

function monitor_status_slug(string $status): string
{
    return strtolower(str_replace(' ', '-', trim($status)));
}

function monitor_status_rank(string $status): int
{
    $map = [
        'OK' => 0,
        'MASTER OFF' => 0,
        'OFF' => 0,
        'DISABLED' => 0,
        'NOT SET UP' => 1,
        'NO DATA' => 2,
        'WARNING' => 3,
        'OVERDUE' => 4,
    ];

    return $map[trim($status)] ?? 0;
}

function monitor_has_issue(array $item): bool
{
    $status = strtoupper(trim((string)($item['status'] ?? '')));
    return in_array($status, ['WARNING', 'OVERDUE', 'NO DATA', 'NOT SET UP'], true);
}

function render_single_monitor_item(string $key, array $item): string
{
    ob_start();
    ?>
    <div class="monitor-item monitor-state-<?= h(monitor_status_slug((string)($item['status'] ?? 'OK'))) ?>">
        <form method="post">
            <input type="hidden" name="monitor_form" value="item">
            <input type="hidden" name="monitor_key" value="<?= h($key) ?>">

            <div class="monitor-item-top">
                <strong><?= h($item['label'] ?? $key) ?></strong>

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
                <span class="monitor-last-entry"><?= h($item['last_entry_display'] ?? '-') ?></span>
            </div>

            <div class="monitor-line">
                <span class="monitor-label">Since Last</span>
                <span class="monitor-since"
                    data-since-seconds="<?= ($item['since_seconds'] ?? null) === null ? '' : (int)$item['since_seconds'] ?>">
                    <?= h($item['since_text'] ?? 'No data') ?>
                </span>
            </div>

            <div class="monitor-line">
                <span class="monitor-label">Timer (min)</span>
                <input type="number"
                    class="monitor-minutes"
                    name="monitor_minutes"
                    min="1"
                    max="1440"
                    value="<?= (int)($item['minutes'] ?? 60) ?>"
                    onchange="this.form.submit()"
                    onblur="this.form.submit()">
            </div>

            <div class="monitor-line">
                <span class="monitor-label">Countdown</span>
                <span class="monitor-countdown"
                    data-remaining-seconds="<?= ($item['remaining_seconds'] ?? null) === null ? '' : (int)$item['remaining_seconds'] ?>">
                    <?= h($item['countdown'] ?? '--') ?>
                </span>
            </div>

            <div class="monitor-line">
                <span class="monitor-label">Status</span>
                <span class="monitor-status monitor-<?= h(monitor_status_slug((string)($item['status'] ?? 'OK'))) ?>">
                    <?= h($item['status'] ?? 'OK') ?>
                </span>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

function render_monitor_shell(array $monitorData): string
{
    $combinedKeys = ['nozzle', 'tricanter', 'project_flow', 'pump_values'];
    $combinedItems = [];
    $otherItems = [];
    $combinedIssueCount = 0;
    $combinedHighestRank = 0;

    foreach (($monitorData['items'] ?? []) as $key => $item) {
        if (in_array($key, $combinedKeys, true)) {
            $combinedItems[$key] = $item;
            $combinedHighestRank = max($combinedHighestRank, monitor_status_rank((string)($item['status'] ?? 'OK')));
            if (monitor_has_issue($item)) {
                $combinedIssueCount++;
            }
        } else {
            $otherItems[$key] = $item;
        }
    }

    $combinedOverall = 'OK';
    if ($combinedHighestRank >= 4) {
        $combinedOverall = 'OVERDUE';
    } elseif ($combinedHighestRank >= 3) {
        $combinedOverall = 'WARNING';
    } elseif ($combinedHighestRank >= 2) {
        $combinedOverall = 'NO DATA';
    } elseif ($combinedHighestRank >= 1) {
        $combinedOverall = 'NOT SET UP';
    }

    $combinedMeta = $combinedIssueCount > 0
        ? $combinedIssueCount . ' issue' . ($combinedIssueCount === 1 ? '' : 's') . ' in nozzle, tricanter, project flow, or pump values'
        : 'All process streams normal. Expand to access toggles, timers, and details.';

    ob_start();
    ?>
    <div class="monitor-shell-shell" data-refresh-seconds="<?= (int)($monitorData['refresh_seconds'] ?? 30) ?>">
        <div class="monitor-shell refined-shell">
            <div class="monitor-toolbar refined-toolbar">
                <div class="monitor-toolbar-left">
                    <div>
                        <div class="section-kicker">system supervision</div>
                        <div class="monitor-heading">Monitoring</div>
                    </div>
                    <div class="monitor-badge monitor-<?= h(monitor_status_slug((string)($monitorData['master_state'] ?? 'OK'))) ?>">
                        <?= h($monitorData['master_state'] ?? 'OK') ?>
                    </div>
                </div>

                <form method="post" class="monitor-toolbar-right refined-controls">
                    <input type="hidden" name="monitor_form" value="master">

                    <label class="switch-row">
                        <span>Master</span>
                        <input type="checkbox"
                            name="monitor_master"
                            <?= !empty($monitorData['master_enabled']) ? 'checked' : '' ?>
                            onchange="this.form.submit()">
                    </label>

                    <label class="timer-row">
                        <span>Refresh</span>
                        <input type="number"
                            name="monitor_refresh_seconds"
                            min="5"
                            max="300"
                            value="<?= (int)($monitorData['refresh_seconds'] ?? 30) ?>"
                            onchange="this.form.submit()"
                            onblur="this.form.submit()">
                        <small>sec</small>
                    </label>
                </form>
            </div>

            <div class="monitor-group-card monitor-state-<?= h(monitor_status_slug($combinedOverall)) ?>">
                <details class="monitor-group-details">
                    <summary class="monitor-group-summary">
                        <div class="monitor-group-main">
                            <div class="monitor-group-title-wrap">
                                <strong class="monitor-group-title">Process Streams</strong>
                                <span class="monitor-group-badge monitor-status monitor-<?= h(monitor_status_slug($combinedOverall)) ?>">
                                    <?= h($combinedOverall) ?>
                                </span>
                            </div>
                            <div class="monitor-group-meta"><?= h($combinedMeta) ?></div>
                        </div>

                        <div class="monitor-group-preview">
                            <?php if ($combinedIssueCount > 0): ?>
                                <?php foreach ($combinedItems as $key => $item): ?>
                                    <?php if (!monitor_has_issue($item)) continue; ?>
                                    <span class="monitor-group-chip monitor-<?= h(monitor_status_slug((string)($item['status'] ?? 'OK'))) ?>">
                                        <?= h($item['label'] ?? $key) ?>: <?= h($item['status'] ?? 'OK') ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($combinedItems as $key => $item): ?>
                                    <span class="monitor-group-chip monitor-<?= h(monitor_status_slug((string)($item['status'] ?? 'OK'))) ?>">
                                        <?= h($item['label'] ?? $key) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="monitor-group-expand">Expand</div>
                    </summary>

                    <div class="monitor-group-body">
                        <div class="monitor-group-grid">
                            <?php foreach ($combinedItems as $key => $item): ?>
                                <?= render_single_monitor_item($key, $item) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
            </div>

            <?php if (!empty($otherItems)): ?>
                <div class="monitor-grid refined-monitor-grid">
                    <?php foreach ($otherItems as $key => $item): ?>
                        <?= render_single_monitor_item($key, $item) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


function render_topbar(array $dashboard): string
{
    $range = $dashboard['range'];

    ob_start();
    ?>
    <div class="topbar refined-topbar">
        <div class="info-card hero-card">
            <div class="info-title">System Status</div>

            <div class="hero-status-row">
                <div>
                    <div class="hero-status <?= $dashboard['system_status'] === 'ONLINE' ? 'status-online' : 'status-offline' ?>">
                        <?= h($dashboard['system_status']) ?>
                    </div>
                    <div class="info-sub">Live dashboard updates without page reload</div>
                </div>

                <div class="hero-stats">
                    <div class="hero-stat">
                        <span>Total Records</span>
                        <b><?= (int)$dashboard['records_loaded'] ?></b>
                    </div>
                    <div class="hero-stat">
                        <span>Range</span>
                        <b><?= h($dashboard['range_summary']) ?></b>
                    </div>
                </div>
            </div>
        </div>

        <div class="info-card range-card">
            <div class="info-title">Date / Time Range</div>

            <?php render_dashboard_range_filter($range); ?>

            <?php if (($range['error'] ?? '') !== ''): ?>
                <div class="range-error"><?= h($range['error']) ?></div>
            <?php elseif (!empty($range['used_default_shift'])): ?>
                <div class="range-active">Showing current 12 hour shift block</div>
            <?php elseif (!empty($range['active'])): ?>
                <div class="range-active">Filtering graphs and tables to selected range</div>
            <?php else: ?>
                <div class="range-active">Showing all available records</div>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function render_tricanter_kpis(array $row): string
{
    ob_start();
    ?>
    <div class="kpi"><small>Bowl Speed</small><b><?= fmt($row['bowl_speed'] ?? null, 0) ?> %</b></div>
    <div class="kpi"><small>Screw Speed</small><b><?= fmt($row['screw_speed'] ?? null, 2) ?> %</b></div>
    <div class="kpi"><small>Bowl RPM</small><b><?= fmt($row['bowl_rpm'] ?? null, 0) ?> RPM</b></div>
    <div class="kpi"><small>Screw RPM</small><b><?= fmt($row['screw_rpm'] ?? null, 2) ?> RPM</b></div>
    <div class="kpi"><small>Impeller</small><b><?= fmt($row['impeller'] ?? null, 0) ?></b></div>
    <div class="kpi"><small>Feed Rate</small><b><?= fmt($row['feed_rate'] ?? null, 2) ?> M3/hr</b></div>
    <div class="kpi"><small>Torque</small><b><?= fmt($row['torque'] ?? null, 1) ?> %</b></div>
    <div class="kpi"><small>Temp</small><b><?= fmt($row['temp'] ?? null, 1) ?> °C</b></div>
    <div class="kpi"><small>Pressure</small><b><?= fmt($row['pressure'] ?? null, 3) ?> BAR</b></div>
    <?php
    return ob_get_clean();
}

function render_tricanter_rows(array $rows): string
{
    ob_start();

    if (!$rows): ?>
        <tr><td colspan="11">No tricanter data in selected range.</td></tr>
    <?php else:
        foreach ($rows as $r): ?>
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
        <?php endforeach;
    endif;

    return ob_get_clean();
}

function render_solid_waste_kpis(array $latestRow, float $totalAmount): string
{
    ob_start();
    ?>
    <div class="kpi"><small>Latest Amount</small><b><?= fmt($latestRow['amount'] ?? null, 0) ?> KG</b></div>
    <div class="kpi"><small>Total Amount</small><b><?= fmt($totalAmount, 0) ?> KG</b></div>
    <div class="kpi"><small>Last Entry</small><b><?= !empty($latestRow['log_time']) ? h(date('H:i', strtotime($latestRow['log_time']))) : '-' ?></b></div>
    <?php
    return ob_get_clean();
}

function render_solid_waste_rows(array $rows): string
{
    ob_start();

    if (!$rows): ?>
        <tr><td colspan="5">No solid waste data in selected range.</td></tr>
    <?php else:
        foreach ($rows as $r): ?>
            <tr class="solid-row" data-id="<?= (int)$r['id'] ?>">
                <td><?= h($r['log_date']) ?></td>
                <td><?= h($r['log_time']) ?></td>
                <td><?= fmt($r['amount'] ?? null, 0) ?> KG</td>
                <td><?= isset($r['_diff_minutes']) && $r['_diff_minutes'] !== null ? fmt($r['_diff_minutes'], 0) : '-' ?></td>
                <td class="comment-cell"><?= h($r['comments'] ?? '') ?></td>
            </tr>
        <?php endforeach;
    endif;

    return ob_get_clean();
}

function render_nozzle_kpis(array $row): string
{
    ob_start();
    ?>
    <div class="kpi"><small>Flow</small><b><?= fmt($row['flow'] ?? null, 1) ?> M3/hr</b></div>
    <div class="kpi"><small>Pressure</small><b><?= fmt($row['pressure'] ?? null, 2) ?> BAR</b></div>
    <div class="kpi"><small>RPM</small><b><?= fmt($row['rpm'] ?? null, 1) ?> RPM</b></div>
    <div class="kpi"><small>Min Deg</small><b><?= fmt($row['min_deg'] ?? null, 0) ?> °</b></div>
    <div class="kpi"><small>Max Deg</small><b><?= fmt($row['max_deg'] ?? null, 0) ?> °</b></div>
    <div class="kpi"><small>Nozzle</small><b>N<?= h($row['nozzle'] ?? '-') ?></b></div>
    <?php
    return ob_get_clean();
}

function render_nozzle_rows(array $rows): string
{
    ob_start();

    if (!$rows): ?>
        <tr><td colspan="8">No nozzle data in selected range.</td></tr>
    <?php else:
        foreach ($rows as $r): ?>
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
        <?php endforeach;
    endif;

    return ob_get_clean();
}

function render_sample_kpis(array $row): string
{
    ob_start();
    ?>
    <div class="kpi"><small>Location</small><b><?= h($row['sample_location'] ?? '-') ?></b></div>
    <div class="kpi"><small>Nozzle</small><b><?= h($row['nozzle'] ?? '-') ?></b></div>
    <div class="kpi"><small>Flow</small><b><?= fmt($row['flow'] ?? null, 2) ?> M3/hr</b></div>
    <div class="kpi"><small>Mercury</small><b><?= fmt($row['mercury'] ?? null, 3) ?> %</b></div>
    <div class="kpi"><small>Solids</small><b><?= fmt($row['solids'] ?? null, 2) ?> %</b></div>
    <div class="kpi"><small>Water</small><b><?= fmt($row['water'] ?? null, 2) ?> %</b></div>
    <div class="kpi"><small>Wax</small><b><?= fmt($row['wax'] ?? null, 2) ?> %</b></div>
    <div class="kpi"><small>Operator</small><b><?= h($row['operator'] ?? '-') ?></b></div>
    <?php
    return ob_get_clean();
}

function render_sample_rows(array $rows): string
{
    ob_start();

    if (!$rows): ?>
        <tr><td colspan="10">No sample data in selected range.</td></tr>
    <?php else:
        foreach ($rows as $r): ?>
            <tr class="sample-row" data-id="<?= (int)$r['id'] ?>">
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
        <?php endforeach;
    endif;

    return ob_get_clean();
}

function render_gas_test_kpis(array $row): string
{
    ob_start();
    ?>
    <div class="kpi"><small>Device</small><b><?= h($row['device'] ?? '-') ?></b></div>
    <div class="kpi"><small>Operator</small><b><?= h($row['operator'] ?? '-') ?></b></div>
    <div class="kpi"><small>Location</small><b><?= h($row['location'] ?? '-') ?></b></div>
    <div class="kpi"><small>Mercury</small><b><?= fmt($row['mercury'] ?? null, 3) ?> µg/m³</b></div>
    <div class="kpi"><small>Benzene</small><b><?= fmt($row['benzene'] ?? null, 2) ?> ppm</b></div>
    <div class="kpi"><small>LEL</small><b><?= fmt($row['lel'] ?? null, 1) ?> %</b></div>
    <div class="kpi"><small>H2S</small><b><?= fmt($row['h2s'] ?? null, 1) ?> ppm</b></div>
    <div class="kpi"><small>O2</small><b><?= fmt($row['o2'] ?? null, 1) ?> %</b></div>
    <div class="kpi"><small>Area</small><b><?= h($row['area_details'] ?? '-') ?></b></div>
    <?php
    return ob_get_clean();
}

function render_gas_test_rows(array $rows): string
{
    ob_start();

    if (!$rows): ?>
        <tr><td colspan="13">No gas test data in selected range.</td></tr>
    <?php else:
        foreach ($rows as $r): ?>
            <tr class="gas-row" data-id="<?= (int)$r['id'] ?>">
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
        <?php endforeach;
    endif;

    return ob_get_clean();
}

function render_project_flow_kpis(array $kpi): string
{
    ob_start();
    ?>
    <div class="kpi"><small>Records</small><b><?= fmt($kpi['count'] ?? 0, 0) ?></b></div>
    <div class="kpi"><small>Recovered Oil</small><b><?= fmt($kpi['oil'] ?? null, 4) ?> m³</b></div>
    <div class="kpi"><small>Recovered Water</small><b><?= fmt($kpi['water'] ?? null, 4) ?> m³</b></div>
    <div class="kpi"><small>Solid Waste</small><b><?= fmt($kpi['solid_waste'] ?? null, 4) ?> KG</b></div>
    <div class="kpi"><small>Tricanter</small><b><?= fmt($kpi['tricanter'] ?? null, 4) ?> m³</b></div>
    <div class="kpi"><small>Nozzle</small><b><?= fmt($kpi['nozzle'] ?? null, 4) ?> m³</b></div>
    <?php
    return ob_get_clean();
}

function render_project_flow_rows(array $rows): string
{
    ob_start();

    if (!$rows): ?>
        <tr><td colspan="8">No project flow data in selected range.</td></tr>
    <?php else:
        foreach ($rows as $r): ?>
            <tr class="project-flow-row" data-id="<?= (int)$r['id'] ?>">
                <td><?= h($r['log_date']) ?></td>
                <td><?= h($r['log_time']) ?></td>
                <td><?= fmt($r['total_recovered_oil'] ?? null, 4) ?> m³</td>
                <td><?= fmt($r['total_recovered_water'] ?? null, 4) ?> m³</td>
                <td><?= fmt($r['total_solid_waste'] ?? null, 4) ?> KG</td>
                <td><?= fmt($r['total_tricanter'] ?? null, 4) ?> m³</td>
                <td><?= fmt($r['total_nozzle'] ?? null, 4) ?> m³</td>
                <td class="comment-cell"><?= h($r['comments'] ?? '') ?></td>
            </tr>
        <?php endforeach;
    endif;

    return ob_get_clean();
}

function pump_status_text($value): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return '-';
    }

    $value = (int)$value;

    if ($value === 0) {
        return 'OFF';
    }

    if ($value === 1) {
        return 'ON';
    }

    if ($value === 2) {
        return 'ERROR';
    }

    return (string)$value;
}

function pump_feedback_display($value, int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    if (!is_numeric($value)) {
        return h($value);
    }

    if ((float)$value < 0) {
        return '###';
    }

    return fmt($value, $decimals);
}

function render_pump_values_kpis(array $row): string
{
    ob_start();
    ?>
    <div class="kpi"><small>SP1 Status</small><b><?= h(pump_status_text($row['suction_pump_1_status'] ?? null)) ?></b></div>
    <div class="kpi"><small>SP2 Status</small><b><?= h(pump_status_text($row['suction_pump_2_status'] ?? null)) ?></b></div>
    <div class="kpi"><small>FP Status</small><b><?= h(pump_status_text($row['feed_pump_status'] ?? null)) ?></b></div>
    <div class="kpi"><small>BP Status</small><b><?= h(pump_status_text($row['booster_pump_status'] ?? null)) ?></b></div>
    <div class="kpi"><small>SP2 Inlet Pressure</small><b><?= fmt($row['suction_pump_2_inlet_pressure'] ?? null, 3) ?> BAR</b></div>
    <div class="kpi"><small>SP2 Outlet Pressure</small><b><?= fmt($row['suction_pump_2_outlet_pressure'] ?? null, 3) ?> BAR</b></div>
    <div class="kpi"><small>FP Inlet Pressure</small><b><?= fmt($row['feed_pump_inlet_pressure'] ?? null, 3) ?> BAR</b></div>
    <div class="kpi"><small>FP Outlet Pressure</small><b><?= fmt($row['feed_pump_outlet_pressure'] ?? null, 3) ?> BAR</b></div>
    <div class="kpi"><small>BP Inlet Pressure</small><b><?= fmt($row['booster_pump_inlet_pressure'] ?? null, 3) ?> BAR</b></div>
    <div class="kpi"><small>BP Outlet Pressure</small><b><?= fmt($row['booster_pump_outlet_pressure'] ?? null, 3) ?> BAR</b></div>
    <?php
    return ob_get_clean();
}

function render_pump_values_rows(array $rows): string
{
    ob_start();

    if (!$rows): ?>
        <tr><td colspan="15">No pump values data in selected range.</td></tr>
    <?php else:
        foreach ($rows as $r): ?>
            <tr class="pump-values-row" data-id="<?= (int)$r['id'] ?>">
                <td><?= h($r['log_date'] ?? '') ?></td>
                <td><?= h($r['log_time'] ?? '') ?></td>
                <td><?= h(pump_status_text($r['suction_pump_1_status'] ?? null)) ?></td>
                <td><?= h(pump_status_text($r['suction_pump_2_status'] ?? null)) ?></td>
                <td><?= pump_feedback_display($r['suction_pump_2_feedback'] ?? null, 2) ?></td>
                <td><?= fmt($r['suction_pump_2_inlet_pressure'] ?? null, 3) ?> BAR</td>
                <td><?= fmt($r['suction_pump_2_outlet_pressure'] ?? null, 3) ?> BAR</td>
                <td><?= h(pump_status_text($r['feed_pump_status'] ?? null)) ?></td>
                <td><?= pump_feedback_display($r['feed_pump_feedback'] ?? null, 2) ?></td>
                <td><?= fmt($r['feed_pump_inlet_pressure'] ?? null, 3) ?> BAR</td>
                <td><?= fmt($r['feed_pump_outlet_pressure'] ?? null, 3) ?> BAR</td>
                <td><?= h(pump_status_text($r['booster_pump_status'] ?? null)) ?></td>
                <td><?= pump_feedback_display($r['booster_pump_feedback'] ?? null, 2) ?></td>
                <td><?= fmt($r['booster_pump_inlet_pressure'] ?? null, 3) ?> BAR</td>
                <td><?= fmt($r['booster_pump_outlet_pressure'] ?? null, 3) ?> BAR</td>
            </tr>
        <?php endforeach;
    endif;

    return ob_get_clean();
}

function build_dashboard_data(PDO $pdo, array $range): array
{
    try {
        $nozzle = fetch_log_rows($pdo, 'nozzle_logs', $range, 'id DESC');
        $tricanter = fetch_log_rows($pdo, 'tricanter_logs', $range, 'id DESC');
        $solidWaste = fetch_log_rows($pdo, 'solid_waste_logs', $range, 'id DESC');
        $sample = tableExists($pdo, 'sample_logs') ? fetch_log_rows($pdo, 'sample_logs', $range, 'id DESC') : [];
        $gasTest = tableExists($pdo, 'gas_test_logs') ? fetch_log_rows($pdo, 'gas_test_logs', $range, 'id DESC') : [];
        $projectFlow = tableExists($pdo, 'project_flow_logs') ? fetch_log_rows($pdo, 'project_flow_logs', $range, 'id DESC') : [];
        $pumpValues = tableExists($pdo, 'pump_values_logs') ? fetch_log_rows($pdo, 'pump_values_logs', $range, 'id DESC') : [];
    } catch (Throwable $e) {
        die("DB Error: " . h($e->getMessage()));
    }

    $solidWaste = solid_diff_minutes_rows($solidWaste);

    $latestNozzle = $nozzle[0] ?? [];
    $latestTricanter = $tricanter[0] ?? [];
    $latestSolidWaste = $solidWaste[0] ?? [];
    $latestSample = $sample[0] ?? [];
    $latestGasTest = $gasTest[0] ?? [];
    $latestProjectFlow = $projectFlow[0] ?? [];
    $latestPumpValues = $pumpValues[0] ?? [];

    $solidWasteTotalAmount = 0.0;
    foreach ($solidWaste as $r) {
        if (isset($r['amount']) && $r['amount'] !== '' && is_numeric($r['amount'])) {
            $solidWasteTotalAmount += (float)$r['amount'];
        }
    }

    $latestNozzleOverall = fetch_latest_row($pdo, 'nozzle_logs') ?: [];
    $latestTricanterOverall = fetch_latest_row($pdo, 'tricanter_logs') ?: [];
    $latestSolidWasteOverall = fetch_latest_row($pdo, 'solid_waste_logs') ?: [];
    $latestSampleOverall = tableExists($pdo, 'sample_logs') ? (fetch_latest_row($pdo, 'sample_logs') ?: []) : [];
    $latestGasTestOverall = tableExists($pdo, 'gas_test_logs') ? (fetch_latest_row($pdo, 'gas_test_logs') ?: []) : [];
    $latestProjectFlowOverall = tableExists($pdo, 'project_flow_logs') ? (fetch_latest_row($pdo, 'project_flow_logs') ?: []) : [];
    $latestPumpValuesOverall = tableExists($pdo, 'pump_values_logs') ? (fetch_latest_row($pdo, 'pump_values_logs') ?: []) : [];

    $systemStatus = (
        !empty($latestNozzleOverall) ||
        !empty($latestTricanterOverall) ||
        !empty($latestSolidWasteOverall) ||
        !empty($latestSampleOverall) ||
        !empty($latestGasTestOverall) ||
        !empty($latestProjectFlowOverall) ||
        !empty($latestPumpValuesOverall)
    ) ? 'ONLINE' : 'NO DATA';

    $recordsLoaded = count($nozzle) + count($tricanter) + count($solidWaste) + count($sample) + count($gasTest) + count($projectFlow) + count($pumpValues);
    $monitorData = buildMonitoringData($pdo);
    $projectFlowKpis = get_project_flow_kpis($pdo, $range);

    
    return [
        'range' => $range,
        'system_status' => $systemStatus,
        'records_loaded' => $recordsLoaded,
        'range_summary' => range_summary_text($range, 'Current shift block'),
        'monitor' => $monitorData,
        'last_stamps' => [
            'nozzle' => row_stamp($latestNozzleOverall),
            'tricanter' => row_stamp($latestTricanterOverall),
            'solid_waste' => row_stamp($latestSolidWasteOverall),
            'sample' => row_stamp($latestSampleOverall),
            'gas_test' => row_stamp($latestGasTestOverall),
            'project_flow' => row_stamp($latestProjectFlowOverall),
            'pump_values' => row_stamp($latestPumpValuesOverall),
        ],
        'panels' => [
            'tricanter' => [
                'kpis_html' => render_tricanter_kpis($latestTricanter),
                'rows_html' => render_tricanter_rows($tricanter),
                'chart' => [
                    'labels' => label_series($tricanter),
                    'datasets' => [
                        ['label' => 'Bowl Speed', 'data' => numeric_series($tricanter, 'bowl_speed')],
                        ['label' => 'Screw Speed', 'data' => numeric_series($tricanter, 'screw_speed')],
                        ['label' => 'Bowl RPM', 'data' => numeric_series($tricanter, 'bowl_rpm')],
                        ['label' => 'Screw RPM', 'data' => numeric_series($tricanter, 'screw_rpm')],
                        ['label' => 'Impeller', 'data' => numeric_series($tricanter, 'impeller')],
                        ['label' => 'Feed Rate', 'data' => numeric_series($tricanter, 'feed_rate')],
                        ['label' => 'Torque', 'data' => numeric_series($tricanter, 'torque')],
                        ['label' => 'Temp', 'data' => numeric_series($tricanter, 'temp')],
                        ['label' => 'Pressure', 'data' => numeric_series($tricanter, 'pressure')],
                    ],
                ],
            ],
            'solid_waste' => [
                'kpis_html' => render_solid_waste_kpis($latestSolidWaste, $solidWasteTotalAmount),
                'rows_html' => render_solid_waste_rows($solidWaste),
                'chart' => [
                    'labels' => label_series($solidWaste),
                    'datasets' => [
                        ['label' => 'Amount', 'data' => numeric_series($solidWaste, 'amount')],
                        ['label' => 'Diff (min)', 'data' => solid_diff_series($solidWaste)],
                    ],
                ],
            ],
            'nozzle' => [
                'kpis_html' => render_nozzle_kpis($latestNozzle),
                'rows_html' => render_nozzle_rows($nozzle),
                'chart' => [
                    'labels' => label_series($nozzle),
                    'datasets' => [
                        ['label' => 'Flow', 'data' => numeric_series($nozzle, 'flow')],
                        ['label' => 'Pressure', 'data' => numeric_series($nozzle, 'pressure')],
                        ['label' => 'Min Deg', 'data' => numeric_series($nozzle, 'min_deg')],
                        ['label' => 'Max Deg', 'data' => numeric_series($nozzle, 'max_deg')],
                        ['label' => 'RPM', 'data' => numeric_series($nozzle, 'rpm')],
                    ],
                ],
            ],
            'sample' => [
                'kpis_html' => render_sample_kpis($latestSample),
                'rows_html' => render_sample_rows($sample),
            ],
            'gas_test' => [
                'kpis_html' => render_gas_test_kpis($latestGasTest),
                'rows_html' => render_gas_test_rows($gasTest),
                'chart' => [
                    'labels' => label_series($gasTest),
                    'datasets' => [
                        ['label' => 'Mercury', 'data' => numeric_series($gasTest, 'mercury')],
                        ['label' => 'Benzene', 'data' => numeric_series($gasTest, 'benzene')],
                        ['label' => 'LEL', 'data' => numeric_series($gasTest, 'lel')],
                        ['label' => 'H2S', 'data' => numeric_series($gasTest, 'h2s')],
                        ['label' => 'O2', 'data' => numeric_series($gasTest, 'o2')],
                    ],
                ],
            ],
            'project_flow' => [
                'kpis_html' => render_project_flow_kpis($projectFlowKpis),
                'rows_html' => render_project_flow_rows($projectFlow),
            ],
            'pump_values' => [
                'kpis_html' => render_pump_values_kpis($latestPumpValues),
                'rows_html' => render_pump_values_rows($pumpValues),
                'chart' => [
                    'labels' => label_series($pumpValues),
                    'datasets' => [
                        ['label' => 'Suction Inlet Pressure', 'data' => numeric_series($pumpValues, 'suction_pump_2_inlet_pressure')],
                        ['label' => 'Suction Outlet Pressure', 'data' => numeric_series($pumpValues, 'suction_pump_2_outlet_pressure')],
                        ['label' => 'Feed Inlet Pressure', 'data' => numeric_series($pumpValues, 'feed_pump_inlet_pressure')],
                        ['label' => 'Feed Outlet Pressure', 'data' => numeric_series($pumpValues, 'feed_pump_outlet_pressure')],
                        ['label' => 'Booster Inlet Pressure', 'data' => numeric_series($pumpValues, 'booster_pump_inlet_pressure')],
                        ['label' => 'Booster Outlet Pressure', 'data' => numeric_series($pumpValues, 'booster_pump_outlet_pressure')],
                    ],
                ],
            ],
        ],
    ];
}

$range = get_range_filter_state();
$dashboard = build_dashboard_data($pdo, $range);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link rel="stylesheet" href="indexStyle.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root{
            --bg-1:#08131f;
            --bg-2:#0e2235;
            --card:#10273c;
            --card-2:#122c44;
            --line:#214968;
            --line-soft:rgba(138,188,230,.14);
            --text:#e6f2ff;
            --muted:#9cc1de;
            --glow:0 10px 35px rgba(0,0,0,.28);
            --radius:16px;
        }

        body{
            background:
                radial-gradient(circle at top left, rgba(0,255,255,.06), transparent 22%),
                radial-gradient(circle at top right, rgba(0,135,255,.08), transparent 24%),
                linear-gradient(180deg, var(--bg-1), #091726 40%, #0a1828 100%);
            color:var(--text);
        }

        .dashboard-shell{
            max-width:1800px;
            margin:0 auto;
        }

        .section-kicker{
            font-size:11px;
            letter-spacing:1.1px;
            text-transform:uppercase;
            color:#8abce6;
            margin-bottom:4px;
        }

        .refined-shell,
        .panel,
        .info-card{
            background:linear-gradient(180deg, rgba(18,44,68,.94), rgba(14,34,53,.96));
            border:1px solid var(--line-soft);
            border-radius:var(--radius);
            box-shadow:var(--glow);
            backdrop-filter: blur(8px);
        }

        .logo-row{
            margin:6px 0 14px;
        }

        .logo-row img{
            height:110px;
            filter:drop-shadow(0 10px 28px rgba(0,0,0,.25));
        }

        .refined-topbar{
            grid-template-columns: 1.4fr .95fr;
            align-items:start;
        }

        .hero-card{
            padding:16px;
        }

        .hero-status-row{
            display:flex;
            justify-content:space-between;
            gap:16px;
            align-items:flex-start;
            margin-bottom:0;
        }

        .hero-status{
            font-size:28px;
            font-weight:800;
            line-height:1;
            margin-bottom:6px;
        }

        .hero-stats{
            display:grid;
            grid-template-columns:repeat(2,minmax(130px,1fr));
            gap:10px;
            width:min(430px,100%);
        }

        .hero-stat{
            background:rgba(255,255,255,.035);
            border:1px solid rgba(255,255,255,.06);
            border-radius:12px;
            padding:10px 12px;
        }

        .hero-stat span{
            display:block;
            font-size:11px;
            color:var(--muted);
            text-transform:uppercase;
            letter-spacing:.7px;
            margin-bottom:5px;
        }

        .hero-stat b{
            display:block;
            font-size:14px;
            color:#fff;
            word-break:break-word;
        }

        .range-card{
            padding:16px;
        }

        .filter-form input[type="datetime-local"],
        .monitor-minutes,
        .timer-row input[type="number"]{
            background:#0a1a29;
            border:1px solid #2a5377;
            border-radius:10px;
            color:#fff;
            padding:8px 10px;
        }

        .btn{
            border-radius:10px;
            background:linear-gradient(180deg, #1f4f74, #173d5b);
            border:1px solid rgba(255,255,255,.06);
            transition:transform .18s ease, background .18s ease, box-shadow .18s ease;
        }

        .btn:hover{
            background:linear-gradient(180deg, #255f8c, #1d4a6d);
            transform:translateY(-1px);
            box-shadow:0 6px 18px rgba(0,0,0,.18);
        }

        .monitor-shell{
            padding:14px;
            margin-bottom:18px;
        }

        .refined-toolbar{
            margin-bottom:14px;
        }

        .refined-controls{
            background:rgba(255,255,255,.035);
            border:1px solid rgba(255,255,255,.06);
            border-radius:14px;
            padding:8px 10px;
        }

        .refined-monitor-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));
            gap:14px;
            align-items:start;
        }

        .monitor-item{
            background:linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.025));
            border-radius:14px;
        }

        .monitor-group-card{
            background:linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.025));
            border-radius:14px;
            border:1px solid rgba(255,255,255,.08);
            overflow:hidden;
            margin-bottom:14px;
        }

        .monitor-group-details{
            display:block;
        }

        .monitor-group-details summary{
            list-style:none;
        }

        .monitor-group-details summary::-webkit-details-marker{
            display:none;
        }

        .monitor-group-summary{
            display:grid;
            grid-template-columns:minmax(220px, 280px) 1fr auto;
            gap:14px;
            align-items:center;
            padding:14px;
            cursor:pointer;
        }

        .monitor-group-title-wrap{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
            margin-bottom:5px;
        }

        .monitor-group-title{
            font-size:16px;
            letter-spacing:.2px;
        }

        .monitor-group-meta{
            color:var(--muted);
            font-size:12px;
        }

        .monitor-group-preview{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
            min-width:0;
        }

        .monitor-group-chip{
            display:inline-flex;
            align-items:center;
            border-radius:999px;
            padding:6px 10px;
            font-size:11px;
            font-weight:700;
            letter-spacing:.2px;
            text-transform:uppercase;
            border:1px solid rgba(255,255,255,.08);
            background:rgba(255,255,255,.05);
        }

        .monitor-group-chip.monitor-warning{
            background:rgba(255,193,7,.12);
            color:#ffe08a;
        }

        .monitor-group-chip.monitor-overdue{
            background:rgba(239,68,68,.14);
            color:#ffb0b0;
        }

        .monitor-group-chip.monitor-no-data,
        .monitor-group-chip.monitor-not-set-up{
            background:rgba(148,163,184,.13);
            color:#d8e1ee;
        }

        .monitor-group-expand{
            color:var(--muted);
            font-size:12px;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.7px;
        }

        .monitor-group-body{
            border-top:1px solid rgba(255,255,255,.08);
            padding:14px;
            background:rgba(255,255,255,.02);
        }

        .monitor-group-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
            gap:14px;
            align-items:start;
        }

        .grid{
            gap:18px;
        }

        .panel{
            padding:14px;
        }

        .panel-head{
            align-items:flex-start;
            margin-bottom:12px;
        }

        .panel-head h2{
            margin:0;
            font-size:24px;
            letter-spacing:.2px;
        }

        .panel-sub{
            color:var(--muted);
            font-size:12px;
            margin-top:4px;
        }

        .kpis{
            gap:10px;
        }

        .kpi{
            background:linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,.03));
            border:1px solid rgba(255,255,255,.06);
            border-radius:12px;
            padding:10px;
            transition:transform .18s ease, border-color .18s ease, background .18s ease;
        }

        .kpi:hover{
            transform:translateY(-1px);
            border-color:rgba(138,188,230,.28);
        }

        .kpi small{
            text-transform:uppercase;
            letter-spacing:.7px;
            font-size:10px;
        }

        .kpi b{
            font-size:19px;
        }

        .chart-card{
            border-radius:14px;
            padding:10px;
            border:1px solid rgba(255,255,255,.05);
            background:linear-gradient(180deg, rgba(8,26,40,.75), rgba(10,24,36,.94));
        }

        .chart-wrap{
            height:240px;
        }

        .table{
            border-radius:14px;
            border:1px solid rgba(255,255,255,.05);
            background:rgba(7,18,28,.42);
        }

        table{
            width:max-content;
            min-width:100%;
        }

        th{
            background:#183a56;
        }

        th, td{
            padding:8px 10px;
            border-bottom:1px solid #1f4665;
        }

        tbody tr{
            transition:background-color .2s ease, transform .22s ease, opacity .22s ease;
        }

        tbody tr:hover{
            background:rgba(255,255,255,.04);
        }

        .comment-cell{
            white-space:normal;
            min-width:220px;
        }

        .row-new{
            animation:rowPulse .9s ease;
        }

        @keyframes rowPulse{
            0%{ background:rgba(255,230,120,.75); color:#000; transform:translateY(-6px); }
            100%{ background:transparent; color:inherit; transform:none; }
        }

        @media (max-width:1400px){
            .refined-topbar{
                grid-template-columns:1fr;
            }
        }

        @media (max-width:1000px){
            .hero-status-row{
                flex-direction:column;
            }

            .hero-stats{
                grid-template-columns:1fr 1fr;
                width:100%;
            }

            .monitor-group-summary{
                grid-template-columns:1fr;
            }

            .monitor-group-grid{
                grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
            }
        }

        @media (max-width:700px){
            .hero-stats{
                grid-template-columns:1fr;
            }

            .monitor-group-grid{
                grid-template-columns:1fr;
            }

            .panel-head h2{
                font-size:20px;
            }
        }
    </style>
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
                    <a class="btn" href="tricanter_list.php">View Logs</a>
                    <?php if ($canEdit): ?><a class="btn" href="tricanter_add.php">Add Record</a><?php endif; ?>
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
                    <a class="btn" href="solid_waste_list.php">View Logs</a>
                    <?php if ($canEdit): ?><a class="btn" href="solid_waste_add.php">Add Record</a><?php endif; ?>
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
                    <h2>Nozzle</h2>
                    <div class="panel-sub">Live nozzle pressure, flow, angle, and RPM trend</div>
                </div>
                <div class="panel-actions">
                    <a class="btn" href="nozzle_list.php">View Logs</a>
                    <?php if ($canEdit): ?><a class="btn" href="nozzle_add.php">Add Record</a><?php endif; ?>
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
                    <a class="btn" href="sample_list.php">View Logs</a>
                    <?php if ($canEdit): ?><a class="btn" href="sample_add.php">Add Record</a><?php endif; ?>
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
                    <div class="panel-sub">Gas readings with live chart and filtered table</div>
                </div>
                <div class="panel-actions">
                    <a class="btn" href="gas_test_list.php">View Logs</a>
                    <?php if ($canEdit): ?><a class="btn" href="gas_test_add.php">Add Record</a><?php endif; ?>
                </div>
            </div>

            <div id="gas-test-kpis" class="kpis"><?= $dashboard['panels']['gas_test']['kpis_html'] ?></div>

            <div class="chart-card">
                <div class="chart-title">Gas Test Trends</div>
                <div class="chart-wrap"><canvas id="gasTestCombinedChart"></canvas></div>
            </div>

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
                    <a class="btn" href="project_flow_list.php">View Logs</a>
                    <?php if ($canEdit): ?><a class="btn" href="project_flow_add.php">Add Record</a><?php endif; ?>
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
                    <a class="btn" href="pump_values_list.php">View Logs</a>
                    <?php if ($canEdit): ?><a class="btn" href="pump_values_add.php">Add Record</a><?php endif; ?>
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
    'Booster Outlet Pressure': '#22c55e'
};

const initialPanels = <?= json_encode($dashboard['panels'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const charts = {};

function validDatasets(datasets) {
    return (datasets || []).filter(ds => Array.isArray(ds.data) && ds.data.length > 0);
}

function normaliseSeries(data) {
    const numeric = data.filter(v => v !== null && v !== '' && !Number.isNaN(Number(v))).map(Number);

    if (!numeric.length) {
        return data.map(() => null);
    }

    const min = Math.min(...numeric);
    const max = Math.max(...numeric);

    if (max === min) {
        return data.map(v => {
            if (v === null || v === '' || Number.isNaN(Number(v))) return null;
            return 50;
        });
    }

    return data.map(v => {
        if (v === null || v === '' || Number.isNaN(Number(v))) return null;
        return ((Number(v) - min) / (max - min)) * 100;
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
        pointRadius: 0,
        pointHoverRadius: 4,
        pointHitRadius: 12,
        spanGaps: true
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
                    grid: { display: false }
                },
                y: {
                    display: false,
                    min: 0,
                    max: 100,
                    grid: { display: false }
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
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
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
        updateChart(charts.gasTest, payload.panels.gas_test.chart);
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
charts.gasTest = makeChart('gasTestCombinedChart', initialPanels.gas_test.chart);
charts.pumpValues = makeChart('pumpValuesPressureChart', initialPanels.pump_values.chart);

markNewRows('tricanter-tbody', 'triLastSeen');
markNewRows('solid-waste-tbody', 'solidLastSeen');
markNewRows('nozzle-tbody', 'nozzleLastSeen');
markNewRows('sample-tbody', 'sampleLastSeen');
markNewRows('gas-test-tbody', 'gasLastSeen');
markNewRows('project-flow-tbody', 'projectFlowLastSeen');
markNewRows('pump-values-tbody', 'pumpValuesLastSeen');

tickMonitorTimers();
setInterval(tickMonitorTimers, 1000);
schedulePolling();
</script>
</body>
</html>