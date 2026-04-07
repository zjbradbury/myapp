<?php
session_start();

date_default_timezone_set('Australia/Adelaide');

$host = "mariadb";
$dbname = "myapp";
$dbuser = "zack";
$dbpass = "Butcher69";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser,
        $dbpass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

function isLoggedIn() {
    return isset($_SESSION["user_id"]);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

function currentRole() {
    return $_SESSION["role"] ?? "";
}

function requireRole(array $allowedRoles) {
    requireLogin();
    if (!in_array(currentRole(), $allowedRoles, true)) {
        die("Access denied.");
    }
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (!function_exists('fmt')) {
    function fmt($value, $decimals = 0) {
        if ($value === null || $value === '') return '-';
        if (!is_numeric($value)) return htmlspecialchars($value);
        return number_format((float)$value, $decimals, '.', '');
    }
}

?>