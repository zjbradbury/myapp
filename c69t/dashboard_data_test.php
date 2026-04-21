<?php
require_once "config_test.php";
requireRole(['admin', 'operator', 'viewer']);

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
    <div class="kpi"><small>Suction Pump 1</small><b><?= h(pump_status_text($row['suction_pump_1_status'] ?? null)) ?></b></div>
    <div class="kpi"><small>Suction Pump 2</small><b><?= h(pump_status_text($row['suction_pump_2_status'] ?? null)) ?></b></div>
    <div class="kpi"><small>Feed Pump</small><b><?= h(pump_status_text($row['feed_pump_status'] ?? null)) ?></b></div>
    <div class="kpi"><small>Booster Pump</small><b><?= h(pump_status_text($row['booster_pump_status'] ?? null)) ?></b></div>
    <div class="kpi"><small>Suction Inlet</small><b><?= fmt($row['suction_pump_2_inlet_pressure'] ?? null, 3) ?> BAR</b></div>
    <div class="kpi"><small>Suction Outlet</small><b><?= fmt($row['suction_pump_2_outlet_pressure'] ?? null, 3) ?> BAR</b></div>
    <div class="kpi"><small>Feed Inlet</small><b><?= fmt($row['feed_pump_inlet_pressure'] ?? null, 3) ?> BAR</b></div>
    <div class="kpi"><small>Feed Outlet</small><b><?= fmt($row['feed_pump_outlet_pressure'] ?? null, 3) ?> BAR</b></div>
    <div class="kpi"><small>Booster Inlet</small><b><?= fmt($row['booster_pump_inlet_pressure'] ?? null, 3) ?> BAR</b></div>
    <div class="kpi"><small>Booster Outlet</small><b><?= fmt($row['booster_pump_outlet_pressure'] ?? null, 3) ?> BAR</b></div>
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

        $latestNozzleOverall = fetch_latest_row($pdo, 'nozzle_logs') ?: [];
        $latestTricanterOverall = fetch_latest_row($pdo, 'tricanter_logs') ?: [];
        $latestSolidWasteOverall = fetch_latest_row($pdo, 'solid_waste_logs') ?: [];
        $latestSampleOverall = tableExists($pdo, 'sample_logs') ? (fetch_latest_row($pdo, 'sample_logs') ?: []) : [];
        $latestGasTestOverall = tableExists($pdo, 'gas_test_logs') ? (fetch_latest_row($pdo, 'gas_test_logs') ?: []) : [];
        $latestProjectFlowOverall = tableExists($pdo, 'project_flow_logs') ? (fetch_latest_row($pdo, 'project_flow_logs') ?: []) : [];
        $latestPumpValuesOverall = tableExists($pdo, 'pump_values_logs') ? (fetch_latest_row($pdo, 'pump_values_logs') ?: []) : [];
    } catch (Throwable $e) {
        throw new RuntimeException($e->getMessage());
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

header('Content-Type: application/json');

try {
    $range = get_range_filter_state();
    $dashboard = build_dashboard_data($pdo, $range);

    echo json_encode([
        'ok' => true,
        'monitor_html' => render_monitor_shell($dashboard['monitor']),
        'topbar_html' => render_topbar($dashboard),
        'panels' => $dashboard['panels'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ]);
}