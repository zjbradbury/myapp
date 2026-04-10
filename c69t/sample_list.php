<?php
require_once "config.php";
requireLogin();

$canEdit = in_array(currentRole(), ["admin", "operator"], true);
$canDelete = in_array(currentRole(), ["admin", "operator"], true);

$range = get_range_filter_state(true);
$rows = fetch_log_rows($pdo, "sample_logs", $range, "log_date DESC, log_time DESC, id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sample Logs</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="container wide">
    <div class="topbar">
        <h2>Sample Logs</h2>
        <?php if ($canEdit): ?>
            <a class="btn" href="sample_add.php">Add Record</a>
        <?php endif; ?>
    </div>

    <?php render_range_filter($range, 'Filtering table to selected range'); ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Uploaded At</th>
                <th>Date</th>
                <th>Time</th>
                <th>Sample Location</th>
                <th>Nozzle</th>
                <th>Flow</th>
                <th>Mercury</th>
                <th>Solids</th>
                <th>Water</th>
                <th>Wax</th>
                <th>Operator</th>
                <th>Comments</th>
                <?php if ($canEdit || $canDelete): ?>
                    <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="<?= ($canEdit || $canDelete) ? 14 : 13; ?>">No records found in selected range.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= h($row["id"]) ?></td>
                        <td><?= h($row["uploaded_at"]) ?></td>
                        <td><?= h($row["log_date"]) ?></td>
                        <td><?= h($row["log_time"]) ?></td>
                        <td><?= h($row["sample_location"]) ?></td>
                        <td><?= h($row["nozzle"]) ?></td>
                        <td><?= $row["flow"] === null || $row["flow"] === '' ? '-' : fmt($row["flow"], 2) ?></td>
                        <td><?= $row["mercury"] === null || $row["mercury"] === '' ? '-' : fmt($row["mercury"], 3) ?></td>
                        <td><?= $row["solids"] === null || $row["solids"] === '' ? '-' : fmt($row["solids"], 2) ?></td>
                        <td><?= $row["water"] === null || $row["water"] === '' ? '-' : fmt($row["water"], 2) ?></td>
                        <td><?= $row["wax"] === null || $row["wax"] === '' ? '-' : fmt($row["wax"], 2) ?></td>
                        <td><?= h($row["operator"]) ?></td>
                        <td><?= h($row["comments"]) ?></td>
                        <?php if ($canEdit || $canDelete): ?>
                            <td>
                                <?php if ($canEdit): ?>
                                    <a href="sample_edit.php?id=<?= (int)$row["id"] ?>">Edit</a>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <a href="sample_delete.php?id=<?= (int)$row["id"] ?>" onclick="return confirm('Delete this record?')">Delete</a>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>