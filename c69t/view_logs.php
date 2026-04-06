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

    // Get latest records
    $nozzle = $pdo->query("
        SELECT * FROM nozzle_logs
        ORDER BY uploaded_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);

    $tricanter = $pdo->query("
        SELECT * FROM tricanter_logs
        ORDER BY uploaded_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    die("DB Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>HMI Logs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <style>
        body {
            font-family: Arial;
            background: #0b1e2d;
            color: #fff;
            margin: 0;
            padding: 20px;
        }

        h1 {
            text-align: center;
        }

        .section {
            margin-bottom: 40px;
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
        }

        th {
            background: #1f4a6e;
        }

        tr:nth-child(even) {
            background: #163a59;
        }

        .time {
            color: #00ffcc;
        }
    </style>

    <!-- Auto refresh every 30 sec -->
    <meta http-equiv="refresh" content="30">
</head>
<body>

<h1>HMI Live Logs</h1>

<div class="section">
    <h2>NOZZLE</h2>
    <table>
        <tr>
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
        <tr>
            <td><?= $row['log_date'] ?></td>
            <td class="time"><?= $row['log_time'] ?></td>
            <td><?= $row['nozzle'] ?></td>
            <td><?= $row['flow'] ?></td>
            <td><?= $row['pressure'] ?></td>
            <td><?= $row['min_deg'] ?></td>
            <td><?= $row['max_deg'] ?></td>
            <td><?= $row['rpm'] ?></td>
            <td><?= $row['comments'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="section">
    <h2>TRICANTER</h2>
    <table>
        <tr>
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
        <tr>
            <td><?= $row['log_date'] ?></td>
            <td class="time"><?= $row['log_time'] ?></td>
            <td><?= $row['bowl_speed'] ?></td>
            <td><?= $row['screw_speed'] ?></td>
            <td><?= $row['bowl_rpm'] ?></td>
            <td><?= $row['screw_rpm'] ?></td>
            <td><?= $row['feed_rate'] ?></td>
            <td><?= $row['torque'] ?></td>
            <td><?= $row['temp'] ?></td>
            <td><?= $row['pressure'] ?></td>
            <td><?= $row['comments'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

</body>
</html>