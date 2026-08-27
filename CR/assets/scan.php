<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';require_once __DIR__.'/PdfScanner.php';requireAssetAdmin($pdo);
header('Content-Type: application/json');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['error'=>'POST required']);exit;}
verifyCsrf();$files=$_FILES['asset_files']??null;$prefix=trim((string)($_POST['prefix']??''));$_SESSION['asset_scan_prefix']=$prefix;
if(!$files||!is_array($files['name']??null)){http_response_code(422);echo json_encode(['error'=>'Select one or more PDF files first.']);exit;}
$result=['asset_number'=>'','test_date'=>'','pdfs_scanned'=>0,'text_found'=>false];
foreach($files['name'] as $i=>$name){if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||strtolower(pathinfo((string)$name,PATHINFO_EXTENSION))!=='pdf')continue;$scan=PdfScanner::scan((string)$files['tmp_name'][$i],$prefix);$result['pdfs_scanned']++;$result['text_found']=$result['text_found']||$scan['text_found'];if($result['asset_number']==='')$result['asset_number']=$scan['asset_number'];if($result['test_date']==='')$result['test_date']=$scan['test_date'];}
if($result['pdfs_scanned']===0){http_response_code(422);echo json_encode(['error'=>'No PDF files were selected for scanning.']);exit;}echo json_encode($result);
