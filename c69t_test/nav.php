<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
$currentRole = $_SESSION['role'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
?>
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
            <a href="worldCup.php" class="<?= $currentPage === 'worldCup.php' ? 'active' : '' ?>">Sweepstake</a>
            <a href="logs.php?table=tricanter" class="<?= $currentPage === 'logs.php?table=tricanter' ? 'active' : '' ?>">View
                Logs</a>

            <a href="graphs.php" class="<?= $currentPage === 'graphs.php' ? 'active' : '' ?>">View Graphs</a>




            <!-- <a href="logs.php?table=nozzle" class="<?= $currentPage === 'logs.php?table=nozzle' ? 'active' : '' ?>">Nozzle Logs</a>
            <a href="logs.php?table=solid_waste" class="<?= $currentPage === 'logs.php?table=solid_waste' ? 'active' : '' ?>">Solid
                Waste Logs</a>

            <a href="logs.php?table=sample" class="<?= $currentPage === 'logs.php?table=sample' ? 'active' : '' ?>">Sample Logs</a>

            <a href="logs.php?table=gas_test" class="<?= $currentPage === 'logs.php?table=gas_test' ? 'active' : '' ?>">Gas Test
                Logs</a> -->

            <div class="hamburger-divider"></div>

            <?php if (in_array($currentRole, ['admin', 'operator'], true)): ?>
                <!-- <a href="record.php?action=add&table=tricanter" class="<?= $currentPage === 'record.php?action=add&table=tricanter' ? 'active' : '' ?>">Add Tricanter
                    Record</a>
                <a href="record.php?action=add&table=nozzle" class="<?= $currentPage === 'record.php?action=add&table=nozzle' ? 'active' : '' ?>">Add Nozzle Record</a>
                <a href="record.php?action=add&table=solid_waste" class="<?= $currentPage === 'record.php?action=add&table=solid_waste' ? 'active' : '' ?>">Add Solid
                    Waste Record</a> -->

                <a href="record.php?action=add&table=sample" class="<?= $currentPage === 'record.php?action=add&table=sample' ? 'active' : '' ?>">Add Sample Record</a>

                <a href="record.php?action=add&table=gas_test" class="<?= $currentPage === 'record.php?action=add&table=gas_test' ? 'active' : '' ?>">Add Gas Test
                    Record</a>

            <?php endif; ?>

            <?php if ($currentRole === 'admin'): ?>
                <div class="hamburger-divider"></div>
                <a href="users.php" class="<?= $currentPage === 'users.php' ? 'active' : '' ?>">Manage Users</a>
                <a href="user_create.php" class="<?= $currentPage === 'user_create.php' ? 'active' : '' ?>">Create User</a>

                <a href="csv_upload.php" class="<?= $currentPage === 'csv_upload.php' ? 'active' : '' ?>">CSV Upload</a>

                <a href="admin_dropdowns.php" class="<?= $currentPage === 'admin_dropdowns.php' ? 'active' : '' ?>">Dropdown
                    Config</a>

                    <a href="fracCalc.php" class="<?= $currentPage === 'fracCalc.php' ? 'active' : '' ?>">Frac Calc</a>

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