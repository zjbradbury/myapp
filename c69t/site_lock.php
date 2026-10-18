<?php
// Keep the ingestion endpoint independent of interactive site access.
function siteIsLocked(PDO $pdo): bool
{
    return getSetting($pdo, 'site_locked', '0') === '1';
}

function siteLockAdmin(PDO $pdo): bool
{
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        return false;
    }
    // Recheck the database so deleted or demoted admins cannot bypass the lock.
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetchColumn() === 'admin';
}

function siteLockBlocks(string $page, bool $locked, bool $admin): bool
{
    return $locked && !$admin && !in_array($page, [
        'shutdown.php', 'site_lock_access.php', 'logout.php', 'upload_hmi_sections.php',
    ], true);
}

function enforceSiteLock(PDO $pdo): void
{
    header('Cache-Control: no-store, private');
    $page = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    if (siteLockBlocks($page, siteIsLocked($pdo), siteLockAdmin($pdo))) {
        // Use an absolute application path, including when PHP PATH_INFO is used.
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/c69t/index.php'), '/');
        header('Location: ' . $base . '/shutdown.php', true, 303);
        exit;
    }
}

function siteLockToken(): string
{
    if (empty($_SESSION['site_lock_csrf'])) {
        $_SESSION['site_lock_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['site_lock_csrf'];
}
