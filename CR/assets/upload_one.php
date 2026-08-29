<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/NextcloudClient.php';
require_once __DIR__ . '/PdfScanner.php';
header('Content-Type: application/json; charset=utf-8');
function uploadError(string $message, int $status = 422): never
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}
if (!isLoggedIn()) uploadError('Your login expired. Refresh and sign in again.', 401);
$access = $pdo->prepare('SELECT id,role2 FROM users WHERE id=?');
$access->execute([(int)$_SESSION['user_id']]);
$user = $access->fetch();
if (!$user || ($user['role2'] ?? '') !== ASSET_ROLE) uploadError('Asset administrator access is required.', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') uploadError('POST required.', 405);
if (!hash_equals((string)($_SESSION['asset_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) uploadError('The form expired. Refresh and try again.', 419);
$file = $_FILES['asset_file'] ?? null;
if (!$file || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) uploadError('The file upload failed. Check the per-file server upload limit.');
if ((int)$file['size'] <= 0 || (int)$file['size'] > MAX_UPLOAD_BYTES) uploadError('The file must be no larger than 25 MB.');
$category = (string)($_POST['asset_category'] ?? '');
$description = trim((string)($_POST['asset_description'] ?? ''));
$span = filter_var($_POST['asset_retest_span'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1200]]);
if (!in_array($category, ['MINSUP', 'HPW', 'VAC', 'OTHER'], true) || $span === false) uploadError('Choose a valid category and retest span.');
$isPdf = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)) === 'pdf';
$number = strtoupper(trim((string)($_POST['detected_asset_number'] ?? $_POST['asset_number'] ?? '')));
$testDate = trim((string)($_POST['detected_test_date'] ?? $_POST['asset_test_date'] ?? ''));
if ($isPdf && ($number === '' || $testDate === '')) {
    $scan = PdfScanner::scan((string)$file['tmp_name'], trim((string)($_POST['scan_prefix'] ?? 'AH')));
    $number = $scan['asset_number'];
    $testDate = $scan['test_date'];
}
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $testDate);
if (!preg_match('/^[A-Z]{1,10}\d{3,10}$/', $number) || !$date || $date->format('Y-m-d') !== $testDate || $testDate > date('Y-m-d')) uploadError('Could not validate an Asset Number and non-future Test Date for ' . $file['name'] . '.');
if ($isPdf && ($_POST['skip_if_duplicate'] ?? '') === '1') {
    $duplicate = $pdo->prepare("SELECT a.id FROM assets a JOIN asset_files f ON f.asset_id=a.id WHERE a.asset_number=? AND f.test_date=? AND LOWER(f.original_filename) LIKE '%.pdf' LIMIT 1");
    $duplicate->execute([$number, $testDate]);
    $duplicateAssetId = (int)$duplicate->fetchColumn();
    if ($duplicateAssetId > 0) {
        $selected = array_map('intval', $_SESSION['selected_assets'] ?? []);
        $_SESSION['selected_assets'] = array_values(array_unique(array_merge($selected, [$duplicateAssetId])));
        echo json_encode(['ok' => true, 'skipped' => true, 'asset_id' => $duplicateAssetId, 'asset_number' => $number]);
        exit;
    }
}
$pdo->beginTransaction();
$remote = null;
try {
    $find = $pdo->prepare('SELECT id,asset_test_date FROM assets WHERE asset_number=? FOR UPDATE');
    $find->execute([$number]);
    $existing = $find->fetch();
    if ($existing) {
        $assetId = (int)$existing['id'];
        $latest = max($testDate, (string)$existing['asset_test_date']);
        $pdo->prepare('UPDATE assets SET asset_category=?,asset_description=?,asset_test_date=?,asset_retest_span=?,uploaded_by=?,uploaded_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$category, $description, $latest, $span, $user['id'], $assetId]);
    } else {
        $pdo->prepare('INSERT INTO assets(asset_number,asset_category,asset_description,asset_test_date,asset_retest_span,uploaded_by) VALUES(?,?,?,?,?,?)')->execute([$number, $category, $description, $testDate, $span, $user['id']]);
        $assetId = (int)$pdo->lastInsertId();
    }
    $storedName = basename((string)$file['name']);
    if ($isPdf) {
        $count = $pdo->prepare("SELECT COUNT(*) FROM asset_files WHERE asset_id=? AND test_date=? AND LOWER(original_filename) LIKE '%.pdf'");
        $count->execute([$assetId, $testDate]);
        $position = (int)$count->fetchColumn() + 1;
        $storedName = $number . ' - ' . $date->format('d.m.Y') . ($position === 1 ? '' : ' (' . $position . ')') . '.pdf';
    }
    $cloud = new NextcloudClient();
    $remote = $cloud->upload((string)$file['tmp_name'], $storedName, $number, $isPdf);
    $pdo->prepare('INSERT INTO asset_files(asset_id,file_location,original_filename,test_date) VALUES(?,?,?,?)')->execute([$assetId, $remote, $storedName, $testDate]);
    $pdo->commit();
    if ($isPdf) {
        $selected = array_map('intval', $_SESSION['selected_assets'] ?? []);
        $_SESSION['selected_assets'] = array_values(array_unique(array_merge($selected, [$assetId])));
    }
    echo json_encode(['ok' => true, 'asset_id' => $assetId, 'asset_number' => $number, 'filename' => $storedName]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($remote !== null && isset($cloud)) try {
        $cloud->delete($remote);
    } catch (Throwable $ignored) {
    }
    uploadError($error->getMessage(), 500);
}
