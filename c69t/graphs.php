<?php
require_once __DIR__ . "/config.php";
requireLogin();

if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$tables = [
    "tricanter_logs" => [
        "label" => "Tricanter",
        "columns" => [
            "bowl_speed" => "Bowl Speed",
            "screw_speed" => "Screw Speed",
            "bowl_rpm" => "Bowl RPM",
            "screw_rpm" => "Screw RPM",
            "impeller" => "Impeller",
            "feed_rate" => "Feed Rate",
            "torque" => "Torque",
            "temp" => "Temp",
            "pressure" => "Pressure",
        ],
    ],
    "nozzle_logs" => [
        "label" => "Nozzle",
        "columns" => [
            "flow" => "Flow",
            "pressure" => "Pressure",
            "min_deg" => "Min Deg",
            "max_deg" => "Max Deg",
            "rpm" => "RPM",
        ],
    ],
    "pump_values_logs" => [
        "label" => "Pump Values",
        "columns" => [
            "suction_pump_1_status" => "SP1 Status",
            "suction_pump_2_status" => "SP2 Status",
            "suction_pump_2_speed_out" => "SP2 Speed Out",
            "suction_pump_2_feedback" => "SP2 Feedback",
            "suction_pump_2_inlet_pressure" => "SP2 Inlet Pressure",
            "suction_pump_2_outlet_pressure" => "SP2 Outlet Pressure",
            "feed_pump_status" => "Feed Pump Status",
            "feed_pump_speed_out" => "Feed Pump Speed Out",
            "feed_pump_feedback" => "Feed Pump Feedback",
            "feed_pump_inlet_pressure" => "Feed Pump Inlet Pressure",
            "feed_pump_outlet_pressure" => "Feed Pump Outlet Pressure",
            "booster_pump_status" => "Booster Pump Status",
            "booster_pump_speed_out" => "Booster Pump Speed Out",
            "booster_pump_feedback" => "Booster Pump Feedback",
            "booster_pump_inlet_pressure" => "Booster Pump Inlet Pressure",
            "booster_pump_outlet_pressure" => "Booster Pump Outlet Pressure",
        ],
    ],
    "nitrogen_logs" => [
        "label" => "Nitrogen",
        "columns" => [
            "nitrogen_active" => "Nitrogen Active",
            "trip_status" => "Trip Status",
            "outlet_flow" => "Outlet Flow",
            "outlet_purity" => "Outlet Purity",
            "inlet_pressure" => "Inlet Pressure",
            "outlet_pressure" => "Outlet Pressure",
            "pre_heat_temp" => "Pre Heat Temp",
            "post_heat_temp" => "Post Heat Temp",
            "interior_o2" => "Interior O2",
        ],
    ],
];

$intervalOptions = [
    1 => "1 minute",
    5 => "5 minutes",
    15 => "15 minutes",
    30 => "30 minutes",
    60 => "1 hour",
];

$endDefault = date("Y-m-d\TH:i");
$startDefault = date("Y-m-d\TH:i", strtotime("-12 hours"));

$start = $_GET["start"] ?? $startDefault;
$end = $_GET["end"] ?? $endDefault;
$interval = (int)($_GET["interval"] ?? 15);
$selected = $_GET["series"] ?? [];

if (!isset($intervalOptions[$interval])) {
    $interval = 15;
}

if (!is_array($selected)) {
    $selected = [];
}

$startSql = str_replace("T", " ", $start) . ":00";
$endSql = str_replace("T", " ", $end) . ":00";
$bucketSeconds = $interval * 60;

$labels = [];
$seriesData = [];

foreach ($selected as $seriesKey) {
    if (!str_contains($seriesKey, ".")) {
        continue;
    }

    [$table, $column] = explode(".", $seriesKey, 2);

    if (!isset($tables[$table]["columns"][$column])) {
        continue;
    }

    $seriesLabel = $tables[$table]["label"] . " - " . $tables[$table]["columns"][$column];

    $sql = "
        SELECT
            FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(CONCAT(log_date, ' ', log_time)) / :bucket) * :bucket) AS bucket_time,
            AVG(CAST(`$column` AS DECIMAL(18,4))) AS avg_value
        FROM `$table`
        WHERE CONCAT(log_date, ' ', log_time) BETWEEN :start_dt AND :end_dt
          AND `$column` IS NOT NULL
          AND `$column` <> ''
        GROUP BY bucket_time
        ORDER BY bucket_time ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":bucket" => $bucketSeconds,
        ":start_dt" => $startSql,
        ":end_dt" => $endSql,
    ]);

    $data = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $timeLabel = date("d/m H:i", strtotime($row["bucket_time"]));
        $labels[$timeLabel] = true;
        $data[$timeLabel] = is_null($row["avg_value"]) ? null : round((float)$row["avg_value"], 3);
    }

    $seriesData[] = [
        "key" => $seriesKey,
        "label" => $seriesLabel,
        "data" => $data,
    ];
}

