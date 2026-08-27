<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';
$current = requireUserAdmin($pdo);
$message = '';
$messageType = 'error';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['user_id'] ?? 0);
    try {
        if ($action === 'create') {
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = trim((string)($_POST['role'] ?? 'user'));
            $role2 = isset($_POST['asset_access']) ? ASSET_ROLE : null;
            if ($username === '' || strlen($password) < 6) throw new RuntimeException('Enter a username and a password of at least 6 characters.');
            if (!in_array($role, ['user', 'operator', 'viewer', 'admin'], true)) throw new RuntimeException('Invalid primary role.');
            $stmt = $pdo->prepare('INSERT INTO users (username,password,role,role2) VALUES (?,?,?,?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $role2]);
            $message = 'User created.';
            $messageType = 'success';
        } elseif ($id <= 0) throw new RuntimeException('Invalid user.');
        elseif ($action === 'password') {
            $password = (string)($_POST['password'] ?? '');
            if (strlen($password) < 6) throw new RuntimeException('Password must be at least 6 characters.');
            $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            $message = 'Password updated.';
            $messageType = 'success';
        } elseif ($action === 'access') {
            if ($id === (int)$current['id'] && !isset($_POST['asset_access'])) throw new RuntimeException('You cannot remove your own asset access.');
            $pdo->prepare('UPDATE users SET role2=? WHERE id=?')->execute([isset($_POST['asset_access']) ? ASSET_ROLE : null, $id]);
            $message = 'Asset access updated.';
            $messageType = 'success';
        } elseif ($action === 'delete') {
            if ($id === (int)$current['id']) throw new RuntimeException('You cannot delete your own account.');
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            $message = 'User deleted.';
            $messageType = 'success';
        } else throw new RuntimeException('Unknown action.');
    } catch (PDOException $e) {
        $message = $e->getCode() === '23000' ? 'That username already exists, or this user is linked to existing records.' : $e->getMessage();
    } catch (Throwable $e) {
        $message = $e->getMessage();
    }
}
$users = $pdo->query('SELECT id,username,role,role2,created_at FROM users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Asset Users</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header>
        <div><span class="eyebrow">CR operations</span>
            <h1>Users</h1>
        </div>
        <nav class="user"><?= h($current['username']) ?><?php if (($current['role2'] ?? '') === ASSET_ROLE): ?> · <a href="index.php">Assets</a><?php endif ?> · <a href="logout.php">Log out</a></nav>
    </header>
    <main><?php if ($message !== ''): ?><div class="message <?= h($messageType) ?>"><?= h($message) ?></div><?php endif ?>
        <section class="card">
            <h2>Create user</h2>
            <form method="post" class="asset-form"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="create"><label>Username<input name="username" required></label><label>Password<input type="password" name="password" minlength="6" required></label><label>Primary role<select name="role">
                        <option>user</option>
                        <option>operator</option>
                        <option>viewer</option>
                        <option>admin</option>
                    </select></label><label class="check"><input type="checkbox" name="asset_access" value="1" checked> Asset administrator access</label>
                <div class="wide"><button>Create user</button></div>
            </form>
        </section>
        <section class="card">
            <h2>Existing users</h2>
            <div class="table-wrap">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Primary role</th>
                            <th>Asset access</th>
                            <th>Created</th>
                            <th>Change password</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($users as $user): ?><tr>
                                <td><strong><?= h($user['username']) ?></strong></td>
                                <td><?= h($user['role']) ?></td>
                                <td>
                                    <form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="access"><input type="hidden" name="user_id" value="<?= h($user['id']) ?>"><label class="check"><input type="checkbox" name="asset_access" value="1" <?= ($user['role2'] ?? '') === ASSET_ROLE ? 'checked' : '' ?>> cr_admin</label><button>Save</button></form>
                                </td>
                                <td><?= h($user['created_at']) ?></td>
                                <td>
                                    <form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="password"><input type="hidden" name="user_id" value="<?= h($user['id']) ?>"><input type="password" name="password" minlength="6" placeholder="New password" required><button>Change</button></form>
                                </td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Delete this user?')"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= h($user['id']) ?>"><button class="danger" <?= ((int)$user['id'] === (int)$current['id']) ? 'disabled' : '' ?>>Delete</button></form>
                                </td>
                            </tr><?php endforeach ?></tbody>
                </table>
            </div>
        </section>
    </main>
</body>

</html>