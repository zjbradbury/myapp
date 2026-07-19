<?php
require_once "config.php";
requireRole(["admin"]);

$message = "";
$messageType = "error";

$currentUserId = $_SESSION["user_id"] ?? $_SESSION["id"] ?? null;
$currentUsername = $_SESSION["username"] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $userId = (int)($_POST["user_id"] ?? 0);

    if ($userId <= 0) {
        $message = "Invalid user selected.";
    } elseif ($action === "change_password") {
        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        if ($newPassword === "" || $confirmPassword === "") {
            $message = "Password fields are required.";
        } elseif ($newPassword !== $confirmPassword) {
            $message = "Passwords do not match.";
        } elseif (strlen($newPassword) < 6) {
            $message = "Password must be at least 6 characters.";
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $userId]);

            $message = "Password updated.";
            $messageType = "success";
        }
    } elseif ($action === "delete_user") {
        $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userToDelete) {
            $message = "User not found.";
        } elseif (($currentUserId !== null && (int)$currentUserId === (int)$userToDelete["id"]) || ($currentUsername !== null && $currentUsername === $userToDelete["username"])) {
            $message = "You cannot delete your own account while logged in.";
        } else {
            $adminCountStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            $adminCount = (int)$adminCountStmt->fetchColumn();

            if ($userToDelete["role"] === "admin" && $adminCount <= 1) {
                $message = "You cannot delete the last admin account.";
            } else {
                $delete = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $delete->execute([$userId]);

                $message = "User deleted.";
                $messageType = "success";
            }
        }
    } else {
        $message = "Invalid action.";
    }
}

$stmt = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY username ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>

<head>
    <title>Manage Users</title>
    <link rel="stylesheet" href="indexStyle.css">
</head>

<body>
    <?php require_once "nav.php"; ?>
    <div class="container wide">
        <div class="topbar">
            <h2>Users</h2>
            <a class="btn" href="user_create.php">Create User</a>
        </div>

        <?php if ($message !== ""): ?>
            <p class="message <?= h($messageType) ?>"><?= h($message) ?></p>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php
                    $isCurrentUser = ($currentUserId !== null && (int)$currentUserId === (int)$user["id"])
                        || ($currentUsername !== null && $currentUsername === $user["username"]);
                    ?>
                    <tr>
                        <td><?= h($user["id"]) ?></td>
                        <td><?= h($user["username"]) ?></td>
                        <td><?= h($user["role"]) ?></td>
                        <td><?= h($user["created_at"]) ?></td>
                        <td class="actions-cell">
                            <form method="post" class="inline-form">
                                <input type="hidden" name="action" value="change_password">
                                <input type="hidden" name="user_id" value="<?= h($user["id"]) ?>">
                                <input type="password" name="new_password" placeholder="New password" required>
                                <input type="password" name="confirm_password" placeholder="Confirm password" required>
                                <button type="submit">Change Password</button>
                            </form>

                            <form method="post" class="inline-form" onsubmit="return confirm('Delete user <?= h(addslashes($user["username"])) ?>? This cannot be undone.');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= h($user["id"]) ?>">
                                <button type="submit" class="danger" <?= $isCurrentUser ? "disabled" : "" ?>>Delete</button>
                                <?php if ($isCurrentUser): ?>
                                    <small>You cannot delete yourself.</small>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</body>

</html>
