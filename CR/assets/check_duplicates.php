<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
function duplicateError(string $message,int $status):never{http_response_code($status);echo json_encode(['error'=>$message]);exit;}
if(!isLoggedIn())duplicateError('Login expired.',401);
$access=$pdo->prepare('SELECT role2 FROM users WHERE id=?');$access->execute([(int)$_SESSION['user_id']]);if($access->fetchColumn()!==ASSET_ROLE)duplicateError('Access denied.',403);
if(!hash_equals((string)($_SESSION['asset_csrf']??''),(string)($_POST['csrf_token']??'')))duplicateError('Form expired.',419);
$pairs=json_decode((string)($_POST['pairs']??'[]'),true);if(!is_array($pairs))$pairs=[];$query=$pdo->prepare("SELECT 1 FROM assets a JOIN asset_files f ON f.asset_id=a.id WHERE a.asset_number=? AND f.test_date=? AND LOWER(f.original_filename) LIKE '%.pdf' LIMIT 1");$duplicates=[];foreach($pairs as $pair){$number=strtoupper(trim((string)($pair['asset_number']??'')));$date=trim((string)($pair['test_date']??''));if($number===''||$date==='')continue;$query->execute([$number,$date]);$duplicates[$number.'|'.$date]=(bool)$query->fetchColumn();}echo json_encode(['duplicates'=>$duplicates]);
