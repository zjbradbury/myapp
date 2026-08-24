<?php
require_once 'config.php';
requireRole(['admin', 'operator', 'viewer']);

$range = get_range_filter_state(true);
$error = $range['error'] ?? '';
$alarms = [];
$mapping = [];

function alarm_mapping(string $file): array
{
    $map = [];
    $handle = @fopen($file, 'r');
    if (!$handle) return $map;
    $headers = fgetcsv($handle) ?: [];
    $headers = array_map(static fn($v) => preg_replace('/^\xEF\xBB\xBF/', '', trim((string)$v)), $headers);
    while (($values = fgetcsv($handle)) !== false) {
        $row = array_combine($headers, array_pad($values, count($headers), ''));
        if ($row && isset($row['alarm_id'])) $map[(int)$row['alarm_id']] = $row;
    }
    fclose($handle);
    return $map;
}

function alarm_duration(int $seconds): string
{
    $seconds = max(0, $seconds);
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;
    if ($days) return sprintf('%dd %02dh %02dm', $days, $hours, $minutes);
    if ($hours) return sprintf('%dh %02dm %02ds', $hours, $minutes, $secs);
    if ($minutes) return sprintf('%dm %02ds', $minutes, $secs);
    return $secs . 's';
}

$mapping = alarm_mapping(__DIR__ . '/alarm_mapping.csv');

