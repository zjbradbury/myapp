<?php
require_once "config.php";
requireRole(["admin", "operator"]);

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

$stmt = $pdo->prepare("DELETE FROM project_flow_logs WHERE id = ?");
$stmt->execute([$id]);

header("Location: project_flow_list.php");
exit;