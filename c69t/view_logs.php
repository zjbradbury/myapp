<?php
$host = "mariadb";
$dbname = "myapp";
$user = "zack";
$pass = "Butcher69";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $nozzle = $pdo->query("
        SELECT * FROM nozzle_logs
        ORDER BY id DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);

    $tricanter = $pdo->query("
        SELECT * FROM tricanter_logs
        ORDER BY id DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    die("DB Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HMI Live Logs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #0b1e2d;
            color: #ffffff;
            margin: 0;
            padding: 20px;
        }

        h1 {
            text-align: center;
            margin-top: 0;
            margin-bottom: 24px;
        }

        h2 {
            margin: 0 0 12px 0;
            color: #9fd3ff;
        }

        .section {
            margin-bottom: 32px;
        }

        .feed-wrap {
            border: 1px solid #1f4a6e;
            background: #122c44;
            overflow: hidden;
            border-radius: 8px;
        }

        .feed-scroll {
            max-height: 420px;
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #122c44;
        }

        th, td {
            padding: 8px;
            border: 1px solid #1f4a6e;
            font-size: 12px;
            white-space: nowrap;
        }

        th {
            position: sticky;
            top: 0;
            background: #1f4a6e;
            z-index: 2;
        }

        tr:nth-child(even) {
            background: #163a59;
        }

        .time {
            color: #00ffcc;
        }

        .flash-new {
            animation: flashRow 2.2s ease-in-out 0s 4;
        }

        @keyframes flashRow {
            0%   { background-color: #ffe066; color: #000; }
            50%  { background-color: #2a5f87; color: #fff; }
            100% { background-color: inherit; color: inherit; }
        }

        .meta {
            font-size: 12px;
            color: #9fb7c8;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>

<h1>HMI Live Logs</h1>

<div class="section">
    <h2>NOZZLE</h2>
    <div class="meta">Newest entries at top</div>
    <div class="feed-wrap">
        <div class="feed-scroll" id="nozzleFeed">
            <table>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Nozzle</th>
                    <th>Flow</th>
                    <th>Pressure</th>
                    <th>Min Deg</th>
                    <th>Max Deg</th>
                    <th>RPM</th>
                    <th>Comments</th>
                </tr>

                <?php foreach ($nozzle as $row): ?>
                <tr class="nozzle-row" data-id="<?= (int)$row['id'] ?>">
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['log_date']) ?></td>
                    <td class="time"><?= htmlspecialchars($row['log_time']) ?></td>
                    <td><?= htmlspecialchars($row['nozzle']) ?></td>
                    <td><?= htmlspecialchars($row['flow']) ?></td>
                    <td><?= htmlspecialchars($row['pressure']) ?></td>
                    <td><?= htmlspecialchars($row['min_deg']) ?></td>
                    <td><?= htmlspecialchars($row['max_deg']) ?></td>
                    <td><?= htmlspecialchars($row['rpm']) ?></td>
                    <td><?= htmlspecialchars($row['comments']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<div class="section">
    <h2>TRICANTER</h2>
    <div class="meta">Newest entries at top</div>
    <div class="feed-wrap">
        <div class="feed-scroll" id="tricanterFeed">
            <table>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Bowl Speed</th>
                    <th>Screw Speed</th>
                    <th>Bowl RPM</th>
                    <th>Screw RPM</th>
                    <th>Feed Rate</th>
                    <th>Torque</th>
                    <th>Temp</th>
                    <th>Pressure</th>
                    <th>Comments</th>
                </tr>

                <?php foreach ($tricanter as $row): ?>
                <tr class="tricanter-row" data-id="<?= (int)$row['id'] ?>">
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['log_date']) ?></td>
                    <td class="time"><?= htmlspecialchars($row['log_time']) ?></td>
                    <td><?= htmlspecialchars($row['bowl_speed']) ?></td>
                    <td><?= htmlspecialchars($row['screw_speed']) ?></td>
                    <td><?= htmlspecialchars($row['bowl_rpm']) ?></td>
                    <td><?= htmlspecialchars($row['screw_rpm']) ?></td>
                    <td><?= htmlspecialchars($row['feed_rate']) ?></td>
                    <td><?= htmlspecialchars($row['torque']) ?></td>
                    <td><?= htmlspecialchars($row['temp']) ?></td>
                    <td><?= htmlspecialchars($row['pressure']) ?></td>
                    <td><?= htmlspecialchars($row['comments']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<script>
function setupFeed(rowSelector, storageKey, feedId) {
    const rows = document.querySelectorAll(rowSelector);
    const feed = document.getElementById(feedId);

    let lastSeenId = parseInt(localStorage.getItem(storageKey) || "0", 10);
    let maxIdOnPage = lastSeenId;
    let firstNewRow = null;

    rows.forEach((row) => {
        const rowId = parseInt(row.dataset.id || "0", 10);

        if (rowId > lastSeenId) {
            row.classList.add("flash-new");
            if (!firstNewRow) {
                firstNewRow = row;
            }
        }

        if (rowId > maxIdOnPage) {
            maxIdOnPage = rowId;
        }
    });

    if (firstNewRow && feed) {
        firstNewRow.scrollIntoView({
            behavior: "smooth",
            block: "nearest"
        });
    } else if (feed) {
        feed.scrollTop = 0;
    }

    localStorage.setItem(storageKey, String(maxIdOnPage));
}

setupFeed(".nozzle-row", "lastSeenNozzleId", "nozzleFeed");
setupFeed(".tricanter-row", "lastSeenTricanterId", "tricanterFeed");
</script>

</body>
</html>