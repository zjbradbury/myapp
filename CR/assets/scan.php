<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PdfScanner.php';
header('Content-Type: application/json; charset=utf-8');
function scanError(string $message, int $status = 422): never
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}
if (!isLoggedIn()) scanError('Your login expired. Refresh the page and sign in again.', 401);
$access = $pdo->prepare('SELECT role2 FROM users WHERE id=?');
$access->execute([(int)$_SESSION['user_id']]);
if ($access->fetchColumn() !== ASSET_ROLE) scanError('You do not have permission to scan assets.', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') scanError('POST required.', 405);
if (!hash_equals((string)($_SESSION['asset_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $limit = ini_get('post_max_size');
    scanError($contentLength > 0 && empty($_POST) ? "The PDF exceeded the server post_max_size limit ({$limit})." : 'The form expired. Refresh the page and try again.', 419);
}
$files = $_FILES['asset_files'] ?? null;
$prefix = trim((string)($_POST['prefix'] ?? ''));
$_SESSION['asset_scan_prefix'] = $prefix;
if (!$files || !is_array($files['name'] ?? null)) scanError('Select a PDF file first.');
$result = ['asset_number' => '', 'test_date' => '', 'pdfs_scanned' => 0, 'text_found' => false];
foreach ($files['name'] as $i => $name) {
    $error = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) scanError('PDF upload failed with error code ' . $error . '. Check PHP upload_max_filesize.');
    if (strtolower(pathinfo((string)$name, PATHINFO_EXTENSION)) !== 'pdf') continue;
    $scan = PdfScanner::scan((string)$files['tmp_name'][$i], $prefix);
    $result['pdfs_scanned']++;
    $result['text_found'] = $result['text_found'] || $scan['text_found'];
    if ($result['asset_number'] === '') $result['asset_number'] = $scan['asset_number'];
    if ($result['test_date'] === '') $result['test_date'] = $scan['test_date'];
}
if ($result['pdfs_scanned'] === 0) scanError('No PDF file was supplied.');
echo json_encode($result);
