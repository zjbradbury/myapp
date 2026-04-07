<?php
require_once "config.php";
requireRole(["admin", "operator", "viewer"]);

$stmt = $pdo->query("
    SELECT *
    FROM solid_waste_logs
    ORDER BY log_date DESC, log_time DESC, id DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$canEdit = in_array(currentRole(), ["admin", "operator"], true);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Solid Waste Logs</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="container wide">
    <div class="topbar">
        <h2>Solid Waste Logs</h2>
        <div>
            <?php if ($canEdit): ?>
                <a class="btn" href="solid_waste_add.php">Add Record</a>
            <?php endif; ?>
            <a class="btn" href="index.php">Back</a>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Amount</th>
                    <th>Comments</th>
                    <?php if ($canEdit): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="<?= $canEdit ? 5 : 4; ?>">No records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= h($row["log_date"]) ?></td>
                            <td><?= h($row["log_time"]) ?></td>
                            <td>
                                <?= $row["amount"] !== null && $row["amount"] !== "" ? h(number_format((float)$row["amount"], 2)) . ' kg' : '' ?>
                            </td>
                            <td><?= nl2br(h($row["comments"] ?? "")) ?></td>
                            <?php if ($canEdit): ?>
                                <td class="actions">
                                    <a class="btn" href="solid_waste_edit.php?id=<?= (int)$row["id"] ?>">Edit</a>
                                    <a class="btn btn-danger"
                                       href="solid_waste_delete.php?id=<?= (int)$row["id"] ?>"
                                       onclick="return confirm('Delete this record?');">
                                        Delete
                                    </a>
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