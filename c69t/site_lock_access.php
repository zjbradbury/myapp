<?php
require_once __DIR__ . '/config.php';

$admin = siteLockAdmin($pdo);
$locked = siteIsLocked($pdo);
if (!$locked && !$admin) {
    header('Location: login.php');
    exit;
}
$message = '';
$action = $locked ? 'unlock' : 'lock';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if (!hash_equals(siteLockToken(), (string)($_POST['csrf'] ?? ''))) {
        $message = 'Your session expired. Please try again.';
    } elseif (!in_array($action, ['lock', 'unlock', 'access'], true) || ($action === 'lock' && !$admin)) {
        http_response_code(403);
        $message = 'Administrator access required.';
    } elseif (($_SESSION['site_lock_retry_after'] ?? 0) > time()) {
        $message = 'Too many attempts. Please wait a minute before trying again.';
    } else {
        // Existing admins must confirm their own password, not another account's.
        $stmt = $pdo->prepare($admin
            ? 'SELECT id, username, role, password FROM users WHERE id = ?'
            : 'SELECT id, username, role, password FROM users WHERE username = ?');
        $stmt->execute([$admin ? $_SESSION['user_id'] : trim((string)($_POST['username'] ?? ''))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['role'] !== 'admin' || !password_verify((string)($_POST['password'] ?? ''), $user['password'])) {
            $message = 'Invalid administrator credentials.';
            $_SESSION['site_lock_failures'] = ($_SESSION['site_lock_failures'] ?? 0) + 1;
            if ($_SESSION['site_lock_failures'] >= 5) {
                $_SESSION['site_lock_retry_after'] = time() + 60;
                $_SESSION['site_lock_failures'] = 0;
            }
        } else {
            if ($action !== 'access') {
                setSetting($pdo, 'site_locked', $action === 'lock' ? '1' : '0');
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            unset($_SESSION['site_lock_failures'], $_SESSION['site_lock_retry_after'], $_SESSION['site_lock_csrf']);
            header('Location: index.php', true, 303);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Site Lockdown</title>
    <link rel="icon" href="c69t.ico">
    <link rel="stylesheet" href="indexStyle.css">
</head>
<body class="app-page auth-page">
<main class="container">
    <div class="page-heading">
        <div class="section-kicker">administrator access</div>
        <h1>Site Lockdown</h1>
        <p>The site is currently <?= $locked ? 'locked' : 'unlocked' ?>. HMI uploads continue while locked.</p>
        <p><?= $admin ? 'Re-enter your password to change the lock.' : 'Sign in with an administrator account.' ?></p>
    </div>
    <?php if ($message !== ''): ?><p class="message error" role="alert"><?= h($message) ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(siteLockToken()) ?>">
        <?php if (!$admin): ?>
            <label for="username">Admin username</label>
            <input id="username" name="username" autocomplete="username" required>
        <?php else: ?>
            <p>Confirming as <?= h($_SESSION['username']) ?></p>
        <?php endif; ?>
        <label for="password">Admin password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button type="submit" name="action" value="<?= $locked ? 'unlock' : 'lock' ?>"><?= $locked ? 'Unlock Site' : 'Lock Site' ?></button>
        <?php if ($locked): ?>
            <button type="submit" name="action" value="access">Admin Login — Keep Site Locked</button>
        <?php endif; ?>
    </form>
    <p><a href="<?= $admin ? 'index.php' : 'shutdown.php' ?>">Cancel</a></p>
</main>
</body>
</html>
