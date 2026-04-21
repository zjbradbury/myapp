<?php
session_start();

date_default_timezone_set('Australia/Adelaide');

$host = "mariadb";
$dbname = "myapp";
$dbuser = "zack";
$dbpass = "Butcher69";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser,
        $dbpass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

function isLoggedIn()
{
    return isset($_SESSION["user_id"]);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

function currentRole()
{
    return $_SESSION["role"] ?? "";
}

function requireRole(array $allowedRoles)
{
    requireLogin();
    if (!in_array(currentRole(), $allowedRoles, true)) {
        http_response_code(403);
        die("Access denied.");
    }
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (!function_exists('fmt')) {
    function fmt($value, $decimals = 0)
    {
        if ($value === null || $value === '') {
            return '-';
        }
        if (!is_numeric($value)) {
            return h($value);
        }
        return number_format((float) $value, $decimals, '.', '');
    }
}

if (!function_exists('numeric_series')) {
    function numeric_series(array $rows, string $key): array
    {
        $out = [];
        foreach (array_reverse($rows) as $row) {
            if (isset($row[$key]) && $row[$key] !== '' && is_numeric($row[$key])) {
                $out[] = (float) $row[$key];
            }
        }
        return $out;
    }
}

if (!function_exists('label_series')) {
    function label_series(array $rows): array
    {
        $out = [];
        foreach (array_reverse($rows) as $row) {
            $out[] = trim(($row['log_date'] ?? '') . ' ' . ($row['log_time'] ?? ''));
        }
        return $out;
    }
}

if (!function_exists('to_datetime_local_value')) {
    function to_datetime_local_value(?string $value): string
    {
        if (!$value) {
            return '';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return '';
        }
        return date('Y-m-d\TH:i', $ts);
    }
}

if (!function_exists('get_current_shift_range')) {
    function get_current_shift_range(?int $timestamp = null): array
    {
        $timestamp = $timestamp ?? time();

        $hour = (int) date('G', $timestamp);
        $today = date('Y-m-d', $timestamp);
        $yesterday = date('Y-m-d', strtotime('-1 day', $timestamp));
        $tomorrow = date('Y-m-d', strtotime('+1 day', $timestamp));

        if ($hour >= 6 && $hour < 18) {
            $start = $today . ' 06:00';
            $end = $today . ' 18:00';
        } elseif ($hour >= 18) {
            $start = $today . ' 18:00';
            $end = $tomorrow . ' 06:00';
        } else {
            $start = $yesterday . ' 18:00';
            $end = $today . ' 06:00';
        }

        return [$start, $end];
    }
}

if (!function_exists('get_previous_shift_range')) {
    function get_previous_shift_range(?int $timestamp = null): array
    {
        $timestamp = $timestamp ?? time();
        [$currentStart] = get_current_shift_range($timestamp);

        $previousStartTs = strtotime($currentStart . ' -12 hours');
        $previousEndTs = strtotime($currentStart);

        return [
            date('Y-m-d H:i', $previousStartTs),
            date('Y-m-d H:i', $previousEndTs),
        ];
    }
}

if (!function_exists('solid_diff_minutes_rows')) {
    function solid_diff_minutes_rows(array $rows): array
    {
        $chronological = array_reverse($rows);
        $prevTs = null;

        foreach ($chronological as &$row) {
            $currentTs = null;

            if (!empty($row['log_date']) && !empty($row['log_time'])) {
                $currentTs = strtotime($row['log_date'] . ' ' . $row['log_time']);
            }

            if ($currentTs !== null && $prevTs !== null) {
                $row['_diff_minutes'] = round(($currentTs - $prevTs) / 60, 2);
            } else {
                $row['_diff_minutes'] = null;
            }

            if ($currentTs !== null) {
                $prevTs = $currentTs;
            }
        }
        unset($row);

        return array_reverse($chronological);
    }
}

if (!function_exists('solid_diff_series')) {
    function solid_diff_series(array $rows): array
    {
        $out = [];
        foreach (array_reverse($rows) as $row) {
            if (isset($row['_diff_minutes']) && $row['_diff_minutes'] !== null && is_numeric($row['_diff_minutes'])) {
                $out[] = (float) $row['_diff_minutes'];
            } else {
                $out[] = null;
            }
        }
        return $out;
    }
}

function nullIfBlank($value)
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function get_range_filter_state(bool $defaultToCurrentShift = true): array
{
    $rangeStart = trim($_GET['start'] ?? '');
    $rangeEnd = trim($_GET['end'] ?? '');
    $quickRange = trim($_GET['quick'] ?? '');
    $usedDefaultShift = false;

    if ($quickRange !== '') {
        $now = time();

        switch ($quickRange) {
            case 'current_shift':
                [$rangeStart, $rangeEnd] = get_current_shift_range($now);
                break;

            case 'previous_shift':
                [$rangeStart, $rangeEnd] = get_previous_shift_range($now);
                break;

            case 'today':
                $rangeStart = date('Y-m-d 00:00', $now);
                $rangeEnd = date('Y-m-d H:i', $now);
                break;

            case '24h':
                $rangeStart = date('Y-m-d H:i', strtotime('-24 hours', $now));
                $rangeEnd = date('Y-m-d H:i', $now);
                break;

            case '7d':
                $rangeStart = date('Y-m-d H:i', strtotime('-7 days', $now));
                $rangeEnd = date('Y-m-d H:i', $now);
                break;

            case 'clear':
                $rangeStart = '';
                $rangeEnd = '';
                break;
        }
    }

    if ($defaultToCurrentShift && $rangeStart === '' && $rangeEnd === '' && $quickRange === '') {
        [$rangeStart, $rangeEnd] = get_current_shift_range();
        $usedDefaultShift = true;
    }

    $startSql = null;
    $endSql = null;
    $rangeError = '';

    if ($rangeStart !== '') {
        $ts = strtotime($rangeStart);
        if ($ts === false) {
            $rangeError = 'Invalid start date/time.';
        } else {
            $startSql = date('Y-m-d H:i:s', $ts);
        }
    }

    if ($rangeEnd !== '') {
        $ts = strtotime($rangeEnd);
        if ($ts === false) {
            $rangeError = 'Invalid end date/time.';
        } else {
            $endSql = date('Y-m-d H:i:s', $ts);
        }
    }

    if ($rangeError === '' && $startSql !== null && $endSql !== null && strtotime($startSql) > strtotime($endSql)) {
        $rangeError = 'Start date/time must be earlier than end date/time.';
    }

    return [
        'start' => $rangeStart,
        'end' => $rangeEnd,
        'start_sql' => $startSql,
        'end_sql' => $endSql,
        'quick' => $quickRange,
        'error' => $rangeError,
        'active' => ($rangeStart !== '' || $rangeEnd !== ''),
        'used_default_shift' => $usedDefaultShift,
    ];
}

function build_log_range_where(array $range): array
{
    $where = [];
    $params = [];

    if (($range['error'] ?? '') === '') {
        if (!empty($range['start_sql'])) {
            $where[] = "TIMESTAMP(log_date, log_time) >= :start_dt";
            $params[':start_dt'] = $range['start_sql'];
        }

        if (!empty($range['end_sql'])) {
            $where[] = "TIMESTAMP(log_date, log_time) <= :end_dt";
            $params[':end_dt'] = $range['end_sql'];
        }
    }

    return [
        'sql' => $where ? (' WHERE ' . implode(' AND ', $where)) : '',
        'params' => $params,
    ];
}

function fetch_log_rows(PDO $pdo, string $table, array $range, string $orderBy = 'log_date DESC, log_time DESC, id DESC'): array
{
    $filter = build_log_range_where($range);
    $stmt = $pdo->prepare("SELECT * FROM {$table}" . $filter['sql'] . " ORDER BY {$orderBy}");
    $stmt->execute($filter['params']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_latest_row(PDO $pdo, string $table, string $orderBy = 'id DESC'): array
{
    $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY {$orderBy} LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function range_summary_text(array $range, string $defaultText = 'Current shift block'): string
{
    if (!empty($range['start']) || !empty($range['end'])) {
        $fromText = !empty($range['start']) ? date('d/m/Y H:i', strtotime($range['start'])) : 'Beginning';
        $toText = !empty($range['end']) ? date('d/m/Y H:i', strtotime($range['end'])) : 'Now';
        return $fromText . ' → ' . $toText;
    }

    return $defaultText;
}

function render_dashboard_range_filter(array $range): void
{
    ?>
    <form method="get" class="filter-form">
        <div class="range-layout">
            <div class="range-inputs">
                <div class="range-row">
                    <label for="start">From</label>
                    <input type="datetime-local" id="start" name="start"
                        value="<?= h(to_datetime_local_value($range['start'] ?? '')) ?>">
                </div>

                <div class="range-row">
                    <label for="end">To</label>
                    <input type="datetime-local" id="end" name="end"
                        value="<?= h(to_datetime_local_value($range['end'] ?? '')) ?>">
                </div>
            </div>

            <div class="range-buttons">
                <div class="filter-actions">
                    <button type="submit" class="btn">Apply Range</button>
                    <a href="<?= h($_SERVER['PHP_SELF']) ?>" class="btn">Clear</a>
                </div>

                <div class="quick-actions">
                    <button type="submit" name="quick" value="current_shift" class="btn btn-quick">Current Shift</button>
                    <button type="submit" name="quick" value="previous_shift" class="btn btn-quick">Previous Shift</button>
                    <button type="submit" name="quick" value="today" class="btn btn-quick">Today</button>
                    <button type="submit" name="quick" value="24h" class="btn btn-quick">Last 24 Hours</button>
                    <button type="submit" name="quick" value="7d" class="btn btn-quick">Last 7 Days</button>
                </div>
            </div>
        </div>
    </form>
    <?php
}

function render_range_filter(array $range, string $message = 'Filtering table to selected range'): void
{
    ?>
    <div class="list-filter-card">
        <div class="list-filter-title">Date / Time Range</div>

        <form method="get" class="list-filter-form">
            <div class="list-range-layout">
                <div class="list-range-inputs">
                    <div class="list-range-row">
                        <label for="start">From</label>
                        <input type="datetime-local" id="start" name="start"
                            value="<?= h(to_datetime_local_value($range['start'] ?? '')) ?>">
                    </div>

                    <div class="list-range-row">
                        <label for="end">To</label>
                        <input type="datetime-local" id="end" name="end"
                            value="<?= h(to_datetime_local_value($range['end'] ?? '')) ?>">
                    </div>
                </div>

                <div class="list-range-buttons">
                    <div class="list-filter-actions">
                        <button type="submit" class="btn">Apply Range</button>
                        <a href="<?= h($_SERVER['PHP_SELF']) ?>" class="btn">Clear</a>
                    </div>

                    <div class="list-quick-actions">
                        <button type="submit" name="quick" value="current_shift" class="btn btn-quick">Current Shift</button>
                        <button type="submit" name="quick" value="previous_shift" class="btn btn-quick">Previous Shift</button>
                        <button type="submit" name="quick" value="today" class="btn btn-quick">Today</button>
                        <button type="submit" name="quick" value="24h" class="btn btn-quick">Last 24 Hours</button>
                        <button type="submit" name="quick" value="7d" class="btn btn-quick">Last 7 Days</button>
                    </div>
                </div>
            </div>
        </form>

        <?php if (($range['error'] ?? '') !== ''): ?>
            <div class="list-range-error"><?= h($range['error']) ?></div>
        <?php elseif (!empty($range['used_default_shift'])): ?>
            <div class="list-range-active">Showing current 12 hour shift block</div>
        <?php elseif (!empty($range['active'])): ?>
            <div class="list-range-active"><?= h($message) ?></div>
        <?php else: ?>
            <div class="list-range-active">Showing all available records</div>
        <?php endif; ?>
    </div>
    <?php
}

function get_project_flow_kpis(PDO $pdo, array $range): array
{
    if (!tableExists($pdo, 'project_flow_logs')) {
        return [
            'count' => 0,
            'oil' => null,
            'water' => null,
            'solid_waste' => null,
            'tricanter' => null,
            'nozzle' => null,
        ];
    }

    $filter = build_log_range_where($range);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM project_flow_logs" . $filter['sql']);
    $countStmt->execute($filter['params']);
    $count = (int) $countStmt->fetchColumn();

    if ($count === 0) {
        return [
            'count' => 0,
            'oil' => null,
            'water' => null,
            'solid_waste' => null,
            'tricanter' => null,
            'nozzle' => null,
        ];
    }

    $firstStmt = $pdo->prepare("
        SELECT *
        FROM project_flow_logs" . $filter['sql'] . "
        ORDER BY log_date ASC, log_time ASC, id ASC
        LIMIT 1
    ");
    $firstStmt->execute($filter['params']);
    $first = $firstStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $lastStmt = $pdo->prepare("
        SELECT *
        FROM project_flow_logs" . $filter['sql'] . "
        ORDER BY log_date DESC, log_time DESC, id DESC
        LIMIT 1
    ");
    $lastStmt->execute($filter['params']);
    $last = $lastStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $delta = function ($lastValue, $firstValue) {
        if (!is_numeric($lastValue) || !is_numeric($firstValue)) {
            return null;
        }
        return (float) $lastValue - (float) $firstValue;
    };

    return [
        'count' => $count,
        'oil' => $delta($last['total_recovered_oil'] ?? null, $first['total_recovered_oil'] ?? null),
        'water' => $delta($last['total_recovered_water'] ?? null, $first['total_recovered_water'] ?? null),
        'solid_waste' => $delta($last['total_solid_waste'] ?? null, $first['total_solid_waste'] ?? null),
        'tricanter' => $delta($last['total_tricanter'] ?? null, $first['total_tricanter'] ?? null),
        'nozzle' => $delta($last['total_nozzle'] ?? null, $first['total_nozzle'] ?? null),
    ];
}

/* =========================
   MONITOR HELPERS
   ========================= */

function getSetting(PDO $pdo, string $key, $default = null)
{
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? $value : $default;
}

function setSetting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$key, $value]);
}

function tableExists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");
    $stmt->execute([$table]);

    $cache[$table] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$table];
}

function formatCountdownSeconds(int $seconds): string
{
    if ($seconds <= 0) {
        return 'OVERDUE';
    }

    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    return sprintf('%02d:%02d', $minutes, $secs);
}

function formatElapsedTime(int $seconds): string
{
    if ($seconds < 60) {
        return $seconds . 's ago';
    }

    if ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return $minutes . 'm ' . $secs . 's ago';
    }

    if ($seconds < 86400) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . 'h ' . $minutes . 'm ago';
    }

    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    return $days . 'd ' . $hours . 'h ago';
}

