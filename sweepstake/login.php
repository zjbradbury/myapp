<?php
require __DIR__ . '/config.php';
if (admin($pdo)) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    $s = $pdo->prepare("SELECT id,username,password,role FROM users WHERE username=? AND role='admin'");
    $s->execute([trim((string)($_POST['username'] ?? ''))]); $user = $s->fetch();
    if ($user && password_verify((string)($_POST['password'] ?? ''),$user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']=$user['id']; $_SESSION['username']=$user['username']; $_SESSION['role']=$user['role'];
        unset($_SESSION['sweepstake_csrf']); header('Location: index.php',true,303); exit;
    }
    $error='Invalid administrator username or password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin sign in · The Sweep</title><link rel="stylesheet" href="style.css"></head><body><main class="auth"><a class="brand" href="index.php">♞ THE SWEEP</a><div class="panel"><p class="eyebrow">ORGANISER ACCESS</p><h1>Welcome back.</h1><p>Sign in with your c69t administrator account.</p><?php if ($error): ?><p class="notice error" role="alert"><?= h($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= h(token()) ?>"><label>Username<input name="username" autocomplete="username" required maxlength="100"></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button>Sign in →</button></form></div><a href="index.php">← Back to the sweep</a></main></body></html>
