<?php
require_once "config.php";
requireRole(["admin", "operator"]);

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

$stmt = $pdo->prepare("DELETE FROM solid_waste_logs WHERE id = ?");
$stmt->execute([$id]);

header("Location: solid_waste_list.php");
exit;