function getLastLogDateTime(PDO $pdo, string $table): ?string
{
    if (!tableExists($pdo, $table)) {
        return null;
    }

    $sql = "
        SELECT CONCAT(log_date, ' ', log_time) AS dt
        FROM {$table}
        WHERE log_date IS NOT NULL
          AND log_time IS NOT NULL
          AND log_date <> ''
          AND log_time <> ''
        ORDER BY log_date DESC, log_time DESC, id DESC
        LIMIT 1
    ";

    $stmt = $pdo->query($sql);
    $dt = $stmt->fetchColumn();

    return $dt ?: null;
}

function buildMonitoringData(PDO $pdo): array
{
    $masterEnabled = (int)getSetting($pdo, 'monitor_master', '1') === 1;
    $refreshSeconds = max(5, (int)getSetting($pdo, 'monitor_refresh_seconds', '30'));

    $items = [
        'nozzle' => [
            'label' => 'Nozzle',
            'table' => 'nozzle_logs',
            'enabled' => (int)getSetting($pdo, 'monitor_nozzle_enabled', '1') === 1,
            'minutes' => max(1, (int)getSetting($pdo, 'monitor_nozzle_minutes', '60')),
        ],
        'tricanter' => [
            'label' => 'Tricanter',
            'table' => 'tricanter_logs',
            'enabled' => (int)getSetting($pdo, 'monitor_tricanter_enabled', '1') === 1,
            'minutes' => max(1, (int)getSetting($pdo, 'monitor_tricanter_minutes', '60')),
        ],
        'solid_waste' => [
            'label' => 'Solid Waste',
            'table' => 'solid_waste_logs',
            'enabled' => (int)getSetting($pdo, 'monitor_solid_waste_enabled', '1') === 1,
            'minutes' => max(1, (int)getSetting($pdo, 'monitor_solid_waste_minutes', '60')),
        ],
        'sample' => [
            'label' => 'Sample',
            'table' => 'sample_logs',
            'enabled' => (int)getSetting($pdo, 'monitor_sample_enabled', '1') === 1,
            'minutes' => max(1, (int)getSetting($pdo, 'monitor_sample_minutes', '60')),
        ],
        'gas_test' => [
            'label' => 'Gas Test',
            'table' => 'gas_test_logs',
            'enabled' => (int)getSetting($pdo, 'monitor_gas_test_enabled', '1') === 1,
            'minutes' => max(1, (int)getSetting($pdo, 'monitor_gas_test_minutes', '60')),
        ],
        'project_flow' => [
            'label' => 'Project Flow',
            'table' => 'project_flow_logs',
            'enabled' => (int)getSetting($pdo, 'monitor_project_flow_enabled', '1') === 1,
            'minutes' => max(1, (int)getSetting($pdo, 'monitor_project_flow_minutes', '60')),
        ],
        'pump_values' => [
            'label' => 'Pump Values',
            'table' => 'pump_values_logs',
            'enabled' => (int)getSetting($pdo, 'monitor_pump_values_enabled', '1') === 1,
            'minutes' => max(1, (int)getSetting($pdo, 'monitor_pump_values_minutes', '60')),
        ],
    ];

    $now = time();
    $masterState = $masterEnabled ? 'OK' : 'MASTER OFF';

    foreach ($items as &$item) {
        $item['exists'] = tableExists($pdo, $item['table']);
        $item['last_entry'] = null;
        $item['last_entry_display'] = 'No data';
        $item['since_seconds'] = null;
        $item['since_text'] = 'No data';
        $item['remaining_seconds'] = null;
        $item['countdown'] = '--';

        if (!$masterEnabled) {
            $item['status'] = 'MASTER OFF';
            continue;
        }

        if (!$item['enabled']) {
            $item['status'] = 'OFF';
            continue;
        }

        if (!$item['exists']) {
            $item['status'] = 'NOT SET UP';
            if ($masterState !== 'ALARM') {
                $masterState = 'WARNING';
            }
            continue;
        }

        $last = getLastLogDateTime($pdo, $item['table']);
        $item['last_entry'] = $last;
        $item['last_entry_display'] = $last ? date('d/m/Y H:i', strtotime($last)) : 'No data';

        if (!$last) {
            $item['status'] = 'NO DATA';
            $item['countdown'] = 'NO DATA';
            if ($masterState !== 'ALARM') {
                $masterState = 'WARNING';
            }
            continue;
        }

        $lastTs = strtotime($last);
        $limitTs = $lastTs + ($item['minutes'] * 60);
        $remaining = $limitTs - $now;
        $since = max(0, $now - $lastTs);

        $item['remaining_seconds'] = $remaining;
        $item['since_seconds'] = $since;
        $item['since_text'] = formatElapsedTime($since);
        $item['countdown'] = formatCountdownSeconds($remaining);

        if ($remaining <= 0) {
            $item['status'] = 'OVERDUE';
            $masterState = 'ALARM';
        } elseif ($remaining <= 300) {
            $item['status'] = 'WARNING';
            if ($masterState !== 'ALARM') {
                $masterState = 'WARNING';
            }
        } else {
            $item['status'] = 'OK';
        }
    }
    unset($item);

    return [
        'master_enabled' => $masterEnabled,
        'refresh_seconds' => $refreshSeconds,
        'master_state' => $masterState,
        'items' => $items,
    ];
}

