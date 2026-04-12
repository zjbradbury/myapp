<?php
require_once "config.php";
requireRole(["admin"]);

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Gas Test Dropdowns</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .manage-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
        }

        .manage-card {
            background: #122c44;
            border-radius: 10px;
            padding: 15px;
        }

        .manage-list {
            display: grid;
            gap: 12px;
            margin-top: 15px;
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
    <h2>Manage Gas Test Dropdowns</h2>

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
                    <label><input type="checkbox" name="allow_mercury" checked> Mercury</label>
                    <label><input type="checkbox" name="allow_benzene" checked> Benzene</label>
                    <label><input type="checkbox" name="allow_lel" checked> LEL</label>
                    <label><input type="checkbox" name="allow_h2s" checked> H2S</label>
                    <label><input type="checkbox" name="allow_o2" checked> O2</label>
                    <label><input type="checkbox" name="allow_product_details" checked> Product Details</label>
                    <label><input type="checkbox" name="allow_action_taken" checked> Actions Taken</label>
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
                                <label><input type="checkbox" name="allow_mercury" <?= ((int)$row["allow_mercury"] === 1) ? 'checked' : '' ?>> Mercury</label>
                                <label><input type="checkbox" name="allow_benzene" <?= ((int)$row["allow_benzene"] === 1) ? 'checked' : '' ?>> Benzene</label>
                                <label><input type="checkbox" name="allow_lel" <?= ((int)$row["allow_lel"] === 1) ? 'checked' : '' ?>> LEL</label>
                                <label><input type="checkbox" name="allow_h2s" <?= ((int)$row["allow_h2s"] === 1) ? 'checked' : '' ?>> H2S</label>
                                <label><input type="checkbox" name="allow_o2" <?= ((int)$row["allow_o2"] === 1) ? 'checked' : '' ?>> O2</label>
                                <label><input type="checkbox" name="allow_product_details" <?= ((int)$row["allow_product_details"] === 1) ? 'checked' : '' ?>> Product Details</label>
                                <label><input type="checkbox" name="allow_action_taken" <?= ((int)$row["allow_action_taken"] === 1) ? 'checked' : '' ?>> Actions Taken</label>
                            </div>

                            <div class="row-actions">
                                <button type="submit">Save Device</button>
                        </form>

                        <form method="post" class="inline-form" onsubmit="return confirm('Delete this device?');">
                            <input type="hidden" name="action" value="delete_device">
                            <input type="hidden" name="id" value="<?= (int)$row["id"] ?>">
                            <button type="submit" class="btn danger">Delete</button>
                        </form>
                            </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="manage-card">
            <h3>Operators</h3>

            <form method="post">
                <input type="hidden" name="action" value="add_operator">
                <input type="text" name="name" placeholder="New operator name" required>
                <label style="display:block; margin:10px 0;">
                    <input type="checkbox" name="active" checked>
                    Active
                </label>
                <button type="submit">Add Operator</button>
            </form>

            <div class="manage-list">
                <?php if (!$operators): ?>
                    <div class="manage-row">No operators found.</div>
                <?php endif; ?>

                <?php foreach ($operators as $row): ?>
                    <div class="manage-row">
                        <div><strong><?= h($row["name"]) ?></strong></div>
                        <div style="margin-top:8px;">
                            <span class="status-pill <?= ((int)$row["active"] === 1) ? 'active' : 'inactive' ?>">
                                <?= ((int)$row["active"] === 1) ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>

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
    </div>
</div>

</body>
</html>