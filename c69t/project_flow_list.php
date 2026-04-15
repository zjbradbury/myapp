<?php
require_once "config.php";
requireLogin();

$range = get_range_filter_state();
$rows = fetch_log_rows($pdo, 'project_flow_logs', $range);
$canEdit = in_array(currentRole(), ["admin", "operator"], true);
?>
<!DOCTYPE html>
<html>

<head>
    <title>Project Flow Logs</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php require_once "nav.php"; ?>

    <div class="container wide">
        <div class="topbar list-topbar">
            <h2>Project Flow Logs</h2>
            <div>
                <?php if ($canEdit): ?>
                    <a class="btn" href="project_flow_add.php">Add Record</a>
                <?php endif; ?>

                <a class="btn" href="csv_download.php?<?= http_build_query([
                    'table' => 'project_flow_logs',
                    'start' => $range['start'] ?? '',
                    'end' => $range['end'] ?? '',
                    'quick' => $range['quick'] ?? ''
                ]) ?>">
                    Download CSV
                </a>
            </div>
        </div>

        <?php render_range_filter($range, 'Filtering project flow table to selected range'); ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Uploaded At</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Recovered Oil</th>
                        <th>Recovered Water</th>
                        <th>Solid Waste</th>
                        <th>Tricanter</th>
                        <th>Nozzle</th>
                        <th>Comments</th>
                        <?php if ($canEdit): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="<?= $canEdit ? 11 : 10; ?>">No records found in selected range.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= h($row["id"]) ?></td>
                                <td><?= h($row["uploaded_at"]) ?></td>
                                <td><?= h($row["log_date"]) ?></td>
                                <td><?= h($row["log_time"]) ?></td>
                                <td><?= fmt($row["total_recovered_oil"], 4) ?></td>
                                <td><?= fmt($row["total_recovered_water"], 4) ?></td>
                                <td><?= fmt($row["total_solid_waste"], 4) ?></td>
                                <td><?= fmt($row["total_tricanter"], 4) ?></td>
                                <td><?= fmt($row["total_nozzle"], 4) ?></td>
                                <td><?= h($row["comments"]) ?></td>
                                <?php if ($canEdit): ?>
                                    <td>
                                        <a class="btn small" href="project_flow_edit.php?id=<?= (int) $row["id"] ?>">Edit</a>
                                        <a class="btn small danger" href="project_flow_delete.php?id=<?= (int) $row["id"] ?>"
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