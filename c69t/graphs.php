<?php
require_once __DIR__ . "/config.php";
requireRole(['admin', 'operator', 'viewer']);

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
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

/*
|--------------------------------------------------------------------------
| BUILD ONE SHARED X-AXIS TIMELINE
|--------------------------------------------------------------------------
| Every selected series is placed against these exact interval buckets.
| This prevents data from different tables shifting left/right when one
| table has a missing record at a particular time.
*/
$startTimestamp = strtotime($startSql);
$endTimestamp = strtotime($endSql);

$timelineStart = (int)(floor($startTimestamp / $bucketSeconds) * $bucketSeconds);
$timelineEnd = (int)(floor($endTimestamp / $bucketSeconds) * $bucketSeconds);

$timelineKeys = [];
$finalLabels = [];

if ($startTimestamp !== false && $endTimestamp !== false && $timelineEnd >= $timelineStart) {
    for ($timestamp = $timelineStart; $timestamp <= $timelineEnd; $timestamp += $bucketSeconds) {
        $bucketKey = date("Y-m-d H:i:s", $timestamp);
        $timelineKeys[] = $bucketKey;
        $finalLabels[] = date("d/m H:i", $timestamp);
    }
}

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

    $dataByBucket = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $bucketTimestamp = strtotime((string)$row["bucket_time"]);

        if ($bucketTimestamp === false) {
            continue;
        }

        $bucketKey = date("Y-m-d H:i:s", $bucketTimestamp);
        $dataByBucket[$bucketKey] = is_null($row["avg_value"])
            ? null
            : round((float)$row["avg_value"], 3);
    }

    $seriesData[] = [
        "key" => $seriesKey,
        "label" => $seriesLabel,
        "data" => $dataByBucket,
    ];
}

$datasets = [];

foreach ($seriesData as $series) {
    $values = [];

    foreach ($timelineKeys as $bucketKey) {
        $values[] = array_key_exists($bucketKey, $series["data"])
            ? $series["data"][$bucketKey]
            : null;
    }

    $datasets[] = [
        "label" => $series["label"],
        "data" => $values,
    ];
}

