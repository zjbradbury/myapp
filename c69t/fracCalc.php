<?php
require_once "config.php";
requireRole(["admin"]);

$PROJECT_FLOW_TABLE = "project_flow_logs";
$PROJECT_FLOW_VALUE_COLUMN = "total_tricanter";
$PROJECT_FLOW_DATE_COLUMN = "log_date";
$PROJECT_FLOW_TIME_COLUMN = "log_time";

$tankCapacities = [
    1 => 60.0,
    2 => 60.0,
    3 => 60.0,
    4 => 60.0,
];

$canEdit = in_array(currentRole(), ["admin"], true);

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
        VALUES (?, ?, 0, 0, NULL)
    ");
    $stmt->execute([$i, "Tank " . $i]);
}

function latest_project_flow($pdo, $table, $valueCol, $dateCol, $timeCol)
{
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

function calculate_tank_level($tank, $latestFlow, $capacity)
{
    $startLevel = (float)$tank["start_level"];
    $isActive = (int)$tank["is_active"] === 1;

    $flowDelta = 0;
    $estimatedGain = 0;
    $estimatedLevel = $startLevel;

    if (!$isActive) {
        return [
            "level" => $estimatedLevel,
            "gain" => 0,
            "flow_delta" => 0
        ];
    }

    $startFlow = $tank["start_flow_value"] !== null ? (float)$tank["start_flow_value"] : null;
    $currentFlow = $latestFlow ? (float)$latestFlow["flow_value"] : null;

    if ($currentFlow !== null && $startFlow !== null && $capacity > 0) {
        $flowDelta = $currentFlow - $startFlow;

        if ($flowDelta < 0) {
            $flowDelta = 0;
        }

        $estimatedGain = $flowDelta;
        $estimatedLevel = $startLevel + $estimatedGain;
    }

    if ($estimatedLevel > $capacity) {
        $estimatedLevel = $capacity;
    }

    if ($estimatedLevel < 0) {
        $estimatedLevel = 0;
    }

    return [
        "level" => $estimatedLevel,
        "gain" => $estimatedGain,
        "flow_delta" => $flowDelta
    ];
}

$latestFlow = latest_project_flow(
    $pdo,
    $PROJECT_FLOW_TABLE,
    $PROJECT_FLOW_VALUE_COLUMN,
    $PROJECT_FLOW_DATE_COLUMN,
    $PROJECT_FLOW_TIME_COLUMN
);

if ($_SERVER["REQUEST_METHOD"] === "POST" && $canEdit) {
    $action = $_POST["action"] ?? "";
    $tankNo = isset($_POST["tank_no"]) ? (int)$_POST["tank_no"] : 0;

    if ($action === "deactivate_all") {
        $tanksToFreeze = $pdo->query("
            SELECT *
            FROM project_tank_levels
            WHERE is_active = 1
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tanksToFreeze as $tankToFreeze) {
            $freezeTankNo = (int)$tankToFreeze["tank_no"];
            $capacity = $tankCapacities[$freezeTankNo] ?? 60.0;
            $calc = calculate_tank_level($tankToFreeze, $latestFlow, $capacity);
            $lastLevel = $calc["level"];

            $stmt = $pdo->prepare("
                UPDATE project_tank_levels
                SET
                    is_active = 0,
                    start_level = ?,
                    manual_level = NULL,
                    start_flow_value = ?,
                    start_datetime = ?
                WHERE tank_no = ?
            ");

            $stmt->execute([
                $lastLevel,
                $latestFlow ? $latestFlow["flow_value"] : null,
                date('Y-m-d H:i:s'),
                $freezeTankNo
            ]);
        }

        header("Location: fracCalc.php");
        exit;
    }

    if ($tankNo >= 1 && $tankNo <= 4) {
        if ($action === "set_active") {
            $stmt = $pdo->prepare("SELECT * FROM project_tank_levels WHERE tank_no = ?");
            $stmt->execute([$tankNo]);
            $tank = $stmt->fetch(PDO::FETCH_ASSOC);

            $capacity = $tankCapacities[$tankNo] ?? 60.0;
            $calc = calculate_tank_level($tank, $latestFlow, $capacity);
            $lastLevel = $calc["level"];

            $pdo->beginTransaction();

            $activeTanks = $pdo->query("
                SELECT *
                FROM project_tank_levels
                WHERE is_active = 1
            ")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($activeTanks as $activeTank) {
                $activeTankNo = (int)$activeTank["tank_no"];
                $activeCapacity = $tankCapacities[$activeTankNo] ?? 60.0;
                $activeCalc = calculate_tank_level($activeTank, $latestFlow, $activeCapacity);
                $activeLastLevel = $activeCalc["level"];

                $freezeStmt = $pdo->prepare("
                    UPDATE project_tank_levels
                    SET
                        is_active = 0,
                        start_level = ?,
                        manual_level = NULL,
                        start_flow_value = ?,
                        start_datetime = ?
                    WHERE tank_no = ?
                ");

                $freezeStmt->execute([
                    $activeLastLevel,
                    $latestFlow ? $latestFlow["flow_value"] : null,
                    date('Y-m-d H:i:s'),
                    $activeTankNo
                ]);
            }

            $stmt = $pdo->prepare("
                UPDATE project_tank_levels
                SET
                    is_active = 1,
                    start_level = ?,
                    manual_level = NULL,
                    start_flow_value = ?,
                    start_datetime = ?
                WHERE tank_no = ?
            ");

            $stmt->execute([
                $lastLevel,
                $latestFlow ? $latestFlow["flow_value"] : null,
                date('Y-m-d H:i:s'),
                $tankNo
            ]);

            $pdo->commit();
        }

        if ($action === "set_inactive") {
            $stmt = $pdo->prepare("SELECT * FROM project_tank_levels WHERE tank_no = ?");
            $stmt->execute([$tankNo]);
            $tank = $stmt->fetch(PDO::FETCH_ASSOC);

            $capacity = $tankCapacities[$tankNo] ?? 60.0;
            $calc = calculate_tank_level($tank, $latestFlow, $capacity);
            $lastLevel = $calc["level"];

            $stmt = $pdo->prepare("
                UPDATE project_tank_levels
                SET
                    is_active = 0,
                    start_level = ?,
                    manual_level = NULL,
                    start_flow_value = ?,
                    start_datetime = ?
                WHERE tank_no = ?
            ");

            $stmt->execute([
                $lastLevel,
                $latestFlow ? $latestFlow["flow_value"] : null,
                date('Y-m-d H:i:s'),
                $tankNo
            ]);
        }

        if ($action === "set_level") {
            $startLevel = (float)($_POST["start_level"] ?? 0);

            if ($startLevel < 0) {
                $startLevel = 0;
            }

            $capacity = $tankCapacities[$tankNo] ?? 60.0;

            if ($startLevel > $capacity) {
                $startLevel = $capacity;
            }

            $stmt = $pdo->prepare("
                UPDATE project_tank_levels
                SET
                    start_level = ?,
                    manual_level = NULL,
                    start_flow_value = ?,
                    start_datetime = ?
                WHERE tank_no = ?
            ");

            $stmt->execute([
                $startLevel,
                $latestFlow ? $latestFlow["flow_value"] : null,
                date('Y-m-d H:i:s'),
                $tankNo
            ]);
        }

        if ($action === "reset") {
            $stmt = $pdo->prepare("
                UPDATE project_tank_levels
                SET
                    start_level = 0,
                    manual_level = NULL,
                    start_flow_value = ?,
                    start_datetime = ?
                WHERE tank_no = ?
            ");

            $stmt->execute([
                $latestFlow ? $latestFlow["flow_value"] : null,
                date('Y-m-d H:i:s'),
                $tankNo
            ]);
        }
    }

    header("Location: fracCalc.php");
    exit;
}

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

        <?php if ($canEdit): ?>
            <form method="post" style="margin-top:12px;">
                <input type="hidden" name="action" value="deactivate_all">
                <button type="submit">Clear Active Tank</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="tank-grid">
        <?php foreach ($tanks as $tank): ?>
            <?php
                $tankNo = (int)$tank["tank_no"];
                $capacity = $tankCapacities[$tankNo] ?? 60.0;

                $startLevel = (float)$tank["start_level"];
                $startFlow = $tank["start_flow_value"] !== null ? (float)$tank["start_flow_value"] : null;

                $calc = calculate_tank_level($tank, $latestFlow, $capacity);
                $estimatedLevel = $calc["level"];
                $estimatedGain = $calc["gain"];
                $flowDelta = $calc["flow_delta"];
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
                    Saved level: <?= number_format($startLevel, 1) ?>m3<br>
                    Estimated gain: <?= number_format($estimatedGain, 1) ?>m3<br>
                    Start flow: <?= $startFlow !== null ? number_format($startFlow, 3) : "Not set" ?><br>
                    Flow used: <?= number_format($flowDelta, 3) ?><br>
                    Capacity: <?= number_format($capacity, 3) ?>m3<br>
                    Started: <?= $tank["start_datetime"] ? h($tank["start_datetime"]) : "Not set" ?><br>
                    Status: <?= (int)$tank["is_active"] === 1 ? "Active / counting" : "Inactive / held" ?>
                </div>

                <?php if ($canEdit): ?>
                    <?php if ((int)$tank["is_active"] === 1): ?>
                        <form class="tank-form" method="post">
                            <input type="hidden" name="tank_no" value="<?= $tankNo ?>">
                            <input type="hidden" name="action" value="set_inactive">
                            <button type="submit">Set Inactive</button>
                        </form>
                    <?php else: ?>
                        <form class="tank-form" method="post">
                            <input type="hidden" name="tank_no" value="<?= $tankNo ?>">
                            <input type="hidden" name="action" value="set_active">
                            <button type="submit">Set Active Tank</button>
                        </form>
                    <?php endif; ?>

                    <form class="tank-form" method="post">
                        <input type="hidden" name="tank_no" value="<?= $tankNo ?>">
                        <input type="hidden" name="action" value="set_level">
                        <input type="number" step="0.1" name="start_level" placeholder="Set level m3" required>
                        <button type="submit">Set Level</button>
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