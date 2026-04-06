<?php
require_once "config.php";
requireLogin();

$stmt = $pdo->query("SELECT * FROM tricanter_logs ORDER BY log_date DESC, log_time DESC, id DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$canEdit = in_array(currentRole(), ["admin", "operator"], true);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tricanter Logs</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container wide">
    <div class="topbar">
        <h2>Tricanter Logs</h2>
        <div>
            <?php if ($canEdit): ?>
                <a class="btn" href="tricanter_add.php">Add Record</a>
            <?php endif; ?>
            <a class="btn" href="dashboard.php">Dashboard</a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Source File</th>
                <th>Uploaded At</th>
                <th>Date</th>
                <th>Time</th>
                <th>Bowl Speed</th>
                <th>Screw Speed</th>
                <th>Bowl RPM</th>
                <th>Screw RPM</th>
                <th>Impeller</th>
                <th>Feed Rate</th>
                <th>Torque</th>
                <th>Temp</th>
                <th>Pressure</th>
                <th>Comments</th>
                <?php if ($canEdit): ?><th>Actions</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= h($row["id"]) ?></td>
                <td><?= h($row["source_file"]) ?></td>
                <td><?= h($row["uploaded_at"]) ?></td>
                <td><?= h($row["log_date"]) ?></td>
                <td><?= h($row["log_time"]) ?></td>
                <td><?= h($row["bowl_speed"]) ?></td>
                <td><?= h($row["screw_speed"]) ?></td>
                <td><?= h($row["bowl_rpm"]) ?></td>
                <td><?= h($row["screw_rpm"]) ?></td>
                <td><?= h($row["impeller"]) ?></td>
                <td><?= h($row["feed_rate"]) ?></td>
                <td><?= h($row["torque"]) ?></td>
                <td><?= h($row["temp"]) ?></td>
                <td><?= h($row["pressure"]) ?></td>
                <td><?= h($row["comments"]) ?></td>
                <?php if ($canEdit): ?>
                    <td>
                        <a class="btn small" href="tricanter_edit.php?id=<?= (int)$row["id"] ?>">Edit</a>
                        <a class="btn small danger" href="tricanter_delete.php?id=<?= (int)$row["id"] ?>" onclick="return confirm('Delete this record?')">Delete</a>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>