$rangeSummary = date("d/m/Y H:i", strtotime($startSql)) . " to " . date("d/m/Y H:i", strtotime($endSql));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Graphs</title>
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

        .logo-row{
            margin:6px 0 14px;
        }

        .logo-row img{
            height:110px;
            filter:drop-shadow(0 10px 28px rgba(0,0,0,.25));
        }

        .panel,
        .info-card{
            background:linear-gradient(180deg, rgba(18,44,68,.94), rgba(14,34,53,.96));
            border:1px solid var(--line-soft);
            border-radius:var(--radius);
            box-shadow:var(--glow);
            backdrop-filter: blur(8px);
        }

        .topbar{
            grid-template-columns: 1.4fr .95fr;
            align-items:start;
        }

        .hero-card,
        .range-card{
            padding:16px;
        }

        .hero-status-row{
            display:flex;
            justify-content:space-between;
            gap:16px;
            align-items:flex-start;
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

        .section-kicker{
            font-size:11px;
            letter-spacing:1.1px;
            text-transform:uppercase;
            color:#8abce6;
            margin-bottom:4px;
        }

        .panel{
            padding:14px;
            margin-bottom:18px;
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

        .chart-card{
            border-radius:14px;
            padding:10px;
            border:1px solid rgba(255,255,255,.05);
            background:linear-gradient(180deg, rgba(8,26,40,.75), rgba(10,24,36,.94));
        }

        .chart-wrap{
            height:520px;
        }

        .filter-card{
            margin-bottom:18px;
        }

        .filter-grid{
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap:12px;
            margin-bottom:14px;
        }

        .filter-field label{
            display:block;
            font-size:11px;
            color:#b7ccdd;
            text-transform:uppercase;
            letter-spacing:.7px;
            margin-bottom:6px;
        }

        input[type="datetime-local"],
        select{
            width:100%;
            box-sizing:border-box;
            background:#0a1a29;
            border:1px solid #2a5377;
            border-radius:10px;
            color:#fff;
            padding:8px 10px;
            height:38px;
        }

        .series-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));
            gap:14px;
            align-items:start;
            margin-top:12px;
        }

        .series-group{
            background:linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.025));
            border:1px solid rgba(255,255,255,.08);
            border-radius:14px;
            padding:12px;
        }

        .series-group h3{
            margin:0 0 10px;
            font-size:16px;
        }

        .check-row{
            display:flex;
            align-items:center;
            gap:8px;
            margin:7px 0;
            font-size:12px;
            color:#dcecff;
            cursor:pointer;
        }

        .check-row input{
            width:auto;
        }

        .btn-row{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
            margin-top:16px;
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

        .btn-secondary{
            background:linear-gradient(180deg, #30465b, #243746);
        }

        .empty-state{
            color:#b7ccdd;
            padding:20px 6px;
            font-size:14px;
        }

        @media (max-width:1400px){
            .topbar{
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

            .chart-wrap{
                height:380px;
            }
        }

        @media (max-width:700px){
            .hero-stats{
                grid-template-columns:1fr;
            }

            .panel-head h2{
                font-size:20px;
            }

            .chart-wrap{
                height:320px;
            }
        }
    </style>
</head>
<body>
<?php if (file_exists(__DIR__ . "/nav.php")) require_once __DIR__ . "/nav.php"; ?>

<div class="dashboard-shell">
    <div class="logo-row">
        <img src="MoombaTankCleaningLogoTransparent.PNG" alt="Moomba Tank Cleaning">
        <img src="Contract69TanksLogoTransparent.png" alt="Contract 69 Tanks">
    </div>

    <h1>Custom Graphs</h1>

    <div class="topbar">
        <div class="info-card hero-card">
            <div class="info-title">Graph Builder</div>

            <div class="hero-status-row">
                <div>
                    <div class="hero-status status-online">CUSTOM TREND</div>
                    <div class="info-sub">Select any values from tricanter, nozzle, pump values, and nitrogen logs.</div>
                </div>

                <div class="hero-stats">
                    <div class="hero-stat">
                        <span>Range</span>
                        <b><?= h($rangeSummary) ?></b>
                    </div>
                    <div class="hero-stat">
                        <span>Frequency</span>
                        <b><?= h($intervalOptions[$interval]) ?></b>
                    </div>
                </div>
            </div>
        </div>

        <div class="info-card range-card">
            <div class="info-title">Selected Values</div>
            <div class="info-value"><?= count($datasets) ?></div>
            <div class="info-sub">Series loaded into the combined graph.</div>
        </div>
    </div>

    <form method="get" class="panel filter-card">
        <div class="panel-head">
            <div>
                <div class="section-kicker">filters</div>
                <h2>Date / Time Range</h2>
                <div class="panel-sub">Choose the same style of range and frequency filtering as the logs page.</div>
            </div>
        </div>

        <div class="filter-grid">
            <div class="filter-field">
                <label for="start">Start</label>
                <input type="datetime-local" name="start" id="start" value="<?= h($start) ?>">
            </div>

            <div class="filter-field">
                <label for="end">End</label>
                <input type="datetime-local" name="end" id="end" value="<?= h($end) ?>">
            </div>

            <div class="filter-field">
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

        <div class="panel-head">
            <div>
                <div class="section-kicker">series</div>
                <h2>Select Values</h2>
                <div class="panel-sub">Each selected value is normalised like the dashboard charts, with raw readings shown on hover.</div>
            </div>
        </div>

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
            <button class="btn" type="submit">Generate Graph</button>
            <a class="btn btn-secondary" href="graphs.php">Clear</a>
        </div>
    </form>

    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="section-kicker">combined trend</div>
                <h2>Selected Values Graph</h2>
                <div class="panel-sub">Same chart format as the index dashboard: hidden axes, normalised trends, raw tooltip values.</div>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-title">Custom Trends</div>

            <?php if (empty($datasets)): ?>
                <div class="empty-state">Select at least one value to graph.</div>
            <?php elseif (empty($finalLabels)): ?>
                <div class="empty-state">No data found for this date/time range.</div>
            <?php else: ?>
                <div class="chart-wrap">
                    <canvas id="customCombinedChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const chartPalette = {
    'Tricanter - Bowl Speed': '#00e5ff',
    'Tricanter - Screw Speed': '#ffd24d',
    'Tricanter - Bowl RPM': '#c8a7ff',
    'Tricanter - Screw RPM': '#ff9bd6',
    'Tricanter - Impeller': '#b6ff7a',
    'Tricanter - Feed Rate': '#00ff88',
    'Tricanter - Torque': '#ff7e67',
    'Tricanter - Temp': '#ffb36b',
    'Tricanter - Pressure': '#7dd3fc',

    'Nozzle - Flow': '#00e5ff',
    'Nozzle - Pressure': '#ffd24d',
    'Nozzle - Min Deg': '#6ee7a1',
    'Nozzle - Max Deg': '#c8a7ff',
    'Nozzle - RPM': '#ff7e67',

    'Pump Values - SP2 Inlet Pressure': '#00e5ff',
    'Pump Values - SP2 Outlet Pressure': '#7dd3fc',
    'Pump Values - Feed Pump Inlet Pressure': '#ffd24d',
    'Pump Values - Feed Pump Outlet Pressure': '#f59e0b',
    'Pump Values - Booster Pump Inlet Pressure': '#6ee7a1',
    'Pump Values - Booster Pump Outlet Pressure': '#22c55e',

    'Nitrogen - Outlet Flow': '#00e5ff',
    'Nitrogen - Outlet Purity': '#ffd24d',
    'Nitrogen - Inlet Pressure': '#7dd3fc',
    'Nitrogen - Outlet Pressure': '#f59e0b',
    'Nitrogen - Pre Heat Temp': '#ffb36b',
    'Nitrogen - Post Heat Temp': '#ff7e67',
    'Nitrogen - Interior O2': '#c8a7ff'
};

const chartLabels = <?= json_encode($finalLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const rawDatasets = <?= json_encode($datasets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function validDatasets(datasets) {
    return (datasets || []).filter(ds => Array.isArray(ds.data) && ds.data.length > 0);
}

function normaliseSeries(data) {
    const numeric = data
        .filter(v => v !== null && v !== '' && !Number.isNaN(Number(v)))
        .map(Number);

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
        spanGaps: false
    };
}

function makeChart(canvasId, labels, datasets) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    const usable = validDatasets(datasets || []);
    if (!usable.length) return null;

    return new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels || [],
            datasets: usable.map(chartDatasetObject)
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: {
                mode: 'index',
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

makeChart('customCombinedChart', chartLabels, rawDatasets);
</script>
</body>
</html>