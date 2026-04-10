<?php
require_once "config.php";
requireLogin();

$range = get_range_filter_state();
$rows = fetch_log_rows($pdo, 'nozzle_logs', $range);
$canEdit = in_array(currentRole(), ["admin", "operator"], true);
?>
<!DOCTYPE html>
<html>

<head>
    <title>Nozzle Logs</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php require_once "nav.php"; ?>
    <div class="container wide">
        <div class="topbar list-topbar">
            <h2>Nozzle Logs</h2>
            <div>
                <?php if ($canEdit): ?>
                    <a class="btn" href="nozzle_add.php">Add Record</a>
                <?php endif; ?>

<a class="btn" href="csv_download.php?<?= http_build_query([
    'table' => 'nozzle_logs',
    'start' => $range['start'] ?? '',
    'end' => $range['end'] ?? '',
    'quick' => $range['quick'] ?? ''
]) ?>">
    Download CSV
</a>

            </div>
        </div>

        <?php render_range_filter($range, 'Filtering nozzle table to selected range'); ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Uploaded At</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Nozzle</th>
                        <th>Flow</th>
                        <th>Pressure</th>
                        <th>Min Deg</th>
                        <th>Max Deg</th>
                        <th>RPM</th>
                        <th>Comments</th>
                        <?php if ($canEdit): ?>
                            <th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="<?= $canEdit ? 12 : 11; ?>">No records found in selected range.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= h($row["id"]) ?></td>
                                <td><?= h($row["uploaded_at"]) ?></td>
                                <td><?= h($row["log_date"]) ?></td>
                                <td><?= h($row["log_time"]) ?></td>
                                <td>N<?= h($row["nozzle"]) ?></td>
                                <td><?= fmt($row["flow"], 1) ?> M3/hr</td>
                                <td><?= fmt($row["pressure"], 2) ?> BAR</td>
                                <td><?= fmt($row["min_deg"], 0) ?> °</td>
                                <td><?= fmt($row["max_deg"], 0) ?> °</td>
                                <td><?= fmt($row["rpm"], 1) ?> RPM</td>
                                <td><?= h($row["comments"]) ?></td>
                                <?php if ($canEdit): ?>
                                    <td>
                                        <a class="btn small" href="nozzle_edit.php?id=<?= (int) $row["id"] ?>">Edit</a>
                                        <a class="btn small danger" href="nozzle_delete.php?id=<?= (int) $row["id"] ?>"
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
