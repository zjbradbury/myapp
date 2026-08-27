<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/NextcloudClient.php';
require_once __DIR__ . '/SimpleZip.php';
requireAssetAdmin($pdo);
$selected = array_values(array_filter(array_map('intval', $_SESSION['selected_assets'] ?? []), fn($id) => $id > 0));
if (!$selected) {
    header('Location: index.php');
    exit;
}
$temporary = [];
$zipPath = tempnam(sys_get_temp_dir(), 'assets_zip_');
if ($zipPath === false) exit('Unable to create download.');
$temporary[] = $zipPath;
try {
    $zip = new SimpleZip($zipPath);
    $cloud = new NextcloudClient();
    $query = $pdo->prepare("SELECT f.file_location,f.original_filename FROM asset_files f WHERE f.asset_id=? AND LOWER(f.original_filename) LIKE '%.pdf' ORDER BY f.test_date DESC,f.id DESC LIMIT 1");
    $added = 0;
    foreach ($selected as $assetId) {
        $query->execute([$assetId]);
        $file = $query->fetch();
        if (!$file) continue;
        $local = tempnam(sys_get_temp_dir(), 'asset_pdf_');
        if ($local === false) throw new RuntimeException('Unable to create temporary file.');
        $temporary[] = $local;
        $cloud->downloadToFile((string)$file['file_location'], $local);
        $zip->addFile($local, (string)$file['original_filename']);
        $added++;
    }
    if (!$added) throw new RuntimeException('None of the selected assets has a PDF.');
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="selected-assets-' . date('Ymd-His') . '.zip"');
    header('Content-Length: ' . filesize($zipPath));
    header('X-Content-Type-Options: nosniff');
    readfile($zipPath);
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        echo h($e->getMessage());
    }
    error_log($e->getMessage());
} finally {
    foreach ($temporary as $path) if (is_file($path)) unlink($path);
}
