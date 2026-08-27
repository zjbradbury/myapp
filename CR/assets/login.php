<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
if(isLoggedIn()){$check=$pdo->prepare('SELECT role,role2 FROM users WHERE id=?');$check->execute([(int)$_SESSION['user_id']]);$access=$check->fetch(PDO::FETCH_ASSOC);if(($access['role2']??'')===ASSET_ROLE){header('Location: index.php');exit;}if(($access['role']??'')==='admin'){header('Location: users.php');exit;}}
$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verifyCsrf();$username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');
 if($username===''||$password==='')$message='Username and password are required.';else{
  $stmt=$pdo->prepare('SELECT id,username,password,role,role2 FROM users WHERE username=? LIMIT 1');$stmt->execute([$username]);$user=$stmt->fetch(PDO::FETCH_ASSOC);
  if(!$user||!password_verify($password,(string)$user['password']))$message='Invalid username or password.';
  elseif(!hash_equals(ASSET_ROLE,(string)($user['role2']??''))&&!hash_equals('admin',(string)($user['role']??'')))$message='Your account does not have access to Asset Management.';
  else{session_regenerate_id(true);$_SESSION['user_id']=$user['id'];$_SESSION['username']=$user['username'];$_SESSION['role']=$user['role'];$_SESSION['role2']=$user['role2'];header('Location: '.(($user['role2']??'')===ASSET_ROLE?'index.php':'users.php'));exit;}
 }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Asset Management Login</title><link rel="stylesheet" href="style.css"></head><body class="auth-page"><main class="auth-wrap"><section class="card auth-card"><span class="eyebrow dark">CR operations</span><h1>Asset management</h1><p>Sign in with your company account.</p><?php if($message!==''):?><div class="message error"><?=h($message)?></div><?php endif?><form method="post" class="login-form"><input type="hidden" name="csrf_token" value="<?=h(csrfToken())?>"><label>Username<input name="username" autocomplete="username" autofocus required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button>Sign in</button></form></section></main></body></html>
