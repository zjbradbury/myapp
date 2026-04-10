<?php
require_once "config.php";

requireRole(["admin"]);

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM gas_test_logs WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: gas_test_list.php");
exit;