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
        http_response_code(403);
        die("Access denied.");
    }
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (!function_exists('fmt')) {
    function fmt($value, $decimals = 0) {
        if ($value === null || $value === '') return '-';
        if (!is_numeric($value)) return h($value);
        return number_format((float)$value, $decimals, '.', '');
    }
}

if (!function_exists('numeric_series')) {
    function numeric_series(array $rows, string $key): array {
        $out = [];
        foreach (array_reverse($rows) as $row) {
            if (isset($row[$key]) && $row[$key] !== '' && is_numeric($row[$key])) {
                $out[] = (float)$row[$key];
            }
        }
        return $out;
    }
}

if (!function_exists('label_series')) {
    function label_series(array $rows): array {
        $out = [];
        foreach (array_reverse($rows) as $row) {
            $out[] = trim(($row['log_date'] ?? '') . ' ' . ($row['log_time'] ?? ''));
        }
        return $out;
    }
}

if (!function_exists('to_datetime_local_value')) {
    function to_datetime_local_value(?string $value): string {
        if (!$value) return '';
        $ts = strtotime($value);
        if ($ts === false) return '';
        return date('Y-m-d\TH:i', $ts);
    }
}

if (!function_exists('get_current_shift_range')) {
    function get_current_shift_range(?int $timestamp = null): array {
        $timestamp = $timestamp ?? time();

        $hour = (int)date('G', $timestamp);
        $today = date('Y-m-d', $timestamp);
        $yesterday = date('Y-m-d', strtotime('-1 day', $timestamp));
        $tomorrow = date('Y-m-d', strtotime('+1 day', $timestamp));

        if ($hour >= 6 && $hour < 18) {
            $start = $today . ' 06:00';
            $end   = $today . ' 18:00';
        } elseif ($hour >= 18) {
            $start = $today . ' 18:00';
            $end   = $tomorrow . ' 06:00';
        } else {
            $start = $yesterday . ' 18:00';
            $end   = $today . ' 06:00';
        }

        return [$start, $end];
    }
}

if (!function_exists('solid_diff_minutes_rows')) {
    function solid_diff_minutes_rows(array $rows): array {
        $chronological = array_reverse($rows);
        $prevTs = null;

        foreach ($chronological as &$row) {
            $currentTs = null;

            if (!empty($row['log_date']) && !empty($row['log_time'])) {
                $currentTs = strtotime($row['log_date'] . ' ' . $row['log_time']);
            }

            if ($currentTs !== null && $prevTs !== null) {
                $row['_diff_minutes'] = round(($currentTs - $prevTs) / 60, 2);
            } else {
                $row['_diff_minutes'] = null;
            }

            if ($currentTs !== null) {
                $prevTs = $currentTs;
            }
        }
        unset($row);

        return array_reverse($chronological);
    }
}

if (!function_exists('solid_diff_series')) {
    function solid_diff_series(array $rows): array {
        $out = [];
        foreach (array_reverse($rows) as $row) {
            if (isset($row['_diff_minutes']) && $row['_diff_minutes'] !== null && is_numeric($row['_diff_minutes'])) {
                $out[] = (float)$row['_diff_minutes'];
            } else {
                $out[] = null;
            }
        }
        return $out;
    }
}
?>