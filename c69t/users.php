<?php
require_once "config.php";
requireRole(["admin"]);

$stmt = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY username ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>

<head>
    <title>Manage Users</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php require_once "nav.php"; ?>
    <div class="container wide">
        <div class="topbar">
            <h2>Users</h2>
            <a class="btn" href="user_create.php">Create User</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= h($user["id"]) ?></td>
                        <td><?= h($user["username"]) ?></td>
                        <td><?= h($user["role"]) ?></td>
                        <td><?= h($user["created_at"]) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</body>

</html>