<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
requireRole(['admin', 'operator', 'viewer']);

$canEdit = in_array(currentRole(), ['admin', 'operator'], true);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec("CREATE TABLE IF NOT EXISTS nozzle_attributes (
    nozzle_number TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    operational_condition TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$seed = $pdo->prepare('INSERT IGNORE INTO nozzle_attributes (nozzle_number, operational_condition) VALUES (?, 1)');
for ($number = 1; $number <= 16; $number++) {
    $seed->execute([$number]);
}

if (empty($_SESSION['nozzle_overview_token'])) {
    $_SESSION['nozzle_overview_token'] = bin2hex(random_bytes(24));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        http_response_code(403);
        die('Access denied. Operator or administrator access required.');
    }
    if (!hash_equals($_SESSION['nozzle_overview_token'], (string)($_POST['token'] ?? ''))) {
        http_response_code(400);
        die('Invalid request token.');
    }

    $number = filter_input(INPUT_POST, 'nozzle_number', FILTER_VALIDATE_INT);
    $condition = filter_input(INPUT_POST, 'operational_condition', FILTER_VALIDATE_INT);
    if ($number === false || $number < 1 || $number > 16 || !in_array($condition, [0, 1, 2], true)) {
        http_response_code(422);
        die('Invalid nozzle condition.');
    }

    $update = $pdo->prepare('UPDATE nozzle_attributes SET operational_condition = ? WHERE nozzle_number = ?');
    $update->execute([$condition, $number]);
    header('Location: nozzle_overview.php');
    exit;
}

function nozzleOverviewData(PDO $pdo): array
{
    $latest = $pdo->query('SELECT * FROM nozzle_logs ORDER BY id DESC LIMIT 1')->fetch() ?: null;
    $activeNozzle = null;
    if ($latest && preg_match('/\d+/', (string)($latest['nozzle'] ?? ''), $match)) {
        $candidate = (int)$match[0];
        if ($candidate >= 1 && $candidate <= 16) $activeNozzle = $candidate;
    }

    $timestamp = null;
    if ($latest && !empty($latest['log_date']) && !empty($latest['log_time'])) {
        $parsed = strtotime($latest['log_date'] . ' ' . $latest['log_time']);
        if ($parsed !== false) $timestamp = $parsed;
    }

    $conditions = array_fill(1, 16, 1);
    foreach ($pdo->query('SELECT nozzle_number, operational_condition FROM nozzle_attributes ORDER BY nozzle_number') as $row) {
        $conditions[(int)$row['nozzle_number']] = (int)$row['operational_condition'];
    }

    $parked = array_fill(1, 16, null);
    for ($number = 1; $number <= 16; $number++) {
        $key = 'nozzle_' . $number . '_parked';
        if ($latest !== null && array_key_exists($key, $latest) && $latest[$key] !== null && $latest[$key] !== '') {
            $parked[$number] = (int)$latest[$key] === 1;
        }
    }

    [$shiftStart, $shiftEnd] = get_current_shift_range();
    $shiftStartTs = strtotime($shiftStart);
    $shiftEndTs = strtotime($shiftEnd);
    $shiftChanges = [];
    $latestChanges = [];
    $newer = null;
    $changeRows = $pdo->query('SELECT id, nozzle, log_date, log_time FROM nozzle_logs ORDER BY id DESC');
    while ($row = $changeRows->fetch()) {
        $match = [];
        $rowNozzle = preg_match('/\d+/', (string)($row['nozzle'] ?? ''), $match) ? (int)$match[0] : null;
        if ($newer !== null && $rowNozzle !== null && $newer['nozzle'] !== null && $rowNozzle !== $newer['nozzle']) {
            $changeTs = strtotime((string)$newer['log_date'] . ' ' . (string)$newer['log_time']);
            $change = ['id' => (int)$newer['id'], 'date' => date('d/m/Y', $changeTs), 'time' => date('g:i:s A', $changeTs), 'from' => $rowNozzle, 'to' => $newer['nozzle']];
            if (count($latestChanges) < 3) $latestChanges[] = $change;
            if ($changeTs >= $shiftStartTs && $changeTs < $shiftEndTs) $shiftChanges[] = $change;
        }
        $newer = ['id' => (int)$row['id'], 'nozzle' => $rowNozzle, 'log_date' => $row['log_date'], 'log_time' => $row['log_time']];
        $rowTs = strtotime((string)$row['log_date'] . ' ' . (string)$row['log_time']);
        if (count($latestChanges) >= 3 && $rowTs < $shiftStartTs) break;
    }
    $changes = count($shiftChanges) > 3 ? $shiftChanges : $latestChanges;

    return [
        'online' => $timestamp !== null && max(0, time() - $timestamp) <= 600,
        'active_nozzle' => $activeNozzle,
        'last_updated' => $timestamp ? date('d/m/Y g:i:s A', $timestamp) : 'No nozzle data',
        'conditions' => $conditions,
        'parked' => $parked,
        'changes' => $changes,
        'changes_scope' => count($shiftChanges) > 3 ? 'Current shift' : 'Latest changes',
    ];
}

