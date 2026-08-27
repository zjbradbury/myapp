<?php
declare(strict_types=1);
require_once __DIR__.'/config.php'; require_once __DIR__.'/NextcloudClient.php';
$currentUser=requireAssetAdmin($pdo); $message=''; $messageType='error';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verifyCsrf(); $action=(string)($_POST['action'] ?? '');
 try {
  if ($action==='create') {
   $number=trim((string)($_POST['asset_number']??'')); $category=trim((string)($_POST['asset_category']??'')); $description=trim((string)($_POST['asset_description']??'')); $testDate=trim((string)($_POST['asset_test_date']??''));
   $span=filter_var($_POST['asset_retest_span']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>36500]]); $file=$_FILES['asset_file']??null; $date=DateTimeImmutable::createFromFormat('!Y-m-d',$testDate);
   if ($number===''||$category===''||$description===''||!$date||$date->format('Y-m-d')!==$testDate||$span===false) throw new RuntimeException('Complete all fields with a valid date and retest span.');
   if (!$file||(int)$file['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Select a file to upload. Upload error code: '.(int)($file['error']??-1));
   if ((int)$file['size']<=0||(int)$file['size']>MAX_UPLOAD_BYTES) throw new RuntimeException('The attachment must be between 1 byte and 25 MB.');
   $cloud=new NextcloudClient(); $remote=$cloud->upload((string)$file['tmp_name'],(string)$file['name'],$number);
   try { $stmt=$pdo->prepare('INSERT INTO assets (asset_number,asset_category,asset_description,asset_test_date,asset_retest_span,file_location,original_filename,uploaded_by) VALUES (?,?,?,?,?,?,?,?)'); $stmt->execute([$number,$category,$description,$testDate,$span,$remote,basename((string)$file['name']),$currentUser['id']]); }
   catch(Throwable $dbError) { $cloud->delete($remote); throw $dbError; }
   header('Location: index.php?created=1'); exit;
  }
  if ($action==='delete') {
   $id=filter_var($_POST['asset_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $stmt=$pdo->prepare('SELECT file_location FROM assets WHERE id=?'); $stmt->execute([$id]); $asset=$stmt->fetch(PDO::FETCH_ASSOC);
   if(!$asset) throw new RuntimeException('Asset not found.'); (new NextcloudClient())->delete((string)$asset['file_location']); $pdo->prepare('DELETE FROM assets WHERE id=?')->execute([$id]); header('Location: index.php?deleted=1'); exit;
  }
  throw new RuntimeException('Unknown action.');
 } catch(Throwable $e) { $message=$e instanceof PDOException&&$e->getCode()==='23000'?'That asset number already exists.':$e->getMessage(); }
}
if(isset($_GET['created'])){$message='Asset uploaded successfully.';$messageType='success';} if(isset($_GET['deleted'])){$message='Asset and attachment deleted.';$messageType='success';}
$search=trim((string)($_GET['q']??'')); $sql="SELECT a.*,COALESCE(u.username, 'Deleted user') AS username,DATE_ADD(a.asset_test_date,INTERVAL a.asset_retest_span DAY) next_test_date FROM assets a LEFT JOIN users u ON u.id=a.uploaded_by";
if($search!==''){ $stmt=$pdo->prepare($sql.' WHERE a.asset_number LIKE ? OR a.asset_category LIKE ? OR a.asset_description LIKE ? ORDER BY a.uploaded_at DESC');$like='%'.$search.'%';$stmt->execute([$like,$like,$like]); } else {$stmt=$pdo->query($sql.' ORDER BY a.uploaded_at DESC');} $assets=$stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Asset Management</title><link rel="stylesheet" href="style.css"></head>
<body><header><div><span class="eyebrow">CR operations</span><h1>Asset management</h1></div><nav class="user"><?=h($currentUser['username'])?> · <a href="users.php">Users</a> · <a href="logout.php">Log out</a></nav></header><main>
<?php if($message!==''):?><div class="message <?=h($messageType)?>"><?=h($message)?></div><?php endif?>
<section class="card"><h2>Add asset</h2><form method="post" enctype="multipart/form-data" class="asset-form"><input type="hidden" name="csrf_token" value="<?=h(csrfToken())?>"><input type="hidden" name="action" value="create">
<label>Asset number<input name="asset_number" maxlength="100" required></label><label>Category<input name="asset_category" maxlength="100" required></label><label class="wide">Description<textarea name="asset_description" rows="3" required></textarea></label><label>Test date<input type="date" name="asset_test_date" required></label><label>Retest span (days)<input type="number" name="asset_retest_span" min="1" max="36500" required></label><label class="wide">Attachment (maximum 25 MB)<input type="file" name="asset_file" required></label><div class="wide"><button>Upload asset</button></div></form></section>
<section class="card"><div class="table-heading"><h2>Assets</h2><form method="get" class="search"><input name="q" value="<?=h($search)?>" placeholder="Search assets"><button>Search</button><?php if($search!==''):?><a href="index.php">Clear</a><?php endif?></form></div><div class="table-wrap"><table><thead><tr><th>Uploaded</th><th>Asset #</th><th>Category</th><th>Description</th><th>Test date</th><th>Retest</th><th>Next test</th><th>File</th><th>By</th><th></th></tr></thead><tbody>
<?php if(!$assets):?><tr><td colspan="10" class="empty">No assets found.</td></tr><?php endif?><?php foreach($assets as $asset):?><tr><td><?=h($asset['uploaded_at'])?></td><td><strong><?=h($asset['asset_number'])?></strong></td><td><?=h($asset['asset_category'])?></td><td><?=nl2br(h($asset['asset_description']))?></td><td><?=h($asset['asset_test_date'])?></td><td><?=h($asset['asset_retest_span'])?> days</td><td class="<?=strtotime((string)$asset['next_test_date'])<strtotime(date('Y-m-d'))?'overdue':''?>"><?=h($asset['next_test_date'])?></td><td><a href="download.php?id=<?=h($asset['id'])?>"><?=h($asset['original_filename'])?></a></td><td><?=h($asset['username'])?></td><td><form method="post" onsubmit="return confirm('Delete this asset and its Nextcloud file?')"><input type="hidden" name="csrf_token" value="<?=h(csrfToken())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="asset_id" value="<?=h($asset['id'])?>"><button class="danger">Delete</button></form></td></tr><?php endforeach?></tbody></table></div></section>
</main></body></html>
