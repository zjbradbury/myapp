<?php
require_once __DIR__ . '/config.php';
if (!siteIsLocked($pdo)) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logging System Has Been Shutdown</title>
    <link rel="icon" href="c69t.ico">
    <link rel="stylesheet" href="indexStyle.css">
    <style>
        .unlock-corner { position: fixed; top: 18px; right: 18px; }
        .shutdown-message { text-align: center; }
    </style>
</head>
<body class="app-page auth-page">
    <a class="btn unlock-corner" href="site_lock_access.php">Unlock</a>
    <main class="container shutdown-message">
        <h1>Logging System Has Been Shutdown</h1>
        <p>Contact A Site Admin</p>
    </main>
</body>
</html>
