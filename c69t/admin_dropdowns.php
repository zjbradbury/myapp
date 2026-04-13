<?php
require_once "config.php";
requireRole(["admin"]);

$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? '';

    try {
        if ($action === "add_device") {
            $name = trim($_POST["name"] ?? '');

            if ($name === '') {
                throw new Exception("Device name is required.");
            }

            $stmt = $pdo->prepare("
                INSERT INTO config_gas_test_devices
                (
                    name,
                    allow_mercury,
                    allow_benzene,
                    allow_lel,
                    allow_h2s,
                    allow_o2,
                    allow_product_details,
                    allow_action_taken
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $name,
                isset($_POST["allow_mercury"]) ? 1 : 0,
                isset($_POST["allow_benzene"]) ? 1 : 0,
                isset($_POST["allow_lel"]) ? 1 : 0,
                isset($_POST["allow_h2s"]) ? 1 : 0,
                isset($_POST["allow_o2"]) ? 1 : 0,
                isset($_POST["allow_product_details"]) ? 1 : 0,
                isset($_POST["allow_action_taken"]) ? 1 : 0,
            ]);

            $message = "Device added.";
        }

        if ($action === "update_device") {
            $id = (int)($_POST["id"] ?? 0);
            $name = trim($_POST["name"] ?? '');

            if ($id <= 0) {
                throw new Exception("Invalid device ID.");
            }

            if ($name === '') {
                throw new Exception("Device name is required.");
            }

            $stmt = $pdo->prepare("
                UPDATE config_gas_test_devices
                SET
                    name = ?,
                    allow_mercury = ?,
                    allow_benzene = ?,
                    allow_lel = ?,
                    allow_h2s = ?,
                    allow_o2 = ?,
                    allow_product_details = ?,
                    allow_action_taken = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $name,
                isset($_POST["allow_mercury"]) ? 1 : 0,
                isset($_POST["allow_benzene"]) ? 1 : 0,
                isset($_POST["allow_lel"]) ? 1 : 0,
                isset($_POST["allow_h2s"]) ? 1 : 0,
                isset($_POST["allow_o2"]) ? 1 : 0,
                isset($_POST["allow_product_details"]) ? 1 : 0,
                isset($_POST["allow_action_taken"]) ? 1 : 0,
                $id
            ]);

            $message = "Device updated.";
        }

        if ($action === "delete_device") {
            $id = (int)($_POST["id"] ?? 0);

            if ($id <= 0) {
                throw new Exception("Invalid device ID.");
            }

            $stmt = $pdo->prepare("DELETE FROM config_gas_test_devices WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Device deleted.";
        }

        if ($action === "add_operator") {
            $name = trim($_POST["name"] ?? '');

            if ($name === '') {
                throw new Exception("Operator name is required.");
            }

            $stmt = $pdo->prepare("
                INSERT INTO config_operators (name, active)
                VALUES (?, ?)
            ");
            $stmt->execute([
                $name,
                isset($_POST["active"]) ? 1 : 0
            ]);

            $message = "Operator added.";
        }

        if ($action === "update_operator") {
            $id = (int)($_POST["id"] ?? 0);
            $name = trim($_POST["name"] ?? '');

            if ($id <= 0) {
                throw new Exception("Invalid operator ID.");
            }

            if ($name === '') {
                throw new Exception("Operator name is required.");
            }

            $stmt = $pdo->prepare("
                UPDATE config_operators
                SET name = ?, active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name,
                isset($_POST["active"]) ? 1 : 0,
                $id
            ]);

            $message = "Operator updated.";
        }

        if ($action === "toggle_operator") {
            $id = (int)($_POST["id"] ?? 0);

            if ($id <= 0) {
                throw new Exception("Invalid operator ID.");
            }

            $stmt = $pdo->prepare("
                UPDATE config_operators
                SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $message = "Operator updated.";
        }

        if ($action === "delete_operator") {
            $id = (int)($_POST["id"] ?? 0);

            if ($id <= 0) {
                throw new Exception("Invalid operator ID.");
            }

            $stmt = $pdo->prepare("DELETE FROM config_operators WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Operator deleted.";
        }

        if ($action === "add_sample_location") {
            $name = trim($_POST["name"] ?? '');

            if ($name === '') {
                throw new Exception("Sample location name is required.");
            }

            $stmt = $pdo->prepare("
                INSERT INTO config_sample_location (name)
                VALUES (?)
            ");
            $stmt->execute([$name]);

            $message = "Sample location added.";
        }

        if ($action === "update_sample_location") {
            $id = (int)($_POST["id"] ?? 0);
            $name = trim($_POST["name"] ?? '');

            if ($id <= 0) {
                throw new Exception("Invalid sample location ID.");
            }

            if ($name === '') {
                throw new Exception("Sample location name is required.");
            }

            $stmt = $pdo->prepare("
                UPDATE config_sample_location
                SET name = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $id]);

            $message = "Sample location updated.";
        }

        if ($action === "delete_sample_location") {
            $id = (int)($_POST["id"] ?? 0);

            if ($id <= 0) {
                throw new Exception("Invalid sample location ID.");
            }

            $stmt = $pdo->prepare("
                DELETE FROM config_sample_location
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            $message = "Sample location deleted.";
        }

        if ($action === "add_gas_test_location") {
            $name = trim($_POST["name"] ?? '');

            if ($name === '') {
                throw new Exception("Gas test location name is required.");
            }

            $stmt = $pdo->prepare("
                INSERT INTO config_gas_test_location (name)
                VALUES (?)
            ");
            $stmt->execute([$name]);

            $message = "Gas test location added.";
        }

        if ($action === "update_gas_test_location") {
            $id = (int)($_POST["id"] ?? 0);
            $name = trim($_POST["name"] ?? '');

            if ($id <= 0) {
                throw new Exception("Invalid gas test location ID.");
            }

            if ($name === '') {
                throw new Exception("Gas test location name is required.");
            }

            $stmt = $pdo->prepare("
                UPDATE config_gas_test_location
                SET name = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $id]);

            $message = "Gas test location updated.";
        }

        if ($action === "delete_gas_test_location") {
            $id = (int)($_POST["id"] ?? 0);

            if ($id <= 0) {
                throw new Exception("Invalid gas test location ID.");
            }

            $stmt = $pdo->prepare("
                DELETE FROM config_gas_test_location
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            $message = "Gas test location deleted.";
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$devices = $pdo->query("
    SELECT *
    FROM config_gas_test_devices
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$operators = $pdo->query("
    SELECT id, name, active
    FROM config_operators
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$sampleLocations = $pdo->query("
    SELECT id, name
    FROM config_sample_location
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$gasTestLocations = $pdo->query("
    SELECT id, name
    FROM config_gas_test_location
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Dropdowns</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .manage-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
        }

        .stack-grid {
            display: grid;
            gap: 20px;
        }

        .manage-card {
            background: #122c44;
            border-radius: 10px;
            padding: 15px;
            min-width: 0;
        }

        .manage-card h3 {
            margin-top: 0;
            margin-bottom: 12px;
        }

        .manage-list {
            display: grid;
            gap: 12px;
            margin-top: 15px;
            max-height: 360px;
            overflow-y: auto;
            padding-right: 6px;
        }

        .manage-row {
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            padding: 12px;
        }

        .device-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px 16px;
            margin-top: 10px;
        }

        .check-row {
            display: flex;
            align-items: center;
            gap: 8px;
            line-height: 1.2;
            min-height: 26px;
        }

        .check-row input[type="checkbox"] {
            margin: 0;
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
        }

        .check-row span {
            display: inline-block;
        }

        .msg-ok {
            background: #1f5f2b;
            color: #fff;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .msg-error {
            background: #7a1f1f;
            color: #fff;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .status-pill {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-pill.active {
            background: #1f5f2b;
            color: #fff;
        }

        .status-pill.inactive {
            background: #666;
            color: #fff;
        }

        .row-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .inline-form {
            margin: 0;
        }

        .manage-list::-webkit-scrollbar {
            width: 10px;
        }

        .manage-list::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.25);
            border-radius: 999px;
        }

        .manage-list::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.06);
            border-radius: 999px;
        }

        @media (max-width: 900px) {
            .manage-grid {
                grid-template-columns: 1fr;
            }

            .device-form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php require_once "nav.php"; ?>

<div class="container wide">
    <h2>Manage Dropdowns</h2>

    <?php if ($message !== ''): ?>
        <div class="msg-ok"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="msg-error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="manage-grid">
        <div class="manage-card">
            <h3>Devices</h3>

            <form method="post">
                <input type="hidden" name="action" value="add_device">
                <input type="text" name="name" placeholder="New device name" required>

                <div class="device-form-grid">
                    <label class="check-row"><input type="checkbox" name="allow_mercury" checked><span>Mercury</span></label>
                    <label class="check-row"><input type="checkbox" name="allow_benzene" checked><span>Benzene</span></label>
                    <label class="check-row"><input type="checkbox" name="allow_lel" checked><span>LEL</span></label>
                    <label class="check-row"><input type="checkbox" name="allow_h2s" checked><span>H2S</span></label>
                    <label class="check-row"><input type="checkbox" name="allow_o2" checked><span>O2</span></label>
                    <label class="check-row"><input type="checkbox" name="allow_product_details" checked><span>Product Details</span></label>
                    <label class="check-row"><input type="checkbox" name="allow_action_taken" checked><span>Actions Taken</span></label>
                </div>

                <div class="row-actions">
                    <button type="submit">Add Device</button>
                </div>
            </form>

            <div class="manage-list">
                <?php if (!$devices): ?>
                    <div class="manage-row">No devices found.</div>
                <?php endif; ?>

                <?php foreach ($devices as $row): ?>
                    <div class="manage-row">
                        <form method="post">
                            <input type="hidden" name="action" value="update_device">
                            <input type="hidden" name="id" value="<?= (int)$row["id"] ?>">

                            <input type="text" name="name" value="<?= h($row["name"]) ?>" required>

                            <div class="device-form-grid">
                                <label class="check-row"><input type="checkbox" name="allow_mercury" <?= ((int)$row["allow_mercury"] === 1) ? 'checked' : '' ?>><span>Mercury</span></label>
                                <label class="check-row"><input type="checkbox" name="allow_benzene" <?= ((int)$row["allow_benzene"] === 1) ? 'checked' : '' ?>><span>Benzene</span></label>
                                <label class="check-row"><input type="checkbox" name="allow_lel" <?= ((int)$row["allow_lel"] === 1) ? 'checked' : '' ?>><span>LEL</span></label>
                                <label class="check-row"><input type="checkbox" name="allow_h2s" <?= ((int)$row["allow_h2s"] === 1) ? 'checked' : '' ?>><span>H2S</span></label>
                                <label class="check-row"><input type="checkbox" name="allow_o2" <?= ((int)$row["allow_o2"] === 1) ? 'checked' : '' ?>><span>O2</span></label>
                                <label class="check-row"><input type="checkbox" name="allow_product_details" <?= ((int)$row["allow_product_details"] === 1) ? 'checked' : '' ?>><span>Product Details</span></label>
                                <label class="check-row"><input type="checkbox" name="allow_action_taken" <?= ((int)$row["allow_action_taken"] === 1) ? 'checked' : '' ?>><span>Actions Taken</span></label>
                            </div>

                            <div class="row-actions">
                                <button type="submit">Save Device</button>
                            </div>
                        </form>

                        <form method="post" class="inline-form" onsubmit="return confirm('Delete this device?');">
                            <input type="hidden" name="action" value="delete_device">
                            <input type="hidden" name="id" value="<?= (int)$row["id"] ?>">
                            <button type="submit" class="btn danger">Delete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stack-grid">
            <div class="manage-card">
                <h3>Operators</h3>

                <form method="post">
                    <input type="hidden" name="action" value="add_operator">
                    <input type="text" name="name" placeholder="New operator name" required>

                    <label class="check-row" style="margin-top:10px;">
                        <input type="checkbox" name="active" checked>
                        <span>Active</span>
                    </label>

                    <div class="row-actions">
                        <button type="submit">Add Operator</button>
                    </div>
                </form>

                <div class="manage-list">
                    <?php if (!$operators): ?>
                        <div class="manage-row">No operators found.</div>
                    <?php endif; ?>

                    <?php foreach ($operators as $row): ?>
                        <div class="manage-row">
                            <form method="post">
                                <input type="hidden" name="action" value="update_operator">
                                <input type="hidden" name="id" value="<?= (int)$row["id"] ?>">

                                <input type="text" name="name" value="<?= h($row["name"]) ?>" required>

                                <label class="check-row" style="margin-top:10px;">
                                    <input type="checkbox" name="active" <?= ((int)$row["active"] === 1) ? 'checked' : '' ?>>
                                    <span>Active</span>
                                </label>

                                <div style="margin-top:8px;">
                                    <span class="status-pill <?= ((int)$row["active"] === 1) ? 'active' : 'inactive' ?>">
                                        <?= ((int)$row["active"] === 1) ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>

                                <div class="row-actions">
                                    <button type="submit">Save Operator</button>
                                </div>
                            </form>

                            <div class="row-actions">
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="toggle_operator">
                                    <input type="hidden" name="id" value="<?= (int)$row["id"] ?>">
                                    <button type="submit"><?= ((int)$row["active"] === 1) ? 'Set Inactive' : 'Set Active' ?></button>
                                </form>

                                <form method="post" class="inline-form" onsubmit="return confirm('Delete this operator?');">
                                    <input type="hidden" name="action" value="delete_operator">
                                    <input type="hidden" name="id" value="<?= (int)$row["id"] ?>">
                                    <button type="submit" class="btn danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="manage-card">
                <h3>Sample Locations</h3>

                <form method="post">
                    <input type="hidden" name="action" value="add_sample_location">
                    <input type="text" name="name" placeholder="New sample location" required>

                    <div class="row-actions">
                        <button type="submit">Add Sample Location</button>
                    </div>
                </form>

                <div class="manage-list">
                    <?php if (!$sampleLocations): ?>
                        <div class="manage-row">No sample locations found.</div>
                    <?php endif; ?>

                    <?php foreach ($sampleLocations as $row): ?>
                        <div class="manage-row">
                            <form method="post">
                                <input type="hidden" name="action" value="update_sample_location">
                                <input type="hidden" name="id" value="<?= (int)$row["id"] ?>">

                                <input type="text" name="name" value="<?= h($row["name"]) ?>" required>

                                <div class="row-actions">
                                    <button type="submit">Save Location</button>
                                </div>
                            </form>

                            <form method="post" class="inline-form" onsubmit="return confirm('Delete this sample location?');">
                                <input type="hidden" name="action" value="delete_sample_location">
                                <input type="hidden" name="id" value="<?= (int)$row["id"] ?>">
                                <button type="submit" class="btn danger">Delete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="manage-card">
                <h3>Gas Test Locations</h3>

                <form method="post">
                    <input type="hidden" name="action" value="add_gas_test_location">
                    <input type="text" name="name" placeholder="New gas test location" required>

                    <div class="row-actions">
                        <button type="submit">Add Gas Test Location</button>
                    </div>
                </form>

                <div class="manage-list">
                    <?php if (!$gasTestLocations): ?>
                        <div class="manage-row">No gas test locations found.</div>
                    <?php endif; ?>

                    <?php foreach ($gasTestLocations as $row): ?>
                        <div class="manage-row">
                            <form method="post">
                                <input type="hidden" name="action" value="update_gas_test_location">
                                <input type="hidden" name="id" value="<?= (int)$row["id"] ?>">

                                <input type="text" name="name" value="<?= h($row["name"]) ?>" required>

                                <div class="row-actions">
                                    <button type="submit">Save Location</button>
                                </div>
                            </form>

                            <form method="post" class="inline-form" onsubmit="return confirm('Delete this gas test location?');">
                                <input type="hidden" name="action" value="delete_gas_test_location">
                                <input type="hidden" name="id" value="<?= (int)$row["id"] ?>">
                                <button type="submit" class="btn danger">Delete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>