$finalLabels = array_keys($labels);

$datasets = [];
foreach ($seriesData as $i => $series) {
    $values = [];
    foreach ($finalLabels as $label) {
        $values[] = $series["data"][$label] ?? null;
    }

    $datasets[] = [
        "label" => $series["label"],
        "data" => $values,
        "borderWidth" => 2,
        "tension" => 0.25,
        "pointRadius" => 1.5,
        "spanGaps" => true,
        "yAxisID" => "y" . $i,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Custom Graphs</title>
    <link rel="stylesheet" href="indexStyle.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .graph-wrap {
            max-width: 1500px;
            margin: 20px auto;
            padding: 0 15px;
        }

        .filter-card, .chart-card {
            background: #111827;
            border: 1px solid #374151;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 18px;
            color: #fff;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }

        input, select {
            width: 100%;
            padding: 8px;
            border-radius: 8px;
            border: 1px solid #4b5563;
            background: #1f2937;
            color: #fff;
        }

        .series-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
        }

        .series-group {
            border: 1px solid #374151;
            border-radius: 10px;
            padding: 10px;
            background: #0f172a;
        }

        .series-group h3 {
            margin-top: 0;
            font-size: 16px;
        }

        .check-row {
            display: flex;
            gap: 8px;
            align-items: center;
            margin: 6px 0;
            font-weight: 400;
        }

        .check-row input {
            width: auto;
        }

        .btn-row {
            margin-top: 16px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        button, .button-link {
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .button-link.secondary {
            background: #374151;
        }

        canvas {
            min-height: 520px;
        }
    </style>
</head>
<body>

<?php if (file_exists(__DIR__ . "/nav.php")) require __DIR__ . "/nav.php"; ?>

<div class="graph-wrap">
    <h1>Custom Graphs</h1>

    <form method="get" class="filter-card">
        <div class="filter-grid">
            <div>
                <label for="start">Start Date / Time</label>
                <input type="datetime-local" name="start" id="start" value="<?= h($start) ?>">
            </div>

            <div>
                <label for="end">End Date / Time</label>
                <input type="datetime-local" name="end" id="end" value="<?= h($end) ?>">
            </div>

            <div>
                <label for="interval">Frequency</label>
                <select name="interval" id="interval">
                    <?php foreach ($intervalOptions as $mins => $label): ?>
                        <option value="<?= h($mins) ?>" <?= $interval === $mins ? "selected" : "" ?>>
                            <?= h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <h2>Select Values to Graph</h2>

        <div class="series-grid">
            <?php foreach ($tables as $tableName => $tableInfo): ?>
                <div class="series-group">
                    <h3><?= h($tableInfo["label"]) ?></h3>

                    <?php foreach ($tableInfo["columns"] as $columnName => $columnLabel): ?>
                        <?php $value = $tableName . "." . $columnName; ?>
                        <label class="check-row">
                            <input
                                type="checkbox"
                                name="series[]"
                                value="<?= h($value) ?>"
                                <?= in_array($value, $selected, true) ? "checked" : "" ?>
                            >
                            <?= h($columnLabel) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="btn-row">
            <button type="submit">Generate Graph</button>
            <a class="button-link secondary" href="graphs.php">Clear</a>
        </div>
    </form>

    <div class="chart-card">
        <?php if (empty($datasets)): ?>
            <p>Select at least one value to graph.</p>
        <?php elseif (empty($finalLabels)): ?>
            <p>No data found for this date/time range.</p>
        <?php else: ?>
            <canvas id="customGraph"></canvas>
        <?php endif; ?>
    </div>
</div>

<script>
const labels = <?= json_encode($finalLabels) ?>;
const datasets = <?= json_encode($datasets) ?>;

if (document.getElementById("customGraph")) {
    const scales = {};

    datasets.forEach((dataset, index) => {
        scales["y" + index] = {
            type: "linear",
            display: false,
            position: index % 2 === 0 ? "left" : "right",
            grid: {
                drawOnChartArea: index === 0
            }
        };
    });

    new Chart(document.getElementById("customGraph"), {
        type: "line",
        data: {
            labels,
            datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: "nearest",
                intersect: false
            },
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: "#fff"
                    }
                },
                tooltip: {
                    mode: "index",
                    intersect: false
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: "#d1d5db",
                        maxRotation: 45,
                        minRotation: 45
                    },
                    grid: {
                        color: "rgba(255,255,255,0.08)"
                    }
                },
                ...scales
            }
        }
    });
}
</script>

</body>
</html>