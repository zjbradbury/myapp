<?php
require_once "config.php";
requireRole(['admin', 'operator', 'viewer']);

header('Content-Type: application/json');
echo json_encode(buildMonitoringData($pdo));