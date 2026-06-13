<?php
require_once "config.php";
requireRole(["admin"]);

/*
|--------------------------------------------------------------------------
| CONFIG SECTION
|--------------------------------------------------------------------------
| Set these to match your Project Flow database table.
|
| This page assumes your project flow value is a CUMULATIVE total, e.g.
| total m3, total litres, total kL, etc.
|
| If your value is only an instant flow rate, this page will need different
| logic to integrate flow over time.
*/

$PROJECT_FLOW_TABLE = "project_flow_logs";
$PROJECT_FLOW_VALUE_COLUMN = "total_tricanter";
$PROJECT_FLOW_DATE_COLUMN = "log_date";
$PROJECT_FLOW_TIME_COLUMN = "log_time";

// Tank capacity in the same unit as your project flow total.
// Example: if project flow is m3, tank capacity must be m3.
$tankCapacities = [
    1 => 60.0,
    2 => 60.0,
    3 => 60.0,
    4 => 60.0,
];

// Viewer can see only. Admin can edit.
$canEdit = in_array(currentRole(), ["admin"], true);

/*
|--------------------------------------------------------------------------
| TABLE SETUP
|--------------------------------------------------------------------------
*/

$pdo->exec("
CREATE TABLE IF NOT EXISTS project_tank_levels (
    tank_no INT PRIMARY KEY,
    tank_name VARCHAR(50) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    start_level DECIMAL(10,3) NOT NULL DEFAULT 0,
    manual_level DECIMAL(10,3) DEFAULT NULL,
    start_flow_value DECIMAL(14,4) DEFAULT NULL,
    start_datetime DATETIME DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

for ($i = 1; $i <= 4; $i++) {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO project_tank_levels
        (tank_no, tank_name, is_active, start_level, manual_level)
        VALUES (?, ?, ?, 0, NULL)
    ");
    $stmt->execute([$i, "Tank " . $i, $i === 1 ? 1 : 0]);
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function latest_project_flow($pdo, $table, $valueCol, $dateCol, $timeCol) {
    $sql = "
        SELECT 
            `$valueCol` AS flow_value,
            TIMESTAMP(`$dateCol`, `$timeCol`) AS flow_datetime
        FROM `$table`
        WHERE `$valueCol` IS NOT NULL
        ORDER BY `$dateCol` DESC, `$timeCol` DESC, id DESC
        LIMIT 1
    ";
    return $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
}


$latestFlow = latest_project_flow(
    $pdo,
    $PROJECT_FLOW_TABLE,
    $PROJECT_FLOW_VALUE_COLUMN,
    $PROJECT_FLOW_DATE_COLUMN,
    $PROJECT_FLOW_TIME_COLUMN
);

/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && $canEdit) {
    $action = $_POST["action"] ?? "";
    $tankNo = isset($_POST["tank_no"]) ? (int)$_POST["tank_no"] : 0;

    if ($tankNo >= 1 && $tankNo <= 4) {

        if ($action === "set_active") {
            $pdo->beginTransaction();

            $pdo->exec("UPDATE project_tank_levels SET is_active = 0");

            $stmt = $pdo->prepare("
                UPDATE project_tank_levels
                SET is_active = 1
                WHERE tank_no = ?
            ");
            $stmt->execute([$tankNo]);

            $pdo->commit();
        }

        if ($action === "set_start") {
            $startLevel = (float)($_POST["start_level"] ?? 0);

            $stmt = $pdo->prepare("
                UPDATE project_tank_levels
                SET 
                    start_level = ?,
                    manual_level = NULL,
                    start_flow_value = ?,
                    start_datetime = NOW()
                WHERE tank_no = ?
            ");
            $stmt->execute([
                $startLevel,
                $latestFlow ? $latestFlow["flow_value"] : null,
                $tankNo
            ]);
        }

        if ($action === "set_manual") {
            $manualLevel = (float)($_POST["manual_level"] ?? 0);

            $stmt = $pdo->prepare("
                UPDATE project_tank_levels
                SET manual_level = ?
                WHERE tank_no = ?
            ");
            $stmt->execute([$manualLevel, $tankNo]);
        }

        if ($action === "reset") {
            $stmt = $pdo->prepare("
                UPDATE project_tank_levels
                SET 
                    start_level = 0,
                    manual_level = NULL,
                    start_flow_value = ?,
                    start_datetime = NOW()
                WHERE tank_no = ?
            ");
            $stmt->execute([
                $latestFlow ? $latestFlow["flow_value"] : null,
                $tankNo
            ]);
        }
    }

    header("Location: fracCalc.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD TANKS
|--------------------------------------------------------------------------
*/

$tanks = $pdo->query("
    SELECT *
    FROM project_tank_levels
    ORDER BY tank_no ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Project Tank Levels</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .tank-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .tank-card {
            border: 1px solid #333;
            border-radius: 14px;
            padding: 16px;
            background: #111827;
            color: #fff;
        }

        .tank-card.active {
            border: 2px solid #22c55e;
            box-shadow: 0 0 15px rgba(34, 197, 94, 0.35);
        }

        .tank-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .tank-level {
            font-size: 34px;
            font-weight: bold;
            margin: 12px 0;
        }

        .tank-meta {
            font-size: 13px;
            opacity: 0.8;
            line-height: 1.5;
        }

        .tank-form {
            margin-top: 12px;
        }

        .tank-form input {
            width: 100%;
            margin-bottom: 8px;
        }

        .tank-form button {
            width: 100%;
            margin-bottom: 6px;
        }

        .active-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #22c55e;
            color: #000;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
        }

        .info-box {
            background: #0f172a;
            color: #fff;
            border-radius: 12px;
            padding: 14px;
            margin-top: 16px;
        }
    </style>
</head>

<body>
<?php require_once "nav.php"; ?>

<div class="container wide">
    <h1>Project Tank Levels</h1>

    <div class="info-box">
        <strong>Latest Project Flow:</strong>
        <?php if ($latestFlow): ?>
            <?= h($latestFlow["flow_value"]) ?>
            at <?= h($latestFlow["flow_datetime"]) ?>
        <?php else: ?>
            No project flow value found.
        <?php endif; ?>
    </div>

    <div class="tank-grid">
        <?php foreach ($tanks as $tank): ?>
            <?php
                $tankNo = (int)$tank["tank_no"];
                $capacity = $tankCapacities[$tankNo] ?? 100.0;

                $startLevel = (float)$tank["start_level"];
                $manualLevel = $tank["manual_level"] !== null ? (float)$tank["manual_level"] : null;
                $startFlow = $tank["start_flow_value"] !== null ? (float)$tank["start_flow_value"] : null;
                $currentFlow = $latestFlow ? (float)$latestFlow["flow_value"] : null;

                $flowDelta = null;
                $estimatedGain = 0;
                $estimatedLevel = $startLevel;

                if ($manualLevel !== null) {
                    $estimatedLevel = $manualLevel;
                } elseif ($currentFlow !== null && $startFlow !== null && $capacity > 0) {
                    $flowDelta = $currentFlow - $startFlow;
                    if ($flowDelta < 0) {
                        $flowDelta = 0;
                    }

                    $estimatedGain = ($flowDelta / $capacity) * 100;
                    $estimatedLevel = $startLevel + $estimatedGain;
                }

                if ($estimatedLevel > 100) {
                    $estimatedLevel = 100;
                }

                if ($estimatedLevel < 0) {
                    $estimatedLevel = 0;
                }
            ?>

            <div class="tank-card <?= (int)$tank["is_active"] === 1 ? "active" : "" ?>">
                <div class="tank-title">
                    <?= h($tank["tank_name"]) ?>
                    <?php if ((int)$tank["is_active"] === 1): ?>
                        <span class="active-badge">ACTIVE</span>
                    <?php endif; ?>
                </div>

                <div class="tank-level">
                    <?= number_format($estimatedLevel, 1) ?>m3
                </div>

                <div class="tank-meta">
                    Start level: <?= number_format($startLevel, 1) ?>m3<br>
                    Estimated gain: <?= number_format($estimatedGain, 1) ?>m3<br>
                    Start flow: <?= $startFlow !== null ? number_format($startFlow, 3) : "Not set" ?><br>
                    Flow used: <?= $flowDelta !== null ? number_format($flowDelta, 3) : "0.000" ?><br>
                    Capacity: <?= number_format($capacity, 3) ?><br>
                    Started: <?= $tank["start_datetime"] ? h($tank["start_datetime"]) : "Not set" ?><br>
                    Mode: <?= $manualLevel !== null ? "Manual level" : "Estimated" ?>
                </div>

                <?php if ($canEdit): ?>
                    <form class="tank-form" method="post">
                        <input type="hidden" name="tank_no" value="<?= $tankNo ?>">
                        <input type="hidden" name="action" value="set_active">
                        <button type="submit">Set Active Tank</button>
                    </form>

                    <form class="tank-form" method="post">
                        <input type="hidden" name="tank_no" value="<?= $tankNo ?>">
                        <input type="hidden" name="action" value="set_start">
                        <input type="number" step="0.1" name="start_level" placeholder="Start level m3" required>
                        <button type="submit">Set Start Level</button>
                    </form>

                    <form class="tank-form" method="post">
                        <input type="hidden" name="tank_no" value="<?= $tankNo ?>">
                        <input type="hidden" name="action" value="set_manual">
                        <input type="number" step="0.1" name="manual_level" placeholder="Manual level m3" required>
                        <button type="submit">Set Manual Level</button>
                    </form>

                    <form class="tank-form" method="post">
                        <input type="hidden" name="tank_no" value="<?= $tankNo ?>">
                        <input type="hidden" name="action" value="reset">
                        <button type="submit" onclick="return confirm('Reset this tank level?')">Reset Level</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>