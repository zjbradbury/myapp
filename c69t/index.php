<?php
require_once "config.php";
requireRole(['admin', 'operator', 'viewer']);

/* =========================
   HANDLE MONITOR POST
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['monitor_form'])) {

    if ($_POST['monitor_form'] === 'master') {

        setSetting($pdo, 'monitor_master', isset($_POST['monitor_master']) ? '1' : '0');

        $refresh = (int)($_POST['monitor_refresh_seconds'] ?? 30);
        if ($refresh < 5) $refresh = 5;
        if ($refresh > 300) $refresh = 300;

        setSetting($pdo, 'monitor_refresh_seconds', (string)$refresh);
    }

    if ($_POST['monitor_form'] === 'item') {

        $key = $_POST['monitor_key'] ?? '';

        $allowed = ['nozzle','tricanter','solid_waste','sample','gas_test'];

        if (in_array($key, $allowed, true)) {

            setSetting($pdo, "monitor_{$key}_enabled",
                isset($_POST['monitor_enabled']) ? '1' : '0'
            );

            $minutes = (int)($_POST['monitor_minutes'] ?? 60);
            if ($minutes < 1) $minutes = 1;
            if ($minutes > 1440) $minutes = 1440;

            setSetting($pdo, "monitor_{$key}_minutes", (string)$minutes);
        }
    }

    header("Location: ".$_SERVER['REQUEST_URI']);
    exit;
}

$range = get_range_filter_state();
$monitorData = buildMonitoringData($pdo);

/* =========================
   LOAD DATA (unchanged)
   ========================= */
$nozzle = fetch_log_rows($pdo,'nozzle_logs',$range,'id DESC');
$tricanter = fetch_log_rows($pdo,'tricanter_logs',$range,'id DESC');
$solidWaste = fetch_log_rows($pdo,'solid_waste_logs',$range,'id DESC');

$latestNozzle = $nozzle[0] ?? [];
$latestTricanter = $tricanter[0] ?? [];
$latestSolidWaste = $solidWaste[0] ?? [];

$systemStatus = (!empty($latestNozzle) || !empty($latestTricanter) || !empty($latestSolidWaste)) ? 'ONLINE' : 'NO DATA';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="indexStyle.css">
</head>
<body>

<?php require_once "nav.php"; ?>
<h1>Dashboard</h1>

<!-- =========================
     MONITOR SECTION
========================= -->

<div class="monitor-shell">

    <!-- MASTER -->
    <div class="monitor-toolbar">

        <div class="monitor-heading">Monitoring</div>

        <form method="post" class="monitor-toolbar-right">
            <input type="hidden" name="monitor_form" value="master">

            <label class="switch-row">
                Master
                <input type="checkbox"
                       name="monitor_master"
                       <?= $monitorData['master_enabled'] ? 'checked' : '' ?>
                       onchange="this.form.submit()">
            </label>

            <label>
                Refresh
                <input type="number"
                       name="monitor_refresh_seconds"
                       value="<?= (int)$monitorData['refresh_seconds'] ?>"
                       min="5" max="300"
                       onchange="this.form.submit()">
            </label>
        </form>

    </div>

    <!-- ITEMS -->
    <div class="monitor-grid">

        <?php foreach ($monitorData['items'] as $key => $item): ?>

        <div class="monitor-item monitor-state-<?= strtolower($item['status']) ?>">

            <form method="post">
                <input type="hidden" name="monitor_form" value="item">
                <input type="hidden" name="monitor_key" value="<?= h($key) ?>">

                <div class="monitor-item-top">
                    <strong><?= h($item['label']) ?></strong>

                    <input type="checkbox"
                           name="monitor_enabled"
                           <?= $item['enabled'] ? 'checked' : '' ?>
                           onchange="this.form.submit()">
                </div>

                <div>Last: <?= h($item['last_entry_display']) ?></div>
                <div>Since: <?= h($item['since_text']) ?></div>

                <div>
                    Timer:
                    <input type="number"
                           name="monitor_minutes"
                           value="<?= (int)$item['minutes'] ?>"
                           min="1" max="1440"
                           onchange="this.form.submit()"
                           onblur="this.form.submit()">
                </div>

                <div>Countdown: <?= h($item['countdown']) ?></div>
                <div>Status: <?= h($item['status']) ?></div>

            </form>

        </div>

        <?php endforeach; ?>

    </div>

</div>

<!-- =========================
     YOUR DASHBOARD (UNCHANGED)
========================= -->

<div class="topbar">
    <div class="info-card">
        <div class="info-title">System Status</div>
        <div class="info-value"><?= $systemStatus ?></div>
    </div>
</div>

</body>
</html>