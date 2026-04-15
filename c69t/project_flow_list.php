<?php
require_once "config.php";
requireLogin();
requireRole(["admin", "operator", "viewer"]);

$range = get_range_filter_state();

$sql = "
SELECT *
FROM project_flow_logs
WHERE 1=1
";

$params = [];

if ($range['start_sql']) {
    $sql .= " AND TIMESTAMP(log_date, log_time) >= ?";
    $params[] = $range['start_sql'];
}
if ($range['end_sql']) {
    $sql .= " AND TIMESTAMP(log_date, log_time) <= ?";
    $params[] = $range['end_sql'];
}

$sql .= " ORDER BY log_date DESC, log_time DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    <div class="topbar">
        <h2>Project Flow Logs</h2>

        <div>
            <?php if ($canEdit): ?>
                <a class="btn" href="project_flow_add.php">Add Record</a>
            <?php endif; ?>
        </div>
    </div>

    <?php render_range_filter(); ?>

    <table class="data-table">
        <thead>
        <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Oil</th>
            <th>Water</th>
            <th>Solid Waste</th>
            <th>Tricanter</th>
            <th>Nozzle</th>
            <th>Comments</th>
            <?php if ($canEdit): ?>
                <th></th>
            <?php endif; ?>
        </tr>
        </thead>

        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= h($r['log_date']) ?></td>
                <td><?= h(substr($r['log_time'], 0, 5)) ?></td>

                <td><?= number_format($r['total_recovered_oil'], 4) ?></td>
                <td><?= number_format($r['total_recovered_water'], 4) ?></td>
                <td><?= number_format($r['total_solid_waste'], 4) ?></td>
                <td><?= number_format($r['total_tricanter'], 4) ?></td>
                <td><?= number_format($r['total_nozzle'], 4) ?></td>

                <td><?= h($r['comments']) ?></td>

                <?php if ($canEdit): ?>
                    <td>
                        <a class="btn small" href="project_flow_edit.php?id=<?= $r['id'] ?>">Edit</a>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

</div>
</body>
</html>