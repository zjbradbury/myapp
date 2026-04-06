<?php
require_once "config.php";

$username = "zack";
$password = "Butcher69";
$role = "admin";

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
$stmt->execute([$username, $hash, $role]);

echo "Admin user created.";