$data = nozzleOverviewData($pdo);
if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

$positions = [
    1 => [50, 7], 2 => [23, 18], 3 => [77, 18], 4 => [13, 29], 5 => [87, 29],
    6 => [50, 18], 7 => [50, 43], 8 => [26, 43], 9 => [74, 43], 10 => [13, 55],
    11 => [87, 55], 12 => [31, 70], 13 => [69, 70], 14 => [21, 81], 15 => [79, 81], 16 => [50, 92],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nozzle Overview</title>
    <link rel="icon" href="c69t.ico">
    <link rel="stylesheet" href="nozzle_overview.css">
</head>
<body>
<?php require __DIR__ . '/nav.php'; ?>
<main class="overview-shell">
    <header class="page-header">
        <div class="brand-block">
            <div class="logo-row">
                <img src="MoombaTankCleaningLogoTransparent.PNG" alt="Moomba Tank Cleaning">
                <img src="Contract69TanksLogoTransparent.png" alt="Contract 69 Tanks">
            </div>
            <div><div class="eyebrow">Tank cleaning system</div><h1>Nozzle Overview</h1></div>
        </div>
        <div class="system-card <?= $data['online'] ? 'online' : 'offline' ?>" id="systemCard">
            <span class="status-lamp"></span>
            <div><strong id="systemLabel">System <?= $data['online'] ? 'online' : 'offline' ?></strong><small>Latest nozzle entry <span id="lastUpdated"><?= h($data['last_updated']) ?></span></small></div>
        </div>
    </header>

    <section class="overview-grid">
        <article class="layout-card">
            <div class="card-heading"><div><span class="eyebrow">Live position</span><h2>Tank nozzle layout</h2></div><div class="legend"><span><i class="legend-active"></i>Active</span><span><i class="legend-operational"></i>Operational</span><span><i class="legend-unavailable"></i>Unavailable</span><span><i class="legend-parked"></i>Parked</span><span><i class="legend-not-parked"></i>Not parked</span><span><i class="legend-unknown"></i>Unknown</span></div></div>
            <div class="tank-layout" id="tankLayout" aria-label="Sixteen nozzle tank layout">
                <svg viewBox="0 0 100 100" aria-hidden="true">
                    <circle class="tank-outline" cx="50" cy="50" r="47" />
                    <g class="pipe-lines">
                        <path d="M50 7 V92 M23 18 H77 M23 18 L13 29 M77 18 L87 29 M26 43 H74 M26 43 L13 55 M74 43 L87 55 M31 70 H69 M31 70 L21 81 M69 70 L79 81" />
                    </g>
                </svg>
                <?php foreach ($positions as $number => [$left, $top]):
                    $active = $data['active_nozzle'] === $number;
                    $condition = $data['conditions'][$number];
                    $conditionClass = $condition === 1 ? 'operational' : ($condition === 0 ? 'unavailable' : 'unknown');
                    $conditionLabel = $condition === 1 ? 'operational' : ($condition === 0 ? 'unavailable' : 'unknown');
                    $isParked = $data['parked'][$number];
                    $parkedClass = $isParked === null ? 'parked-unknown' : ($isParked ? 'parked' : 'not-parked');
                    $parkedLabel = $isParked === null ? 'parking unknown' : ($isParked ? 'parked' : 'not parked'); ?>
                    <div class="nozzle-node<?= $active ? ' active' : '' ?> <?= $conditionClass ?> <?= $parkedClass ?>" data-nozzle="<?= $number ?>" style="--left:<?= $left ?>%;--top:<?= $top ?>%" aria-label="Nozzle <?= $number ?>, <?= $active ? 'active, ' : '' ?><?= $conditionLabel ?>, <?= $parkedLabel ?>">
                        <span class="node-number"><?= $number ?></span><span class="node-lamp"></span><span class="condition-lamp" title="<?= ucfirst($conditionLabel) ?>"></span><span class="parked-lamp" title="<?= ucfirst($parkedLabel) ?>"></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>

        <aside class="condition-card">
            <div class="card-heading"><div><span class="eyebrow">Manual status</span><h2>Operational condition</h2></div></div>
            <p class="card-copy">Set whether each nozzle is available for operation. Live activity is read from the latest nozzle log.</p>
            <div class="condition-list">
                <?php foreach ($data['conditions'] as $number => $condition):
                    $conditionLabel = $condition === 1 ? 'Operational' : ($condition === 0 ? 'Unavailable' : 'Unknown');
                    $conditionClass = $condition === 1 ? 'good' : ($condition === 0 ? 'bad' : 'unknown'); ?>
                    <form method="post" class="condition-row">
                        <input type="hidden" name="token" value="<?= h($_SESSION['nozzle_overview_token']) ?>">
                        <input type="hidden" name="nozzle_number" value="<?= $number ?>">
                        <span class="condition-number">N<?= $number ?></span>
                        <span class="condition-state <?= $conditionClass ?>"><i></i><?= $conditionLabel ?></span>
                        <select name="operational_condition" aria-label="Nozzle <?= $number ?> operational condition" <?= !$canEdit ? 'disabled' : '' ?> onchange="this.form.submit()">
                            <option value="1" <?= $condition === 1 ? 'selected' : '' ?>>Operational</option>
                            <option value="0" <?= $condition === 0 ? 'selected' : '' ?>>Unavailable</option>
                            <option value="2" <?= $condition === 2 ? 'selected' : '' ?>>Unknown</option>
                        </select>
                    </form>
                <?php endforeach; ?>
            </div>
            <?php if (!$canEdit): ?><p class="read-only-note">Viewer access is read-only.</p><?php endif; ?>
        </aside>
    </section>
    <section class="changes-card">
        <div class="card-heading"><div><span class="eyebrow" id="changesScope"><?= h($data['changes_scope']) ?></span><h2>Nozzle number changes</h2></div></div>
        <div class="table-wrap"><table><thead><tr><th>Date</th><th>Time</th><th>Previous nozzle</th><th>New nozzle</th></tr></thead><tbody id="changesBody">
        <?php if (!$data['changes']): ?><tr><td colspan="4" class="empty-row">No nozzle number changes recorded.</td></tr><?php else: foreach ($data['changes'] as $change): ?>
            <tr><td><?= h($change['date']) ?></td><td><?= h($change['time']) ?></td><td>N<?= (int)$change['from'] ?></td><td><strong>N<?= (int)$change['to'] ?></strong></td></tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
    </section>
</main>
<script>
(() => {
    const apply = data => {
        const card = document.getElementById('systemCard');
        card.classList.toggle('online', data.online);
        card.classList.toggle('offline', !data.online);
        document.getElementById('systemLabel').textContent = `System ${data.online ? 'online' : 'offline'}`;
        document.getElementById('lastUpdated').textContent = data.last_updated;
        document.querySelectorAll('.nozzle-node').forEach(node => {
            const number = Number(node.dataset.nozzle);
            const active = number === data.active_nozzle;
            const condition = Number(data.conditions[number]);
            const parked = data.parked[number];
            node.classList.toggle('active', active);
            node.classList.toggle('operational', condition === 1);
            node.classList.toggle('unavailable', condition === 0);
            node.classList.toggle('unknown', condition === 2);
            node.classList.toggle('parked', parked === true);
            node.classList.toggle('not-parked', parked === false);
            node.classList.toggle('parked-unknown', parked === null);
            const label = condition === 1 ? 'operational' : (condition === 0 ? 'unavailable' : 'unknown');
            const parkedLabel = parked === null ? 'parking unknown' : (parked ? 'parked' : 'not parked');
            node.querySelector('.condition-lamp').title = label[0].toUpperCase() + label.slice(1);
            node.querySelector('.parked-lamp').title = parkedLabel[0].toUpperCase() + parkedLabel.slice(1);
            node.setAttribute('aria-label', `Nozzle ${number}, ${active ? 'active, ' : ''}${label}, ${parkedLabel}`);
        });
        document.getElementById('changesScope').textContent = data.changes_scope;
        const body = document.getElementById('changesBody');
        body.replaceChildren();
        if (!data.changes.length) {
            const row = body.insertRow();
            const cell = row.insertCell();
            cell.colSpan = 4;
            cell.className = 'empty-row';
            cell.textContent = 'No nozzle number changes recorded.';
        } else data.changes.forEach(change => {
            const row = body.insertRow();
            [change.date, change.time, `N${change.from}`, `N${change.to}`].forEach((value, index) => {
                const cell = row.insertCell();
                cell.textContent = value;
                if (index === 3) cell.className = 'new-nozzle';
            });
        });
    };
    const refresh = () => fetch('nozzle_overview.php?format=json', {cache: 'no-store'}).then(r => r.ok ? r.json() : Promise.reject()).then(apply).catch(() => {});
    window.setInterval(refresh, 15000);
})();
</script>
</body>
</html>
