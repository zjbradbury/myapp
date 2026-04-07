<?php
require_once "config.php";
requireRole(["admin"]);

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = trim($_POST["role"] ?? "user");

    if ($username === "" || $password === "" || $role === "") {
        $message = "All fields are required.";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);

        if ($check->fetch()) {
            $message = "Username already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hash, $role]);
            header("Location: users.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Create User</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php require_once "nav.php"; ?>
    <div class="container">
        <h2>Create User</h2>

        <?php if ($message !== ""): ?>
            <p class="message error"><?= h($message) ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>

            <select name="role" required>
                <option value="user">user</option>
                <option value="operator">operator</option>
                <option value="viewer">viewer</option>
                <option value="admin">admin</option>
            </select>

            <button type="submit">Create User</button>
        </form>

    </div>
</body>

</html>