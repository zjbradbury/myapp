<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Australia/Adelaide');

$databaseHost = getenv('ASSETS_DB_HOST') ?: 'mariadb';
$databaseName = getenv('ASSETS_DB_NAME') ?: 'myapp';
$databaseUser = getenv('ASSETS_DB_USER') ?: 'zack';
$databasePassword = getenv('ASSETS_DB_PASSWORD') ?: 'Butcher69';

try {
    $pdo = new PDO(
        "mysql:host={$databaseHost};dbname={$databaseName};charset=utf8mb4",
        $databaseUser,
        $databasePassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit('Database connection failed.');
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

const ASSET_ROLE = 'cr_admin';
const MAX_UPLOAD_BYTES = 25 * 1024 * 1024;
function requireAssetAdmin(PDO $pdo): array
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    $stmt = $pdo->prepare('SELECT id, username, role, role2 FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !hash_equals(ASSET_ROLE, (string)($user['role2'] ?? ''))) {
        http_response_code(403);
        exit('Access denied. This application requires role2 = cr_admin.');
    }
    return $user;
}
function requireUserAdmin(PDO $pdo): array
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    $stmt = $pdo->prepare('SELECT id,username,role,role2 FROM users WHERE id=? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !hash_equals('admin', (string)($user['role'] ?? ''))) {
        http_response_code(403);
        exit('Access denied. User management requires role = admin.');
    }
    return $user;
}
function csrfToken(): string
{
    if (empty($_SESSION['asset_csrf'])) $_SESSION['asset_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['asset_csrf'];
}
function verifyCsrf(): void
{
    if (!hash_equals((string)($_SESSION['asset_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        exit('The form expired. Refresh and try again.');
    }
}
function envRequired(string $name): string
{
    $value = trim((string)getenv($name));
    if ($value === '') throw new RuntimeException("Missing required environment variable: {$name}");
    return $value;
}
