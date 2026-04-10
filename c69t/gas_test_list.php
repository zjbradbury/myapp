<?php
require_once "config.php";
requireLogin();

$canEdit = in_array(currentRole(), ["admin", "operator"], true);
$canDelete = currentRole() === "admin";

$range = get_range_filter_state(true);
$rows = fetch_log_rows($pdo, "gas_test_logs", $range, "log_date DESC, log_time DESC, id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gas Test Logs</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="container wide">
    <div class="topbar">
        <h2>Gas Test Logs</h2>
        <?php if ($canEdit): ?>
            <a class="btn" href="gas_test_add.php">Add Record</a>
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
                <th>Device</th>
                <th>Operator</th>
                <th>Location</th>
                <th>Area Details</th>
                <th>Mercury</th>
                <th>Benzene</th>
                <th>LEL</th>
                <th>H2S</th>
                <th>O2</th>
                <th>Product Details</th>
                <th>Actions Taken</th>
                <?php if ($canEdit || $canDelete): ?>
                    <th>Manage</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="<?= ($canEdit || $canDelete) ? 16 : 15; ?>">No records found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= h($row["id"]) ?></td>
                        <td><?= h($row["uploaded_at"]) ?></td>
                        <td><?= h($row["log_date"]) ?></td>
                        <td><?= h($row["log_time"]) ?></td>
                        <td><?= h($row["device"]) ?></td>
                        <td><?= h($row["operator"]) ?></td>
                        <td><?= h($row["location"]) ?></td>
                        <td><?= h($row["area_details"]) ?></td>

                        <td><?= $row["mercury"] ? fmt($row["mercury"], 3) : '-' ?></td>
                        <td><?= $row["benzene"] ? fmt($row["benzene"], 3) : '-' ?></td>
                        <td><?= $row["lel"] ? fmt($row["lel"], 2) : '-' ?></td>
                        <td><?= $row["h2s"] ? fmt($row["h2s"], 2) : '-' ?></td>
                        <td><?= $row["o2"] ? fmt($row["o2"], 2) : '-' ?></td>

                        <td><?= h($row["product_details"]) ?></td>
                        <td><?= h($row["action_taken"]) ?></td>

                        <?php if ($canEdit || $canDelete): ?>
                            <td>
                                <?php if ($canEdit): ?>
                                    <a href="gas_test_edit.php?id=<?= $row["id"] ?>">Edit</a>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <a href="gas_test_delete.php?id=<?= $row["id"] ?>" onclick="return confirm('Delete this record?')">Delete</a>
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