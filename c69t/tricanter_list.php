<?php
require_once "config.php";
requireLogin();

$range = get_range_filter_state();
$rows = fetch_log_rows($pdo, 'tricanter_logs', $range);
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
        <div class="topbar list-topbar">
            <h2>Tricanter Logs</h2>
            <div>
                <?php if ($canEdit): ?>
                    <a class="btn" href="tricanter_add.php">Add Record</a>
                <?php endif; ?>
<a class="btn" href="csv_download.php?<?= http_build_query([
    'table' => 'tricanter_logs',
    'from_date' => $fromDate,
    'from_time' => $fromTime,
    'to_date' => $toDate,
    'to_time' => $toTime
]) ?>">
    Download CSV
</a>
            </div>
        </div>

        <?php render_range_filter($range, 'Filtering tricanter table to selected range'); ?>

        <div class="table-wrap">
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
                        <?php if ($canEdit): ?>
                            <th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="<?= $canEdit ? 15 : 14; ?>">No records found in selected range.</td>
                        </tr>
                    <?php else: ?>
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
                                <td><?= h($row["comments"]) ?></td>
                                <?php if ($canEdit): ?>
                                    <td>
                                        <a class="btn small" href="tricanter_edit.php?id=<?= (int) $row["id"] ?>">Edit</a>
                                        <a class="btn small danger" href="tricanter_delete.php?id=<?= (int) $row["id"] ?>"
                                            onclick="return confirm('Delete this record?')">Delete</a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
