<?php
require_once "config.php";
requireLogin();
require_once "nav.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container wide">
    <div class="topbar">
        <div>
            <h2>Dashboard</h2>
            <p>Logged in as <strong><?= h($_SESSION["username"]) ?></strong> (<?= h($_SESSION["role"]) ?>)</p>
        </div>
        <a class="btn danger" href="logout.php">Logout</a>
    </div>

    <div class="menu-grid">
        <a class="card-link" href="tricanter_list.php">Tricanter Logs</a>
        <a class="card-link" href="nozzle_list.php">Nozzle Logs</a>

        <?php if (currentRole() === "admin"): ?>
            <a class="card-link" href="users.php">Manage Users</a>
            <a class="card-link" href="user_create.php">Create User</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>