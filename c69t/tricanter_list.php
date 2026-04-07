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
    <?php require_once "nav.php"; ?>
    <div class="container wide">
        <div class="topbar">
            <h2>Tricanter Logs</h2>
            <div>
                <?php if ($canEdit): ?>
                <a class="btn" href="tricanter_add.php">Add Record</a>
                <?php endif; ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
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
                    <td><?= h($row["uploaded_at"]) ?></td>
                    <td><?= h($row["log_date"]) ?></td>
                    <td><?= h($row["log_time"]) ?></td>
                    <td><?= fmt($row["bowl_speed"], 0) ?> %</td>
                    <td><?= fmt($row["screw_speed"], 2) ?> %</td>
                    <td><?= fmt($row["bowl_rpm"], 0) ?> RPM</td>
                    <td><?= fmt($row["screw_rpm"], 2) ?> RPM</td>
                    <td><?= fmt($row["impeller"], 0) ?></td>
                    <td><?= fmt($row["feed_rate"], 2) ?> M3/hr</td>
                    <td><?= fmt($row["torque"], 1) ?> %</td>
                    <td><?= fmt($row["temp"], 1) ?> °C</td>
                    <td><?= fmt($row["pressure"], 3) ?> BAR</td>
                    <td class="comments-cell" title="<?= h($row["comments"]) ?>"><?= h($row["comments"]) ?></td>
                    <?php if ($canEdit): ?>
                    <td>
                        <a class="btn small" href="tricanter_edit.php?id=<?= (int)$row["id"] ?>">Edit</a>
                        <a class="btn small danger" href="tricanter_delete.php?id=<?= (int)$row["id"] ?>"
                            onclick="return confirm('Delete this record?')">Delete</a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>

</html>