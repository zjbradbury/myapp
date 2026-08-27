<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/NextcloudClient.php';
requireAssetAdmin($pdo);
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$stmt = $pdo->prepare('SELECT file_location,original_filename FROM asset_files WHERE id=?');
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$file) {
    http_response_code(404);
    exit('Asset file not found.');
}
$filename = str_replace(["\r", "\n", '"'], '', basename((string)$file['original_filename']));
header('Content-Type: application/octet-stream');
header("Content-Disposition: attachment; filename=\"{$filename}\"; filename*=UTF-8''" . rawurlencode($filename));
header('X-Content-Type-Options: nosniff');
try {
    (new NextcloudClient())->stream((string)$file['file_location']);
} catch (Throwable $e) {
    error_log($e->getMessage());
}
