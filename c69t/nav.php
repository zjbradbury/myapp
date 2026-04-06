<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
$currentRole = $_SESSION['role'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
?>
<style>
.top-nav-wrap {
    position: fixed;
    top: 15px;
    right: 15px;
    z-index: 2000;
}

.menu-toggle {
    background: #163a59;
    border: 1px solid #2c5d87;
    color: #fff;
    width: 48px;
    height: 48px;
    border-radius: 10px;
    cursor: pointer;
    display: inline-flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 5px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.25);
}

.menu-toggle span {
    display: block;
    width: 22px;
    height: 3px;
    background: #fff;
    border-radius: 3px;
}

.hamburger-menu {
    position: absolute;
    top: 58px;
    right: 0;
    min-width: 240px;
    background: #122c44;
    border: 1px solid #2c5d87;
    border-radius: 12px;
    box-shadow: 0 10px 24px rgba(0,0,0,0.35);
    overflow: hidden;
    display: none;
}

.hamburger-menu.open {
    display: block;
}

.hamburger-header {
    padding: 12px 14px;
    background: #163a59;
    border-bottom: 1px solid #2c5d87;
    font-size: 13px;
    color: #cfe6ff;
}

.hamburger-header strong {
    color: #fff;
}

.hamburger-menu a {
    display: block;
    padding: 12px 14px;
    color: #fff;
    text-decoration: none;
    border-bottom: 1px solid #1f4a6e;
    font-size: 14px;
}

.hamburger-menu a:last-child {
    border-bottom: none;
}

.hamburger-menu a:hover,
.hamburger-menu a.active {
    background: #1b4568;
}

.hamburger-divider {
    height: 1px;
    background: #2c5d87;
}

@media (max-width: 700px) {
    .hamburger-menu {
        min-width: 210px;
    }
}
</style>

<div class="top-nav-wrap">
    <button class="menu-toggle" type="button" id="menuToggle" aria-label="Open navigation menu" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <div class="hamburger-menu" id="hamburgerMenu">
        <?php if ($isLoggedIn): ?>
            <div class="hamburger-header">
                Logged in as <strong><?= h($_SESSION['username'] ?? 'User') ?></strong><br>
                Role: <strong><?= h($currentRole ?: '-') ?></strong>
            </div>

            <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Home</a>
            <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
            <a href="tricanter_list.php" class="<?= $currentPage === 'tricanter_list.php' ? 'active' : '' ?>">Tricanter Logs</a>
            <a href="nozzle_list.php" class="<?= $currentPage === 'nozzle_list.php' ? 'active' : '' ?>">Nozzle Logs</a>

            <?php if (in_array($currentRole, ['admin', 'operator'], true)): ?>
                <a href="tricanter_add.php" class="<?= $currentPage === 'tricanter_add.php' ? 'active' : '' ?>">Add Tricanter Record</a>
                <a href="nozzle_add.php" class="<?= $currentPage === 'nozzle_add.php' ? 'active' : '' ?>">Add Nozzle Record</a>
            <?php endif; ?>

            <?php if ($currentRole === 'admin'): ?>
                <div class="hamburger-divider"></div>
                <a href="users.php" class="<?= $currentPage === 'users.php' ? 'active' : '' ?>">Manage Users</a>
                <a href="user_create.php" class="<?= $currentPage === 'user_create.php' ? 'active' : '' ?>">Create User</a>
            <?php endif; ?>

            <div class="hamburger-divider"></div>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php" class="<?= $currentPage === 'login.php' ? 'active' : '' ?>">Login</a>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const toggle = document.getElementById('menuToggle');
    const menu = document.getElementById('hamburgerMenu');

    if (!toggle || !menu) return;

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        const isOpen = menu.classList.toggle('open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
        if (!menu.contains(e.target) && !toggle.contains(e.target)) {
            menu.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>