<?php
require __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['sweepstake_csrf']);
    session_regenerate_id(true);
    header('Location: index.php', true, 303); exit;
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign out · The Sweep</title><link rel="stylesheet" href="style.css"></head><body><main class="auth"><div class="panel"><h1>Sign out?</h1><p>This signs you out of your shared admin session in this browser.</p><form method="post"><input type="hidden" name="csrf" value="<?= h(token()) ?>"><button>Sign out</button></form><a href="index.php">Back to the sweep</a></div></main></body></html>
