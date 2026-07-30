<?php
require_once "config.php";
requireRole(['admin', 'operator', 'viewer']);

$canEdit = currentRole() === 'admin';

/*
|--------------------------------------------------------------------------
| STORAGE
|--------------------------------------------------------------------------
| The page creates its own single-row settings table automatically.
*/
$pdo->exec("
    CREATE TABLE IF NOT EXISTS tricanter_height_settings (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        start_project_flow_id BIGINT UNSIGNED NULL,
        start_flow_total DECIMAL(14,4) NULL,
        start_height_mm DECIMAL(12,2) NULL,
        target_height_mm DECIMAL(12,2) NULL,
        diameter_m DECIMAL(10,3) NULL,
        updated_by BIGINT UNSIGNED NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");


try {
    $pdo->exec("ALTER TABLE tricanter_height_settings ADD COLUMN target_height_mm DECIMAL(12,2) NULL AFTER start_height_mm");
} catch (PDOException $e) {
    if ((string)$e->getCode() !== '42S21') {
        throw $e;
    }
}

try {
    $pdo->exec("ALTER TABLE tricanter_height_settings ADD COLUMN diameter_m DECIMAL(10,3) NULL AFTER target_height_mm");
} catch (PDOException $e) {
    if ((string)$e->getCode() !== '42S21') {
        throw $e;
    }
}

$pdo->exec("
    INSERT IGNORE INTO tricanter_height_settings
        (id)
    VALUES
        (1)
");

function latestTricanterFlow(PDO $pdo): ?array
{
    $stmt = $pdo->query("
        SELECT id, log_date, log_time, total_tricanter
        FROM project_flow_logs
        WHERE total_tricanter IS NOT NULL
        ORDER BY log_date DESC, log_time DESC, id DESC
        LIMIT 1
    ");

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function latestTricanterRate(PDO $pdo): ?array
{
    $stmt = $pdo->query("
        SELECT id, log_date, log_time, feed_rate
        FROM tricanter_logs
        WHERE feed_rate IS NOT NULL
        ORDER BY log_date DESC, log_time DESC, id DESC
        LIMIT 1
    ");

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function selectedFlowRows(PDO $pdo, int $limit = 5000): array
{
    $limit = max(10, min(20000, $limit));

    $stmt = $pdo->query("
        SELECT id, log_date, log_time, total_tricanter
        FROM project_flow_logs
        WHERE total_tricanter IS NOT NULL
        ORDER BY log_date DESC, log_time DESC, id DESC
        LIMIT {$limit}
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function loadHeightSettings(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT *
        FROM tricanter_height_settings
        WHERE id = 1
        LIMIT 1
    ");

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        http_response_code(403);
        die('Access denied.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $startHeight = filter_input(INPUT_POST, 'start_height_mm', FILTER_VALIDATE_FLOAT);
        $flowRowId = filter_input(INPUT_POST, 'start_project_flow_id', FILTER_VALIDATE_INT);
        $targetHeight = filter_input(INPUT_POST, 'target_height_mm', FILTER_VALIDATE_FLOAT);
        $diameter = filter_input(INPUT_POST, 'diameter_m', FILTER_VALIDATE_FLOAT);

        if ($startHeight === false || $startHeight === null || $startHeight < 0) {
            $error = 'Enter a valid starting height in millimetres.';
        } elseif ($targetHeight === false || $targetHeight === null || $targetHeight < 0 || $targetHeight >= $startHeight) {
            $error = 'Enter a target height that is lower than the starting height.';
        } elseif ($diameter === false || $diameter === null || $diameter <= 0) {
            $error = 'Enter a valid tank diameter in metres.';
        } elseif (!$flowRowId) {
            $error = 'Select a tricanter project-flow starting point.';
        } else {
            $stmt = $pdo->prepare("
                SELECT id, total_tricanter
                FROM project_flow_logs
                WHERE id = ?
                  AND total_tricanter IS NOT NULL
                LIMIT 1
            ");
            $stmt->execute([$flowRowId]);
            $flowRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$flowRow) {
                $error = 'The selected project-flow record could not be found.';
            } else {
                $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                $stmt = $pdo->prepare("
                    UPDATE tricanter_height_settings
                    SET start_project_flow_id = ?,
                        start_flow_total = ?,
                        start_height_mm = ?,
                        target_height_mm = ?,
                        diameter_m = ?,
                        updated_by = ?,
                        updated_at = NOW()
                    WHERE id = 1
                ");
                $stmt->execute([
                    (int)$flowRow['id'],
                    (float)$flowRow['total_tricanter'],
                    (float)$startHeight,
                    (float)$targetHeight,
                    (float)$diameter,
                    $userId
                ]);

                header('Location: tank_height.php?saved=1');
                exit;
            }
        }
    }

    if ($action === 'use_latest') {
        $startHeight = filter_input(INPUT_POST, 'start_height_mm', FILTER_VALIDATE_FLOAT);
        $targetHeight = filter_input(INPUT_POST, 'target_height_mm', FILTER_VALIDATE_FLOAT);
        $diameter = filter_input(INPUT_POST, 'diameter_m', FILTER_VALIDATE_FLOAT);
        $latest = latestTricanterFlow($pdo);

        if ($startHeight === false || $startHeight === null || $startHeight < 0) {
            $error = 'Enter a valid starting height in millimetres.';
        } elseif ($targetHeight === false || $targetHeight === null || $targetHeight < 0 || $targetHeight >= $startHeight) {
            $error = 'Enter a target height that is lower than the starting height.';
        } elseif ($diameter === false || $diameter === null || $diameter <= 0) {
            $error = 'Enter a valid tank diameter in metres.';
        } elseif (!$latest) {
            $error = 'No tricanter project-flow total is available.';
        } else {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $stmt = $pdo->prepare("
                UPDATE tricanter_height_settings
                SET start_project_flow_id = ?,
                    start_flow_total = ?,
                    start_height_mm = ?,
                    target_height_mm = ?,
                    diameter_m = ?,
                    updated_by = ?,
                    updated_at = NOW()
                WHERE id = 1
            ");
            $stmt->execute([
                (int)$latest['id'],
                (float)$latest['total_tricanter'],
                (float)$startHeight,
                (float)$targetHeight,
                (float)$diameter,
                $userId
            ]);

            header('Location: tank_height.php?saved=1');
            exit;
        }
    }

    if ($action === 'clear') {
        $stmt = $pdo->prepare("
            UPDATE tricanter_height_settings
            SET start_project_flow_id = NULL,
                start_flow_total = NULL,
                start_height_mm = NULL,
                target_height_mm = NULL,
                diameter_m = NULL,
                updated_by = ?,
                updated_at = NOW()
            WHERE id = 1
        ");
        $stmt->execute([isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null]);

        header('Location: tank_height.php?cleared=1');
        exit;
    }
}

if (isset($_GET['saved'])) {
    $success = 'Tank diameter, starting point, and heights saved.';
} elseif (isset($_GET['cleared'])) {
    $success = 'Saved calculation cleared.';
}

$settings = loadHeightSettings($pdo);
$latest = latestTricanterFlow($pdo);
$latestRate = latestTricanterRate($pdo);
$flowRows = selectedFlowRows($pdo);

/*
|--------------------------------------------------------------------------
| LIVE REFRESH
|--------------------------------------------------------------------------
| Uses the dashboard's standard 30-second live refresh interval.
| This avoids depending on differing settings-table column names.
*/
$refreshSeconds = 30;

$startFlow = isset($settings['start_flow_total']) && $settings['start_flow_total'] !== null
    ? (float)$settings['start_flow_total']
    : null;
$startHeight = isset($settings['start_height_mm']) && $settings['start_height_mm'] !== null
    ? (float)$settings['start_height_mm']
    : null;
$currentFlow = $latest ? (float)$latest['total_tricanter'] : null;
$targetHeight = isset($settings['target_height_mm']) && $settings['target_height_mm'] !== null
    ? (float)$settings['target_height_mm']
    : null;
$diameter = isset($settings['diameter_m']) && $settings['diameter_m'] !== null
    ? (float)$settings['diameter_m']
    : null;
$volumePerMillimetre = $diameter !== null && $diameter > 0
    ? pi() * pow($diameter / 2, 2) / 1000
    : null;
$tricanterRate = $latestRate ? (float)$latestRate['feed_rate'] : null;

$missingSetup = [];
if ($diameter === null || $diameter <= 0) $missingSetup[] = 'tank diameter';
if ($startHeight === null) $missingSetup[] = 'starting height';
if ($targetHeight === null) $missingSetup[] = 'target height';
if ($startFlow === null) $missingSetup[] = 'starting flow total';

$setupError = $missingSetup
    ? 'Tank height setup is incomplete. Enter and save: ' . implode(', ', $missingSetup) . '.'
    : '';

$setupComplete = !$missingSetup;
$flowUsed = ($setupComplete && $currentFlow !== null)
    ? $currentFlow - $startFlow
    : null;
$heightUsed = ($flowUsed !== null && $volumePerMillimetre !== null)
    ? $flowUsed / $volumePerMillimetre
    : null;
$currentHeight = ($startHeight !== null && $heightUsed !== null)
    ? $startHeight - $heightUsed
    : null;
$heightRemaining = ($currentHeight !== null && $targetHeight !== null)
    ? max(0.0, $currentHeight - $targetHeight)
    : null;
$volumeRemaining = ($heightRemaining !== null && $volumePerMillimetre !== null)
    ? $heightRemaining * $volumePerMillimetre
    : null;
$hoursToTarget = ($volumeRemaining !== null && $tricanterRate !== null && $tricanterRate > 0)
    ? $volumeRemaining / $tricanterRate
    : null;

function numberOrDash(?float $value, int $decimals = 2): string
{
    return $value === null ? '-' : number_format($value, $decimals);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="c69t.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tricanter Tank Height</title>
    <link rel="stylesheet" href="indexStyle.css">
    <style>
        .height-layout {
            display: grid;
            grid-template-columns: minmax(320px, .8fr) minmax(420px, 1.2fr);
            gap: 18px;
            align-items: start;
        }

        .height-form {
            display: grid;
            gap: 14px;
        }

        .field-group {
            display: grid;
            gap: 7px;
        }

        .field-group label {
            font-size: 12px;
            font-weight: 700;
            color: #b9d0e3;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .field-group input,
        .field-group select {
            width: 100%;
            box-sizing: border-box;
            background: #0a1a29;
            border: 1px solid #2a5377;
            border-radius: 10px;
            color: #fff;
            padding: 10px 12px;
            min-height: 42px;
        }

        .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .height-result {
            display: grid;
            gap: 16px;
        }

        .height-primary {
            padding: 26px;
            border-radius: 18px;
            border: 1px solid rgba(72, 196, 255, .28);
            background:
                radial-gradient(circle at top right, rgba(42, 153, 214, .22), transparent 42%),
                linear-gradient(180deg, rgba(14, 42, 65, .96), rgba(7, 24, 38, .96));
            box-shadow: 0 18px 42px rgba(0, 0, 0, .22);
        }

        .height-primary small {
            display: block;
            color: #b9d0e3;
            text-transform: uppercase;
            letter-spacing: .1em;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .height-primary strong {
            display: block;
            font-size: clamp(46px, 8vw, 88px);
            line-height: 1;
            color: #fff;
        }

        .height-primary span {
            color: #8fd9ff;
            font-size: 20px;
            font-weight: 700;
        }

        .target-card {
            padding: 22px;
            border-radius: 16px;
            border: 1px solid rgba(255, 193, 7, .28);
            background: linear-gradient(180deg, rgba(70, 54, 12, .55), rgba(24, 22, 12, .65));
        }

        .target-card strong {
            display: block;
            font-size: clamp(34px, 5vw, 58px);
            line-height: 1.05;
            color: #fff;
        }

        .target-card span {
            color: #ffd86b;
            font-weight: 700;
        }

        .height-kpis {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .height-note {
            margin-top: 12px;
            color: #a9bfd0;
            font-size: 13px;
            line-height: 1.55;
        }

        .notice {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, .1);
        }

        .notice.success {
            background: rgba(49, 177, 117, .13);
            color: #9ff0c5;
        }

        .notice.error {
            background: rgba(220, 72, 72, .14);
            color: #ffc1c1;
        }

        .live-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #45d98b;
            box-shadow: 0 0 12px rgba(69, 217, 139, .75);
            margin-right: 7px;
        }

        @media (max-width:900px) {
            .height-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width:600px) {
            .target-card {
                padding: 22px;
                border-radius: 16px;
                border: 1px solid rgba(255, 193, 7, .28);
                background: linear-gradient(180deg, rgba(70, 54, 12, .55), rgba(24, 22, 12, .65));
            }

            .target-card strong {
                display: block;
                font-size: clamp(34px, 5vw, 58px);
                line-height: 1.05;
                color: #fff;
            }

            .target-card span {
                color: #ffd86b;
                font-weight: 700;
            }

            .height-kpis {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="app-page">
    <?php require_once "nav.php"; ?>

    <div class="dashboard-shell">
        <div class="logo-row">
            <img src="MoombaTankCleaningLogoTransparent.PNG" alt="Moomba Tank Cleaning">
            <img src="Contract69TanksLogoTransparent.png" alt="Contract 69 Tanks">
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <div class="section-kicker">live calculation</div>
                    <h2>Tricanter Tank Height</h2>
                    <div class="panel-sub">
                        <span class="live-dot"></span>
                        Refreshes every <?= (int)$refreshSeconds ?> seconds using the dashboard refresh setting
                    </div>
                </div>
                <div class="panel-actions">
                    <a class="btn" href="index.php">Dashboard</a>
                </div>
            </div>

            <?php if ($success !== ''): ?>
                <div class="notice success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="notice error"><?= h($error) ?></div>
            <?php endif; ?>

            <div id="setupErrorNotice" class="notice error" <?= $setupError === '' ? 'hidden' : '' ?>><?= h($setupError) ?></div>

            <div class="height-layout">
                <div class="chart-card">
                    <div class="chart-title">Set Calculation Starting Point</div>

                    <form method="post" class="height-form">
                        <input type="hidden" name="action" value="save">

                        <div class="field-group">
                            <label for="start_height_mm">Starting tank height (mm)</label>
                            <input
                                type="number"
                                id="start_height_mm"
                                name="start_height_mm"
                                min="0"
                                step="0.01"
                                required
                                value="<?= $startHeight !== null ? h(number_format($startHeight, 2, '.', '')) : '' ?>"
                                <?= !$canEdit ? 'disabled' : '' ?>>
                        </div>

                        <div class="field-group">
                            <label for="target_height_mm">Lower target height (mm)</label>
                            <input
                                type="number"
                                id="target_height_mm"
                                name="target_height_mm"
                                min="0"
                                step="0.01"
                                required
                                value="<?= $targetHeight !== null ? h(number_format($targetHeight, 2, '.', '')) : '' ?>"
                                <?= !$canEdit ? 'disabled' : '' ?>>
                        </div>

                        <div class="field-group">
                            <label for="diameter_m">Tank diameter (m)</label>
                            <input
                                type="number"
                                id="diameter_m"
                                name="diameter_m"
                                min="0.001"
                                step="0.001"
                                required
                                value="<?= $diameter !== null ? h(number_format($diameter, 3, '.', '')) : '' ?>"
                                <?= !$canEdit ? 'disabled' : '' ?>>
                            <div class="height-note">Assumes a vertical cylindrical tank with a constant diameter.</div>
                        </div>

                        <div class="field-group">
                            <label for="start_project_flow_id">Tricanter project-flow starting point</label>
                            <select
                                id="start_project_flow_id"
                                name="start_project_flow_id"
                                required
                                <?= !$canEdit ? 'disabled' : '' ?>>
                                <option value="">Select a date and time</option>
                                <?php foreach ($flowRows as $row): ?>
                                    <option
                                        value="<?= (int)$row['id'] ?>"
                                        <?= ((int)($settings['start_project_flow_id'] ?? 0) === (int)$row['id']) ? 'selected' : '' ?>>
                                        <?= h($row['log_date']) ?> <?= h(substr($row['log_time'], 0, 8)) ?>
                                        — <?= h(number_format((float)$row['total_tricanter'], 4)) ?> m³
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if ($canEdit): ?>
                            <div class="action-row">
                                <button class="btn" type="submit">Save Selected Point</button>
                                <button
                                    class="btn"
                                    type="submit"
                                    name="action"
                                    value="use_latest">
                                    Use Latest as Start
                                </button>
                                <button
                                    class="btn btn-secondary"
                                    type="submit"
                                    name="action"
                                    value="clear"
                                    formnovalidate>
                                    Clear
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="height-note">Your account can view the live calculation but cannot change its saved setup.</div>
                        <?php endif; ?>
                    </form>

                </div>

                <div class="height-result">
                    <div class="height-primary">
                        <small>Calculated current height</small>
                        <strong id="currentHeight"><?= numberOrDash($currentHeight, 1) ?></strong>
                        <span>mm</span>
                    </div>

                    <div class="target-card">
                        <small>Estimated time to target height</small>
                        <strong id="hoursToTarget"><?= numberOrDash($hoursToTarget, 1) ?></strong>
                        <span>hours until <span id="targetHeightDisplay"><?= numberOrDash($targetHeight, 1) ?></span> mm</span>
                        <div class="height-note">Using latest tricanter flow rate: <b id="tricanterRateDisplay"><?= numberOrDash($tricanterRate, 2) ?> m³/hr</b></div>
                    </div>

                    <div class="height-kpis kpis">
                        <div class="kpi">
                            <small>Starting Height</small>
                            <b id="startHeightDisplay"><?= numberOrDash($startHeight, 1) ?> mm</b>
                        </div>
                        <div class="kpi">
                            <small>Starting Flow Total</small>
                            <b id="startFlowDisplay"><?= numberOrDash($startFlow, 4) ?> m³</b>
                        </div>
                        <div class="kpi">
                            <small>Current Flow Total</small>
                            <b id="currentFlowDisplay"><?= numberOrDash($currentFlow, 4) ?> m³</b>
                        </div>
                        <div class="kpi">
                            <small>Flow Difference</small>
                            <b id="flowUsedDisplay"><?= numberOrDash($flowUsed, 4) ?> m³</b>
                        </div>
                        <div class="kpi">
                            <small>Calculated Height Used</small>
                            <b id="heightUsedDisplay"><?= numberOrDash($heightUsed, 1) ?> mm</b>
                        </div>
                        <div class="kpi">
                            <small>Tank Diameter</small>
                            <b id="diameterDisplay"><?= numberOrDash($diameter, 3) ?> m</b>
                        </div>
                        <div class="kpi">
                            <small>Latest Reading</small>
                            <b id="latestReadingDisplay">
                                <?= $latest ? h($latest['log_date'] . ' ' . substr($latest['log_time'], 0, 8)) : '-' ?>
                            </b>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const refreshSeconds = <?= (int)$refreshSeconds ?>;

        function formatNumber(value, decimals) {
            if (value === null || value === undefined || Number.isNaN(Number(value))) {
                return '-';
            }

            return Number(value).toLocaleString(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        async function refreshTankHeight() {
            try {
                const response = await fetch('tank_height_data.php', {
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error('Refresh failed');
                }

                const data = await response.json();
                const setupErrorNotice = document.getElementById('setupErrorNotice');
                setupErrorNotice.textContent = data.setup_error || '';
                setupErrorNotice.hidden = !data.setup_error;

                document.getElementById('currentHeight').textContent =
                    formatNumber(data.current_height_mm, 1);
                document.getElementById('startHeightDisplay').textContent =
                    formatNumber(data.start_height_mm, 1) + ' mm';
                document.getElementById('startFlowDisplay').textContent =
                    formatNumber(data.start_flow_total, 4) + ' m³';
                document.getElementById('currentFlowDisplay').textContent =
                    formatNumber(data.current_flow_total, 4) + ' m³';
                document.getElementById('flowUsedDisplay').textContent =
                    formatNumber(data.flow_difference, 4) + ' m³';
                document.getElementById('heightUsedDisplay').textContent =
                    formatNumber(data.height_used_mm, 1) + ' mm';
                document.getElementById('diameterDisplay').textContent =
                    formatNumber(data.diameter_m, 3) + ' m';
                document.getElementById('latestReadingDisplay').textContent =
                    data.latest_reading || '-';
                document.getElementById('hoursToTarget').textContent =
                    formatNumber(data.hours_to_target, 1);
                document.getElementById('targetHeightDisplay').textContent =
                    formatNumber(data.target_height_mm, 1);
                document.getElementById('tricanterRateDisplay').textContent =
                    formatNumber(data.tricanter_flow_rate, 2) + ' m³/hr';
            } catch (error) {
                console.error(error);
            }
        }

        setInterval(refreshTankHeight, refreshSeconds * 1000);
    </script>
</body>

</html>
