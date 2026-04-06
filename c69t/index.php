<?php
session_start();
date_default_timezone_set('Australia/Adelaide');

/* =========================
   AUTH
   ========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['admin','operator','viewer'], true)) {
    http_response_code(403);
    die('Access denied.');
}

/* =========================
   DB
   ========================= */
$pdo = new PDO(
    "mysql:host=mariadb;dbname=myapp;charset=utf8mb4",
    "zack",
    "Butcher69",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

/* =========================
   HELPERS
   ========================= */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES); }
function fmt($v,$d=0){ return ($v===''||$v===null)?'-':number_format((float)$v,$d); }

function getShift(){
    $now=time(); $h=date('G');
    if($h>=6&&$h<18){
        return [date('Y-m-d 06:00'),date('Y-m-d 18:00')];
    }elseif($h>=18){
        return [date('Y-m-d 18:00'),date('Y-m-d 06:00',strtotime('+1 day'))];
    }else{
        return [date('Y-m-d 18:00',strtotime('-1 day')),date('Y-m-d 06:00')];
    }
}

/* =========================
   RANGE
   ========================= */
$rangeStart=$_GET['start']??'';
$rangeEnd=$_GET['end']??'';

if(!$rangeStart && !$rangeEnd){
    [$rangeStart,$rangeEnd]=getShift();
}

$start=date('Y-m-d H:i:s',strtotime($rangeStart));
$end=date('Y-m-d H:i:s',strtotime($rangeEnd));

/* =========================
   DATA
   ========================= */
$q="WHERE TIMESTAMP(log_date,log_time) BETWEEN :s AND :e";

$stmt=$pdo->prepare("SELECT * FROM nozzle_logs $q ORDER BY id DESC");
$stmt->execute([':s'=>$start,':e'=>$end]);
$nozzle=$stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt=$pdo->prepare("SELECT * FROM tricanter_logs $q ORDER BY id DESC");
$stmt->execute([':s'=>$start,':e'=>$end]);
$tricanter=$stmt->fetchAll(PDO::FETCH_ASSOC);

/* latest overall */
$latestNozzle=$pdo->query("SELECT * FROM nozzle_logs ORDER BY id DESC LIMIT 1")->fetch();
$latestTri=$pdo->query("SELECT * FROM tricanter_logs ORDER BY id DESC LIMIT 1")->fetch();

$lastNozzleStamp=($latestNozzle['log_date']??'-').' '.($latestNozzle['log_time']??'');
$lastTriStamp=($latestTri['log_date']??'-').' '.($latestTri['log_time']??'');

$status=($latestNozzle||$latestTri)?'ONLINE':'NO DATA';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>SCADA Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{background:#0b1e2d;color:#fff;font-family:Arial;margin:0;padding:15px;}
h1{text-align:center;margin-bottom:12px;}

.topbar{
display:grid;
grid-template-columns:1.3fr 0.8fr 2.2fr;
gap:10px;
margin-bottom:12px;
}

.info-card{
background:#122c44;
padding:8px 10px;
border-radius:10px;
}

.info-title{font-size:10px;color:#9ec3df;margin-bottom:4px;}
.info-value{font-size:20px;font-weight:bold;}
.info-sub{font-size:11px;color:#b7ccdd;}

.status-row{
display:flex;
justify-content:space-between;
align-items:center;
}

.last-entry{
text-align:right;
font-size:11px;
}

.last-entry b{
display:block;
color:#9ec3df;
font-size:10px;
}

/* RANGE */
.range-layout{
display:grid;
grid-template-columns:1fr auto;
gap:10px;
align-items:center;
}

.range-inputs{
display:flex;
flex-direction:column;
gap:6px;
}

.range-row{
display:flex;
align-items:center;
gap:6px;
}

.range-row label{
width:35px;
font-size:11px;
color:#b7ccdd;
}

input[type=datetime-local]{
width:170px;
padding:6px;
background:#0d2234;
border:1px solid #2a5377;
color:#fff;
border-radius:6px;
font-size:12px;
}

.range-buttons{
display:flex;
flex-direction:column;
align-items:flex-end;
gap:6px;
}

.btn{
background:#1f4a6e;
padding:7px 10px;
border-radius:6px;
border:0;
color:#fff;
cursor:pointer;
font-size:11px;
}

.btn:hover{background:#295d89;}

.quick-actions{
display:flex;
gap:5px;
flex-wrap:wrap;
justify-content:flex-end;
}

.panel{background:#122c44;padding:10px;border-radius:10px;margin-bottom:10px;}
</style>
</head>

<body>
<?php require_once "nav.php"; ?>
<h1>SCADA Dashboard</h1>

<div class="topbar">

<div class="info-card">
<div class="info-title">System Status</div>

<div class="status-row">
<div class="info-value"><?=h($status)?></div>

<div class="last-entry">
<b>Last Entry</b>
Nozzle: <?=h($lastNozzleStamp)?><br>
Tri: <?=h($lastTriStamp)?>
</div>
</div>

<div class="info-sub">Auto refresh 30s</div>
</div>

<div class="info-card">
<div class="info-title">Records</div>
<div class="info-value"><?=count($nozzle)+count($tricanter)?></div>
</div>

<div class="info-card">
<div class="info-title">Date / Time Range</div>

<form method="get">
<div class="range-layout">

<div class="range-inputs">

<div class="range-row">
<label>From</label>
<input type="datetime-local" name="start" value="<?=date('Y-m-d\TH:i',strtotime($rangeStart))?>">
</div>

<div class="range-row">
<label>To</label>
<input type="datetime-local" name="end" value="<?=date('Y-m-d\TH:i',strtotime($rangeEnd))?>">
</div>

</div>

<div class="range-buttons">

<div>
<button class="btn">Apply</button>
<a href="" class="btn">Clear</a>
</div>

<div class="quick-actions">
<button name="quick" value="current_shift" class="btn">Shift</button>
<button name="quick" value="today" class="btn">Today</button>
<button name="quick" value="24h" class="btn">24h</button>
<button name="quick" value="7d" class="btn">7d</button>
</div>

</div>

</div>
</form>

</div>

</div>

</body>
</html>