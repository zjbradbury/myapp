<?php
require_once "config.php";
requireRole(["admin", "operator", "viewer"]);

$canEdit = in_array(currentRole(), ["admin", "operator"], true);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && $canEdit) {
    $selectedIds = $_POST['selected_ids'] ?? [];

    if (!is_array($selectedIds) || !$selectedIds) {
        $error = 'No records were selected.';
    } else {
        $ids = array_values(array_unique(array_map('intval', $selectedIds)));
        $ids = array_filter($ids, function ($id) {
            return $id > 0;
        });

        if (!$ids) {
            $error = 'No valid records were selected.';
        } else {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM solid_waste_logs WHERE id IN ($placeholders)");
            $stmt->execute($ids);

            $deletedCount = $stmt->rowCount();

            header('Location: solid_waste_logs.php?msg=' . urlencode($deletedCount . ' record(s) deleted'));
            exit;
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $message = (string) $_GET['msg'];
}

$range = get_range_filter_state();
$rows = fetch_log_rows($pdo, 'solid_waste_logs', $range);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Solid Waste Logs</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .bulk-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .checkbox-col {
            width: 44px;
            text-align: center;
        }

        .checkbox-cell {
            text-align: center;
            vertical-align: middle;
        }

        .notice-success,
        .notice-error {
            margin: 12px 0;
            padding: 10px 12px;
            border-radius: 8px;
        }

        .notice-success {
            background: rgba(40, 167, 69, 0.18);
            border: 1px solid rgba(40, 167, 69, 0.45);
            color: #d7ffe0;
        }

        .notice-error {
            background: rgba(220, 53, 69, 0.18);
            border: 1px solid rgba(220, 53, 69, 0.45);
            color: #ffd9de;
        }
    </style>
</head>

<body>
    <?php require_once "nav.php"; ?>

    <div class="container wide">
        <div class="topbar list-topbar">
            <h2>Solid Waste Logs</h2>
            <div>
                <?php if ($canEdit): ?>
                    <a class="btn" href="solid_waste_add.php">Add Record</a>
                <?php endif; ?>

                <a class="btn" href="csv_download.php?<?= http_build_query([
                    'table' => 'solid_waste_logs',
                    'start' => $range['start'] ?? '',
                    'end' => $range['end'] ?? '',
                    'quick' => $range['quick'] ?? ''
                ]) ?>">
                    Download CSV
                </a>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="notice-success"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="notice-error"><?= h($error) ?></div>
        <?php endif; ?>

        <?php render_range_filter($range, 'Filtering solid waste table to selected range'); ?>

        <form method="post" id="bulkDeleteForm" onsubmit="return confirmBulkDelete();">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <?php if ($canEdit): ?>
                                <th class="checkbox-col">
                                    <input type="checkbox" id="select_all">
                                </th>
                            <?php endif; ?>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Amount</th>
                            <th>Comments</th>
                            <?php if ($canEdit): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="<?= $canEdit ? 6 : 4; ?>">No records found in selected range.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <?php if ($canEdit): ?>
                                        <td class="checkbox-cell">
                                            <input type="checkbox" name="selected_ids[]" value="<?= (int) $row["id"] ?>" class="row-checkbox">
                                        </td>
                                    <?php endif; ?>

                                    <td><?= h($row["log_date"]) ?></td>
                                    <td><?= h($row["log_time"]) ?></td>
                                    <td>
                                        <?= $row["amount"] !== null && $row["amount"] !== "" ? fmt($row["amount"], 0) . ' kg' : '' ?>
                                    </td>
                                    <td><?= nl2br(h($row["comments"] ?? "")) ?></td>

                                    <?php if ($canEdit): ?>
                                        <td>
                                            <a class="btn small" href="solid_waste_edit.php?id=<?= (int) $row["id"] ?>">Edit</a>
                                            <a class="btn small danger" href="solid_waste_delete.php?id=<?= (int) $row["id"] ?>"
                                               onclick="return confirm('Delete this record?')">Delete</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($canEdit && $rows): ?>
                <div class="bulk-actions">
                    <button type="submit" name="bulk_delete" value="1" class="btn danger">
                        Delete Selected
                    </button>
                    <span id="selected_count">0 selected</span>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($canEdit): ?>
        <script>
            const selectAll = document.getElementById('select_all');
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            const selectedCount = document.getElementById('selected_count');

            function updateSelectedCount() {
                const checked = document.querySelectorAll('.row-checkbox:checked').length;
                if (selectedCount) {
                    selectedCount.textContent = checked + ' selected';
                }

                if (selectAll) {
                    selectAll.checked = rowCheckboxes.length > 0 && checked === rowCheckboxes.length;
                    selectAll.indeterminate = checked > 0 && checked < rowCheckboxes.length;
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    rowCheckboxes.forEach(cb => {
                        cb.checked = selectAll.checked;
                    });
                    updateSelectedCount();
                });
            }

            rowCheckboxes.forEach(cb => {
                cb.addEventListener('change', updateSelectedCount);
            });

            function confirmBulkDelete() {
                const checked = document.querySelectorAll('.row-checkbox:checked').length;

                if (checked === 0) {
                    alert('Please select at least one record to delete.');
                    return false;
                }

                return confirm('Delete ' + checked + ' selected record(s)?');
            }

            updateSelectedCount();
        </script>
    <?php endif; ?>
</body>
</html>