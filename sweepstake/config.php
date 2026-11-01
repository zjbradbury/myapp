<?php
// Independent sweepstake configuration; uses the shared database and admin accounts.
session_start();
date_default_timezone_set('Australia/Adelaide');
header('Cache-Control: no-store, private');
$host = "mariadb";
$dbname = "myapp";
$dbuser = "zack";
$dbpass = "Butcher69";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Sweepstake database: ' . $e->getMessage());
    http_response_code(503);
    exit('Sweepstake database unavailable. Please check the connection and PHP pdo_mysql extension.');
}
require_once __DIR__ . '/functions.php';