function monitoring_state_rank($state)
{
    static $map = [
        'OK' => 0,
        'MASTER OFF' => 0,
        'DISABLED' => 0,
        'NOT SET UP' => 1,
        'NO DATA' => 2,
        'WARNING' => 3,
        'OVERDUE' => 4,
    ];

    return $map[$state] ?? 0;
}

function monitoring_state_badge_class($state)
{
    switch ($state) {
        case 'OVERDUE':
            return 'danger';
        case 'WARNING':
            return 'warn';
        case 'NO DATA':
        case 'NOT SET UP':
            return 'muted';
        case 'OK':
            return 'ok';
        case 'MASTER OFF':
        case 'DISABLED':
        default:
            return 'off';
    }
}

function monitoring_has_issue($item)
{
    if (!is_array($item)) {
        return false;
    }

    $state = strtoupper(trim((string)($item['state'] ?? '')));

    return in_array($state, ['WARNING', 'OVERDUE', 'NO DATA', 'NOT SET UP'], true);
}

function build_compact_monitor_group(array $monitoring, array $keys, $title = 'Process Monitoring')
{
    $items = [];
    $issueCount = 0;
    $highestRank = 0;

    foreach ($keys as $key) {
        if (!isset($monitoring[$key])) {
            continue;
        }

        $item = $monitoring[$key];
        $item['key'] = $key;
        $items[$key] = $item;

        $rank = monitoring_state_rank($item['state'] ?? '');
        if ($rank > $highestRank) {
            $highestRank = $rank;
        }

        if (monitoring_has_issue($item)) {
            $issueCount++;
        }
    }

    $overallState = 'OK';
    if ($highestRank >= 4) {
        $overallState = 'OVERDUE';
    } elseif ($highestRank >= 3) {
        $overallState = 'WARNING';
    } elseif ($highestRank >= 2) {
        $overallState = 'NO DATA';
    } elseif ($highestRank >= 1) {
        $overallState = 'NOT SET UP';
    }

    return [
        'title' => $title,
        'items' => $items,
        'issue_count' => $issueCount,
        'has_issue' => $issueCount > 0,
        'overall_state' => $overallState,
    ];
}