if ($error === '') {
    try {
        if (!tableExists($pdo, 'hmi_alarm_logs')) {
            $error = 'The hmi_alarm_logs table does not exist yet.';
        } else {
            $where = ['a.state = 1'];
            $params = [];
            if (!empty($range['start_sql'])) {
                $where[] = 'TIMESTAMP(a.log_date, a.log_time) >= :start_dt';
                $params[':start_dt'] = $range['start_sql'];
            }
            if (!empty($range['end_sql'])) {
                $where[] = 'TIMESTAMP(a.log_date, a.log_time) <= :end_dt';
                $params[':end_dt'] = $range['end_sql'];
            }
            $sql = "SELECT a.*,
                    (SELECT MIN(TIMESTAMP(c.log_date, c.log_time))
                       FROM hmi_alarm_logs c
                      WHERE c.alarm_id = a.alarm_id AND c.state = 0
                        AND (TIMESTAMP(c.log_date, c.log_time) > TIMESTAMP(a.log_date, a.log_time)
                             OR (TIMESTAMP(c.log_date, c.log_time) = TIMESTAMP(a.log_date, a.log_time) AND c.id > a.id))) AS cleared_at
                    FROM hmi_alarm_logs a
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY a.log_date DESC, a.log_time DESC, a.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $alarms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$now = time();
$totalSeconds = 0;
$activeCount = 0;
$counts = [];
foreach ($alarms as &$alarm) {
    $started = strtotime($alarm['log_date'] . ' ' . $alarm['log_time']) ?: $now;
    $ended = !empty($alarm['cleared_at']) ? strtotime($alarm['cleared_at']) : $now;
    $alarm['_duration'] = max(0, $ended - $started);
    $alarm['_active'] = empty($alarm['cleared_at']);
    $totalSeconds += $alarm['_duration'];
    if ($alarm['_active']) $activeCount++;
    $alarmId = (int)$alarm['alarm_id'];
    $counts[$alarmId] = ($counts[$alarmId] ?? 0) + 1;
}
unset($alarm);
$topFrequency = $counts ? max($counts) : 0;
$topAlarmIds = $topFrequency ? array_keys($counts, $topFrequency, true) : [];
$topAlarm = $topAlarmIds ? (string)$topAlarmIds[0] : '-';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="c69t.ico" type="image/x-icon">
    <title>Alarm History</title>
    <link rel="stylesheet" href="alarm_history.css">
</head>

<body>
    <?php require 'nav.php'; ?>
    <main class="shell">
        <div class="logos">
            <img src="MoombaTankCleaningLogoTransparent.PNG" alt="Moomba Tank Cleaning">
            <img src="Contract69TanksLogoTransparent.png" alt="Contract 69 Tanks">
        </div>

        <section class="hero card">
            <div class="kicker">Operations</div>
            <h1>Alarm History</h1>
            <div class="sub">Alarm occurrences, active time and frequency for the selected period.</div>
        </section>

        <section class="filters card">
            <h2>Date / Time Search</h2>
            <form method="get" class="filter-form">
                <label class="field">From<input type="datetime-local" name="start" value="<?= h(to_datetime_local_value($range['start'] ?? '')) ?>"></label>
                <label class="field">To<input type="datetime-local" name="end" value="<?= h(to_datetime_local_value($range['end'] ?? '')) ?>"></label>
                
                <div class="actions">
                    <button class="btn" type="submit">Apply Range</button>
                    <a class="btn" href="alarm_history.php?quick=clear">All History</a>
                </div>
                
                <div class="quick">
                    <button class="btn" name="quick" value="current_shift">Current Shift</button>
                    <button class="btn" name="quick" value="previous_shift">Previous Shift</button>
                    <button class="btn" name="quick" value="today">Today</button>
                    <button class="btn" name="quick" value="24h">Last 24 Hours</button>
                    <button class="btn" name="quick" value="7d">Last 7 Days</button>
                </div>

            </form>
            
            <div class="range-note"><?= h(!empty($range['used_default_shift']) ? 'Showing current 12 hour shift block' : range_summary_text($range, 'All available alarms')) ?></div>
        </section>

        <?php if ($error !== ''): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
        <section class="kpis">
            <div class="kpi card">
                <div class="kpi-label">Alarm Amount</div>
                <div class="kpi-value"><?= count($alarms) ?></div>
                <div class="kpi-detail">Occurrences in selected period</div>
            </div>
            <div class="kpi card">
                <div class="kpi-label">Total Duration</div>
                <div class="kpi-value"><?= h(alarm_duration($totalSeconds)) ?></div>
                <div class="kpi-detail">Combined active time</div>
            </div>
            <div class="kpi card">
                <div class="kpi-label">Highest Frequency</div>
                <div class="kpi-value"><?= $topFrequency ?></div>
                <div class="kpi-detail">Alarm ID <?= h($topAlarm) ?> occurrence<?= $topFrequency === 1 ? '' : 's' ?></div>
            </div>
            <div class="kpi card active">
                <div class="kpi-label">Active Alarms</div>
                <div class="kpi-value"><?= $activeCount ?></div>
                <div class="kpi-detail">Not yet cleared</div>
            </div>
        </section>

        <section class="table-card card">
            <div class="toolbar"><?= count($alarms) ?> alarm occurrence<?= count($alarms) === 1 ? '' : 's' ?></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Alarm ID</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Description</th>
                            <th>Active For</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$alarms): ?><tr>
                                <td class="empty" colspan="6">No alarms found in the selected period.</td>
                            </tr><?php endif; ?>
                        <?php foreach ($alarms as $alarm):
                            $id = (int)$alarm['alarm_id'];
                            $description = trim((string)($alarm['alarm_text'] ?? ''));
                            if ($description === '') $description = trim((string)($mapping[$id]['alarm_text'] ?? '')) ?: 'Alarm ' . $id;
                        ?>
                            <tr class="<?= $alarm['_active'] ? 'active-alarm' : '' ?>">
                                <td><?= $id ?></td>
                                <td><?= h(date('d/m/Y', strtotime($alarm['log_date']))) ?></td>
                                <td><?= h(date('H:i:s', strtotime($alarm['log_time']))) ?></td>
                                <td><?= h($description) ?></td>
                                <td><?= h(alarm_duration($alarm['_duration'])) ?></td>
                                <td><span class="status <?= $alarm['_active'] ? 'on' : '' ?>"><?= $alarm['_active'] ? 'ACTIVE' : 'Cleared' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>

</html>