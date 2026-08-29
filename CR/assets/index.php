<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/NextcloudClient.php';
require_once __DIR__ . '/PdfScanner.php';
$currentUser = requireAssetAdmin($pdo);
$message = '';
$messageType = 'error';
function uploadedFiles(array $input): array
{
    $out = [];
    if (!is_array($input['name'] ?? null)) return $out;
    foreach ($input['name'] as $i => $name) $out[] = ['name' => (string)$name, 'tmp_name' => (string)($input['tmp_name'][$i] ?? ''), 'size' => (int)($input['size'][$i] ?? 0), 'error' => (int)($input['error'][$i] ?? UPLOAD_ERR_NO_FILE)];
    return $out;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'create') {
            $number = strtoupper(trim((string)($_POST['asset_number'] ?? '')));
            $category = (string)($_POST['asset_category'] ?? '');
            $description = trim((string)($_POST['asset_description'] ?? ''));
            $testDate = (string)($_POST['asset_test_date'] ?? '');
            $span = filter_var($_POST['asset_retest_span'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1200]]);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $testDate);
            $files = array_merge(uploadedFiles($_FILES['asset_files'] ?? []), uploadedFiles($_FILES['folder_files'] ?? []));
            if (!in_array($category, ['MINSUP', 'HPW', 'VAC', 'OTHER'], true) || $span === false) throw new RuntimeException('Choose a valid category and retest span.');
            if (!$files) throw new RuntimeException('Select one or more files.');
            foreach ($files as $file) if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('Every attachment must upload successfully and be no larger than 25 MB.');
            $prefix = trim((string)($_POST['scan_prefix'] ?? ($_SESSION['asset_scan_prefix'] ?? 'AH')));
            $browserScans = json_decode((string)($_POST['browser_scan_results'] ?? '{}'), true);
            if (!is_array($browserScans)) $browserScans = [];
            $pdfTotal = count(array_filter($files, fn($file) => strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'pdf'));
            $groups = [];
            $unmatched = [];
            foreach ($files as $file) {
                $isPdf = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'pdf';
                $fileNumber = $number;
                $fileDate = $testDate;
                if ($isPdf) {
                    $browser = $browserScans[$file['name'] . '|' . $file['size']] ?? null;
                    if (is_array($browser) && isset($browser['asset_number'], $browser['test_date'])) {
                        $fileNumber = strtoupper(trim((string)$browser['asset_number']));
                        $fileDate = trim((string)$browser['test_date']);
                    } else {
                        $scan = PdfScanner::scan($file['tmp_name'], $prefix);
                        if ($scan['asset_number'] !== '' && $scan['test_date'] !== '') {
                            $fileNumber = $scan['asset_number'];
                            $fileDate = $scan['test_date'];
                        } elseif ($pdfTotal > 1) {
                            $unmatched[] = $file['name'];
                            continue;
                        }
                    }
                }
                $validDate = DateTimeImmutable::createFromFormat('!Y-m-d', $fileDate);
                if ($fileNumber === '' || !preg_match('/^[A-Z]{1,10}\d{3,10}$/', $fileNumber) || !$validDate || $validDate->format('Y-m-d') !== $fileDate || $fileDate > date('Y-m-d')) {
                    $unmatched[] = $file['name'];
                    continue;
                }
                $key = $fileNumber;
                $groups[$key]['test_date'] = isset($groups[$key]['test_date']) && $groups[$key]['test_date'] > $fileDate ? $groups[$key]['test_date'] : $fileDate;
                $groups[$key]['files'][] = ['file' => $file, 'test_date' => $fileDate, 'is_pdf' => $isPdf];
            }
            if ($unmatched) throw new RuntimeException('Could not detect both Asset Number and a non-future Test Date in: ' . implode(', ', $unmatched) . '. Nothing was uploaded.');
            $pdo->beginTransaction();
            $remoteFiles = [];
            $uploadedPdfAssetIds = [];
            try {
                $find = $pdo->prepare('SELECT id,asset_test_date FROM assets WHERE asset_number=? FOR UPDATE');
                $update = $pdo->prepare('UPDATE assets SET asset_category=?,asset_description=?,asset_test_date=?,asset_retest_span=?,uploaded_by=?,uploaded_at=CURRENT_TIMESTAMP WHERE id=?');
                $create = $pdo->prepare('INSERT INTO assets(asset_number,asset_category,asset_description,asset_test_date,asset_retest_span,uploaded_by) VALUES(?,?,?,?,?,?)');
                $insert = $pdo->prepare('INSERT INTO asset_files(asset_id,file_location,original_filename,test_date) VALUES(?,?,?,?)');
                $count = $pdo->prepare("SELECT COUNT(*) FROM asset_files WHERE asset_id=? AND test_date=? AND LOWER(original_filename) LIKE '%.pdf'");
                $cloud = new NextcloudClient();
                foreach ($groups as $groupNumber => $group) {
                    $latestDate = $group['test_date'];
                    $find->execute([$groupNumber]);
                    $existing = $find->fetch();
                    if ($existing) {
                        $assetId = (int)$existing['id'];
                        $effectiveDate = $latestDate > $existing['asset_test_date'] ? $latestDate : $existing['asset_test_date'];
                        $update->execute([$category, $description, $effectiveDate, $span, $currentUser['id'], $assetId]);
                    } else {
                        $create->execute([$groupNumber, $category, $description, $latestDate, $span, $currentUser['id']]);
                        $assetId = (int)$pdo->lastInsertId();
                    }
                    foreach ($group['files'] as $item) {
                        $file = $item['file'];
                        $fileDate = $item['test_date'];
                        $isPdf = $item['is_pdf'];
                        if ($isPdf) {
                            $uploadedPdfAssetIds[] = $assetId;
                            $count->execute([$assetId, $fileDate]);
                            $pdfNumber = (int)$count->fetchColumn() + 1;
                            $suffix = $pdfNumber === 1 ? '' : ' (' . $pdfNumber . ')';
                            $storedName = $groupNumber . ' - ' . (new DateTimeImmutable($fileDate))->format('d.m.Y') . $suffix . '.pdf';
                        } else {
                            $storedName = basename($file['name']);
                        }
                        $remote = $cloud->upload($file['tmp_name'], $storedName, $groupNumber, $isPdf);
                        $remoteFiles[] = $remote;
                        $insert->execute([$assetId, $remote, $storedName, $fileDate]);
                    }
                }
                $pdo->commit();
                if ($uploadedPdfAssetIds) {
                    $selected = array_map('intval', $_SESSION['selected_assets'] ?? []);
                    $_SESSION['selected_assets'] = array_values(array_unique(array_merge($selected, $uploadedPdfAssetIds)));
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if (isset($cloud)) foreach ($remoteFiles as $remote) try {
                    $cloud->delete($remote);
                } catch (Throwable $ignored) {
                }
                throw $e;
            }
            header('Location: index.php?created=1');
            exit;
        }
        if (in_array($action, ['select_asset', 'remove_selected', 'clear_selected'], true)) {
            $_SESSION['selected_assets'] = array_values(array_unique(array_map('intval', $_SESSION['selected_assets'] ?? [])));
            $id = (int)($_POST['asset_id'] ?? 0);
            if ($action === 'select_asset' && $id > 0 && !in_array($id, $_SESSION['selected_assets'], true)) $_SESSION['selected_assets'][] = $id;
            if ($action === 'remove_selected') $_SESSION['selected_assets'] = array_values(array_diff($_SESSION['selected_assets'], [$id]));
            if ($action === 'clear_selected') $_SESSION['selected_assets'] = [];
            $query = trim((string)($_POST['return_q'] ?? ''));
            header('Location: index.php' . ($query !== '' ? '?q=' . rawurlencode($query) : ''));
            exit;
        }
        if ($action === 'delete') {
            $id = filter_var($_POST['asset_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $exists = $pdo->prepare('SELECT id FROM assets WHERE id=?');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) throw new RuntimeException('Asset not found.');
            $stmt = $pdo->prepare('SELECT file_location FROM asset_files WHERE asset_id=?');
            $stmt->execute([$id]);
            $files = $stmt->fetchAll();
            $cloud = new NextcloudClient();
            foreach ($files as $file) $cloud->delete((string)$file['file_location']);
            $pdo->prepare('DELETE FROM assets WHERE id=?')->execute([$id]);
            header('Location: index.php?deleted=1');
            exit;
        }
        throw new RuntimeException('Unknown action.');
    } catch (Throwable $e) {
        $message = $e instanceof PDOException && $e->getCode() === '23000' ? 'That asset number already exists.' : $e->getMessage();
    }
}
if (isset($_GET['created'])) {
    $message = 'Asset history updated and files uploaded successfully.';
    $messageType = 'success';
}
if (isset($_GET['selected'])) {
    $message = 'The existing assets were selected and are ready to download.';
    $messageType = 'success';
}
if (isset($_GET['deleted'])) {
    $message = 'Asset and files deleted.';
    $messageType = 'success';
}
$search = trim((string)($_GET['q'] ?? ''));
$sql = "SELECT a.*,COALESCE(u.username,'Deleted user') username,DATE_ADD(a.asset_test_date,INTERVAL a.asset_retest_span MONTH) next_test_date FROM assets a LEFT JOIN users u ON u.id=a.uploaded_by";
if ($search !== '') {
    $stmt = $pdo->prepare($sql . ' WHERE a.asset_number LIKE ? OR a.asset_category LIKE ? OR a.asset_description LIKE ? ORDER BY a.uploaded_at DESC');
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query($sql . ' ORDER BY a.uploaded_at DESC');
}
$assets = $stmt->fetchAll();
$fileStmt = $pdo->prepare("SELECT id,original_filename FROM asset_files WHERE asset_id=? AND test_date=(SELECT MAX(test_date) FROM asset_files WHERE asset_id=? AND LOWER(original_filename) LIKE '%.pdf') AND LOWER(original_filename) LIKE '%.pdf' ORDER BY id DESC LIMIT 1");
$selectedIds = array_values(array_unique(array_filter(array_map('intval', $_SESSION['selected_assets'] ?? []), fn($id) => $id > 0)));
$selectedAssets = [];
if ($selectedIds) {
    $marks = implode(',', array_fill(0, count($selectedIds), '?'));
    $selectedStmt = $pdo->prepare("SELECT id,asset_number,asset_category,asset_test_date FROM assets WHERE id IN ($marks) ORDER BY asset_number");
    $selectedStmt->execute($selectedIds);
    $selectedAssets = $selectedStmt->fetchAll();
    $_SESSION['selected_assets'] = array_column($selectedAssets, 'id');
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Asset Management</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header>
        <div><span class="eyebrow">CR operations</span>
            <h1>Asset management</h1>
        </div>
        <nav class="user"><?= h($currentUser['username']) ?><?php if (($currentUser['role'] ?? '') === 'admin'): ?> · <a href="users.php">Users</a><?php endif ?> · <a href="logout.php">Log out</a></nav>
    </header>
    <main><?php if ($message !== ''): ?><div class="message <?= h($messageType) ?>"><?= h($message) ?></div><?php endif ?>
        <section class="card">
            <h2>Add asset</h2>
            <form method="post" enctype="multipart/form-data" class="asset-form" id="asset-form"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="create"><label>Asset number<input id="asset-number" name="asset_number" maxlength="100" placeholder="e.g. AH0000" required></label><label>Category<select name="asset_category" required>
                        <option value="">Select category</option>
                        <option>MINSUP</option>
                        <option>HPW</option>
                        <option>VAC</option>
                        <option>OTHER</option>
                    </select></label><label class="wide">Description<textarea name="asset_description" rows="3" required></textarea></label><label>Test date<input id="test-date" type="date" name="asset_test_date" required></label><label>Retest span (months)<input type="number" name="asset_retest_span" value="12" min="1" max="1200" required></label><label class="wide">Files (25 MB maximum per file)<input id="asset-files" type="file" name="asset_files[]" multiple><small>Choose one or multiple files.</small></label><label class="wide">Or choose a folder<input id="asset-folder" type="file" name="folder_files[]" multiple webkitdirectory directory></label>
                <div class="wide form-actions"><button type="button" id="scan-files">Scan PDFs and prefill</button><button type="submit">Upload Assets</button><span id="scan-status"></span></div>
            </form>
        </section>
        <section class="card">
            <div class="table-heading">
                <h2>Assets</h2>
                <form method="get" class="search"><input name="q" value="<?= h($search) ?>" placeholder="Search assets"><button>Search</button><?php if ($search !== ''): ?><a href="index.php">Clear</a><?php endif ?></form>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Uploaded</th>
                            <th>Asset #</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Test date</th>
                            <th>Retest</th>
                            <th>Next test</th>
                            <th>Latest PDF</th>
                            <th>By</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody><?php if (!$assets): ?><tr>
                                <td colspan="10" class="empty">No assets found.</td>
                            </tr><?php endif ?><?php foreach ($assets as $asset): $fileStmt->execute([$asset['id'], $asset['id']]);
                                                    $assetFiles = $fileStmt->fetchAll(); ?><tr>
                                <td><?= h($asset['uploaded_at']) ?></td>
                                <td><strong><?= h($asset['asset_number']) ?></strong></td>
                                <td><?= h($asset['asset_category']) ?></td>
                                <td><?= nl2br(h($asset['asset_description'])) ?></td>
                                <td><?= h($asset['asset_test_date']) ?></td>
                                <td><?= h($asset['asset_retest_span']) ?> months</td>
                                <td class="<?= strtotime((string)$asset['next_test_date']) < strtotime(date('Y-m-d')) ? 'overdue' : '' ?>"><?= h($asset['next_test_date']) ?></td>
                                <td><?php foreach ($assetFiles as $file): ?><a href="download.php?id=<?= h($file['id']) ?>"><?= h($file['original_filename']) ?></a><?php endforeach ?></td>
                                <td><?= h($asset['username']) ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Delete this asset and all its Nextcloud files?')"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="asset_id" value="<?= h($asset['id']) ?>"><button class="danger">Delete</button></form>
                                </td>
                            </tr><?php endforeach ?></tbody>
                </table>
            </div>
        </section>
    </main>
    <?php if ($selectedAssets): ?><section class="card selected-assets">
            <div class="table-heading">
                <h2>Selected assets (<?= count($selectedAssets) ?>)</h2>
                <div class="selection-actions"><a class="button-link" href="download_selected.php">Download latest PDFs</a>
                    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="clear_selected"><input type="hidden" name="return_q" value="<?= h($search) ?>"><button class="danger">Clear all</button></form>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Asset #</th>
                            <th>Category</th>
                            <th>Latest test date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($selectedAssets as $selected): ?><tr>
                                <td><strong><?= h($selected['asset_number']) ?></strong></td>
                                <td><?= h($selected['asset_category']) ?></td>
                                <td><?= h($selected['asset_test_date']) ?></td>
                                <td>
                                    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="remove_selected"><input type="hidden" name="asset_id" value="<?= h($selected['id']) ?>"><input type="hidden" name="return_q" value="<?= h($search) ?>"><button class="danger">Remove</button></form>
                                </td>
                            </tr><?php endforeach ?></tbody>
                </table>
            </div>
        </section><?php endif ?>
    <style>
        .selection-actions {
            display: flex;
            gap: 10px;
            align-items: center
        }

        .button-link {
            display: inline-block;
            border-radius: 7px;
            background: var(--brand);
            color: #fff;
            padding: 10px 16px;
            font-weight: 700;
            text-decoration: none
        }

        .selected-assets table {
            min-width: 650px
        }
    </style>
    <script>
        const selectedAssetIds = <?= json_encode(array_map('intval', $selectedIds)) ?>,
            csrfToken = <?= json_encode(csrfToken()) ?>,
            currentSearch = <?= json_encode($search) ?>;
        const assetTable = [...document.querySelectorAll('table')].find(table => [...table.querySelectorAll('th')].some(th => th.textContent.trim() === 'Uploaded')),
            selectedPanel = document.querySelector('.selected-assets');
        if (assetTable && selectedPanel) assetTable.closest('section').before(selectedPanel);
        if (assetTable) {
            const heading = document.createElement('th');
            heading.textContent = 'Select';
            assetTable.querySelector('thead tr').prepend(heading);
            for (const row of assetTable.querySelectorAll('tbody tr')) {
                const idInput = row.querySelector('input[name=asset_id]');
                if (!idInput) continue;
                const id = Number(idInput.value),
                    cell = document.createElement('td'),
                    checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = selectedAssetIds.includes(id);
                checkbox.setAttribute('aria-label', 'Select asset for bulk download');
                checkbox.addEventListener('change', () => {
                    const form = document.createElement('form');
                    form.method = 'post';
                    for (const [name, value] of Object.entries({
                            csrf_token: csrfToken,
                            action: checkbox.checked ? 'select_asset' : 'remove_selected',
                            asset_id: id,
                            return_q: currentSearch
                        })) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = value;
                        form.appendChild(input);
                    }
                    document.body.appendChild(form);
                    form.submit();
                });
                cell.appendChild(checkbox);
                row.prepend(cell);
            }
        }
        const normal = document.querySelector('#asset-files'),
            folder = document.querySelector('#asset-folder'),
            status = document.querySelector('#scan-status');
        folder.addEventListener('change', () => {
            if (folder.files.length) status.textContent = folder.files.length + ' folder files selected.';
        });
        async function scan(prefix) {
            const selected = [...normal.files, ...folder.files];
            if (!selected.length) {
                status.textContent = 'Select files or a folder first.';
                return;
            }
            status.textContent = 'Scanning PDFs for prefix ' + prefix + '…';
            const data = new FormData();
            data.append('csrf_token', document.querySelector('[name=csrf_token]').value);
            data.append('prefix', prefix);
            for (const file of selected) data.append('asset_files[]', file);
            try {
                const response = await fetch('scan.php', {
                    method: 'POST',
                    body: data
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || 'Scan failed');
                if (result.asset_number) document.querySelector('#asset-number').value = result.asset_number;
                if (result.test_date) document.querySelector('#test-date').value = result.test_date;
                status.textContent = result.text_found ? 'Scan complete. Check the detected values before uploading.' : 'No readable PDF text found. Enter the values manually; image PDFs require server OCR.';
            } catch (error) {
                status.textContent = error.message;
            }
        }
        scan = async function(prefix) {
            const selected = [...normal.files, ...folder.files].filter(file => file.name.toLowerCase().endsWith('.pdf'));
            if (!selected.length) {
                status.textContent = 'Select PDF files or a folder first.';
                return;
            }
            let found = 0,
                failed = [];
            status.textContent = 'Scanning 0 of ' + selected.length + ' PDFs…';
            for (let i = 0; i < selected.length; i++) {
                const data = new FormData();
                data.append('csrf_token', document.querySelector('[name=csrf_token]').value);
                data.append('prefix', prefix);
                data.append('asset_files[]', selected[i]);
                try {
                    const response = await fetch('scan.php', {
                        method: 'POST',
                        body: data
                    });
                    const body = await response.text();
                    let result;
                    try {
                        result = JSON.parse(body);
                    } catch {
                        throw new Error('Server returned an HTML error for ' + selected[i].name + '. Check PHP upload limits and the server error log.');
                    }
                    if (!response.ok) throw new Error(result.error || 'Scan failed');
                    if (result.asset_number && result.test_date) found++;
                    if (!document.querySelector('#asset-number').value && result.asset_number) document.querySelector('#asset-number').value = result.asset_number;
                    if (!document.querySelector('#test-date').value && result.test_date) document.querySelector('#test-date').value = result.test_date;
                } catch (error) {
                    failed.push(selected[i].name + ': ' + error.message);
                }
                status.textContent = 'Scanning ' + (i + 1) + ' of ' + selected.length + ' PDFs…';
            }
            status.textContent = 'Scan complete: ' + found + ' identified' + (failed.length ? '; ' + failed.length + ' failed. ' + failed.join(' | ') : '.');
        };
        document.querySelector('#scan-files').addEventListener('click', () => {
            const prefix = prompt('Enter the asset-number prefix to search for:', 'AH');
            if (prefix !== null && prefix.trim() !== '') scan(prefix.trim());
        });
    </script>
    <script src="batch-preview.js" defer></script>
    <script src="scroll-memory.js" defer></script>
</body>

</html>
