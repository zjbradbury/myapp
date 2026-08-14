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
    <style>
        :root{--bg:#081522;--card:#102a40;--card2:#13344f;--line:#275573;--text:#e9f4ff;--muted:#9fc0d8;--red:#ff6262;--amber:#ffc65c}
        *{box-sizing:border-box} body{margin:0;padding:16px;background:radial-gradient(circle at top left,#0d3043 0,transparent 30%),var(--bg);color:var(--text);font-family:Arial,sans-serif}
        .shell{max-width:1500px;margin:auto}.logos{display:flex;justify-content:center;gap:18px;align-items:center}.logos img{height:92px;max-width:43%;object-fit:contain}
        .card{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid rgba(160,205,235,.16);border-radius:16px;box-shadow:0 12px 34px rgba(0,0,0,.25)}
        .hero{padding:20px;margin:12px 0}.kicker{text-transform:uppercase;letter-spacing:1.2px;font-size:11px;color:#8cc7ef}.hero h1{margin:5px 0;font-size:30px}.sub{color:var(--muted);font-size:13px}
        .filters{padding:16px;margin-bottom:14px}.filters h2{font-size:14px;margin:0 0 12px}.filter-form{display:flex;gap:12px;align-items:end;flex-wrap:wrap}.field{display:flex;flex-direction:column;gap:5px;color:var(--muted);font-size:12px}.field input{background:#091c2b;color:#fff;border:1px solid var(--line);padding:9px;border-radius:9px;color-scheme:dark}
        .actions,.quick{display:flex;gap:7px;flex-wrap:wrap}.btn{border:1px solid rgba(255,255,255,.08);background:#1d5075;color:#fff;border-radius:9px;padding:9px 11px;text-decoration:none;cursor:pointer;font-size:12px}.btn:hover{background:#286890}.range-note{margin-top:10px;color:#a9cce4;font-size:12px}
        .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:14px}.kpi{padding:17px}.kpi-label{color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.7px}.kpi-value{font-size:25px;font-weight:800;margin-top:7px}.kpi-detail{font-size:11px;color:var(--muted);margin-top:4px}.kpi.active .kpi-value{color:var(--red)}
        .table-card{overflow:hidden}.toolbar{padding:13px 16px;color:var(--muted);font-size:12px;border-bottom:1px solid rgba(160,205,235,.12)}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:780px}th{background:#0d2538;color:#a9d7f4;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.6px;padding:12px}td{padding:12px;border-top:1px solid rgba(160,205,235,.1);font-size:13px}tr.active-alarm{background:rgba(190,35,35,.22);box-shadow:inset 4px 0 var(--red)}tr.active-alarm td{color:#fff}.status{display:inline-block;padding:4px 8px;border-radius:999px;background:#23455b;font-size:11px}.status.on{background:#a52d32;color:#fff;font-weight:bold}.empty,.error{padding:20px;text-align:center;color:var(--muted)}.error{color:#ffd0d0;background:#5a2026;border-radius:12px;margin-bottom:14px}
        @media(max-width:760px){body{padding:10px}.kpis{grid-template-columns:repeat(2,1fr)}.hero h1{font-size:25px}.logos img{height:70px}.filter-form{align-items:stretch}.field{width:100%}.actions,.quick{width:100%}.btn{flex:1;text-align:center}}
    </style>
</head>
<body>
<?php require 'nav.php'; ?>
<main class="shell">
    <div class="logos"><img src="MoombaTankCleaningLogoTransparent.PNG" alt="Moomba Tank Cleaning"><img src="Contract69TanksLogoTransparent.png" alt="Contract 69 Tanks"></div>
    <section class="hero card"><div class="kicker">Operations</div><h1>Alarm History</h1><div class="sub">Alarm occurrences, active time and frequency for the selected period.</div></section>

    <section class="filters card">
        <h2>Date / Time Search</h2>
        <form method="get" class="filter-form">
            <label class="field">From<input type="datetime-local" name="start" value="<?= h(to_datetime_local_value($range['start'] ?? '')) ?>"></label>
            <label class="field">To<input type="datetime-local" name="end" value="<?= h(to_datetime_local_value($range['end'] ?? '')) ?>"></label>
            <div class="actions"><button class="btn" type="submit">Apply Range</button><a class="btn" href="alarm_history.php?quick=clear">All History</a></div>
            <div class="quick"><button class="btn" name="quick" value="current_shift">Current Shift</button><button class="btn" name="quick" value="previous_shift">Previous Shift</button><button class="btn" name="quick" value="today">Today</button><button class="btn" name="quick" value="24h">Last 24 Hours</button><button class="btn" name="quick" value="7d">Last 7 Days</button></div>
        </form>
        <div class="range-note"><?= h(!empty($range['used_default_shift']) ? 'Showing current 12 hour shift block' : range_summary_text($range, 'All available alarms')) ?></div>
    </section>

    <?php if ($error !== ''): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <section class="kpis">
        <div class="kpi card"><div class="kpi-label">Alarm Amount</div><div class="kpi-value"><?= count($alarms) ?></div><div class="kpi-detail">Occurrences in selected period</div></div>
        <div class="kpi card"><div class="kpi-label">Total Duration</div><div class="kpi-value"><?= h(alarm_duration($totalSeconds)) ?></div><div class="kpi-detail">Combined active time</div></div>
        <div class="kpi card"><div class="kpi-label">Highest Frequency</div><div class="kpi-value"><?= $topFrequency ?></div><div class="kpi-detail">Alarm ID <?= h($topAlarm) ?> occurrence<?= $topFrequency === 1 ? '' : 's' ?></div></div>
        <div class="kpi card active"><div class="kpi-label">Active Alarms</div><div class="kpi-value"><?= $activeCount ?></div><div class="kpi-detail">Not yet cleared</div></div>
    </section>

    <section class="table-card card"><div class="toolbar"><?= count($alarms) ?> alarm occurrence<?= count($alarms) === 1 ? '' : 's' ?></div><div class="table-wrap"><table>
        <thead><tr><th>Alarm ID</th><th>Date</th><th>Time</th><th>Description</th><th>Active For</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$alarms): ?><tr><td class="empty" colspan="6">No alarms found in the selected period.</td></tr><?php endif; ?>
        <?php foreach ($alarms as $alarm):
            $id = (int)$alarm['alarm_id'];
            $description = trim((string)($alarm['alarm_text'] ?? ''));
            if ($description === '') $description = trim((string)($mapping[$id]['alarm_text'] ?? '')) ?: 'Alarm ' . $id;
        ?>
            <tr class="<?= $alarm['_active'] ? 'active-alarm' : '' ?>">
                <td><?= $id ?></td><td><?= h(date('d/m/Y', strtotime($alarm['log_date']))) ?></td><td><?= h(date('H:i:s', strtotime($alarm['log_time']))) ?></td><td><?= h($description) ?></td><td><?= h(alarm_duration($alarm['_duration'])) ?></td><td><span class="status <?= $alarm['_active'] ? 'on' : '' ?>"><?= $alarm['_active'] ? 'ACTIVE' : 'Cleared' ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></section>
</main>
</body></html>
