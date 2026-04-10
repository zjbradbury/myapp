<?php
require_once "config.php";

requireRole(["admin", "operator"]);


$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM sample_logs WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: sample_list.php");
exit;