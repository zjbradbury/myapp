<?php
require_once "config.php";
requireLogin();

$stmt = $pdo->query("SELECT * FROM nozzle_logs ORDER BY log_date DESC, log_time DESC, id DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <div class="topbar">
            <h2>Nozzle Logs</h2>
            <div>
                <?php if ($canEdit): ?>
                    <a class="btn" href="nozzle_add.php">Add Record</a>
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
            </tbody>
        </table>
    </div>
</body>

</html>