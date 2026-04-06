<?php
$host = "mariadb";
$dbname = "myapp";
$user = "zack";
$pass = "Butcher69";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fmt($value, $decimals = 0) {
    if ($value === null || $value === '') return '-';
    if (!is_numeric($value)) return h($value);
    return number_format((float)$value, $decimals, '.', '');
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $latestNozzle = $pdo->query("SELECT * FROM nozzle_logs ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $latestTricanter = $pdo->query("SELECT * FROM tricanter_logs ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    $nozzle = $pdo->query("SELECT * FROM nozzle_logs ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    $tricanter = $pdo->query("SELECT * FROM tricanter_logs ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    die("DB Error: " . h($e->getMessage()));
}
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="30">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body {background:#0b1e2d;color:#fff;font-family:Arial;margin:0;padding:15px;}
h1{text-align:center;margin-bottom:15px;}

.grid{display:grid;grid-template-columns:1fr 1fr;gap:15px;}
.panel{background:#122c44;padding:10px;border-radius:10px;}

.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:10px;}
.kpi{background:#163a59;padding:8px;border-radius:6px;text-align:center;}
.kpi small{color:#aaa;display:block;}
.kpi b{font-size:18px;}

.chart{height:200px;}

.table{max-height:300px;overflow:auto;}
table{width:100%;border-collapse:collapse;}
th,td{padding:6px;font-size:11px;border-bottom:1px solid #1f4a6e;}
th{background:#1f4a6e;position:sticky;top:0;}

.flash{animation:flash 2s 3;}
@keyframes flash{
0%{background:yellow;color:black;}
100%{background:inherit;color:inherit;}
}

@media(max-width:900px){
.grid{grid-template-columns:1fr;}
}
</style>
</head>

<body>

<h1>SCADA Dashboard</h1>

<div class="grid">

<!-- NOZZLE -->
<div class="panel">
<h2>Nozzle</h2>

<div class="kpis">
<div class="kpi"><small>Flow</small><b><?=fmt($latestNozzle['flow']??null,1)?></b></div>
<div class="kpi"><small>Pressure</small><b><?=fmt($latestNozzle['pressure']??null,2)?></b></div>
<div class="kpi"><small>RPM</small><b><?=fmt($latestNozzle['rpm']??null,1)?></b></div>
<div class="kpi"><small>Min Deg</small><b><?=fmt($latestNozzle['min_deg']??null,0)?></b></div>
<div class="kpi"><small>Max Deg</small><b><?=fmt($latestNozzle['max_deg']??null,0)?></b></div>
</div>

<canvas id="nozzleChart" class="chart"></canvas>

<div class="table">
<table>
<tr>
<th>ID</th><th>Date</th><th>Time</th><th>Nozzle</th><th>Flow</th><th>Pressure</th><th>Min</th><th>Max</th><th>RPM</th>
</tr>
<?php foreach($nozzle as $r): ?>
<tr class="nozzle-row" data-id="<?=$r['id']?>">
<td><?=$r['id']?></td>
<td><?=$r['log_date']?></td>
<td><?=$r['log_time']?></td>
<td><?=$r['nozzle']?></td>
<td><?=fmt($r['flow'],1)?></td>
<td><?=fmt($r['pressure'],2)?></td>
<td><?=fmt($r['min_deg'],0)?></td>
<td><?=fmt($r['max_deg'],0)?></td>
<td><?=fmt($r['rpm'],1)?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
</div>

<!-- TRICANTER -->
<div class="panel">
<h2>Tricanter</h2>

<div class="kpis">
<div class="kpi"><small>Feed</small><b><?=fmt($latestTricanter['feed_rate']??null,2)?></b></div>
<div class="kpi"><small>Torque</small><b><?=fmt($latestTricanter['torque']??null,1)?></b></div>
<div class="kpi"><small>Temp</small><b><?=fmt($latestTricanter['temp']??null,1)?></b></div>
<div class="kpi"><small>Pressure</small><b><?=fmt($latestTricanter['pressure']??null,3)?></b></div>
<div class="kpi"><small>Bowl RPM</small><b><?=fmt($latestTricanter['bowl_rpm']??null,0)?></b></div>
<div class="kpi"><small>Screw RPM</small><b><?=fmt($latestTricanter['screw_rpm']??null,2)?></b></div>
</div>

<canvas id="triChart" class="chart"></canvas>

<div class="table">
<table>
<tr>
<th>ID</th><th>Date</th><th>Time</th><th>Feed</th><th>Torque</th><th>Temp</th><th>Pressure</th>
</tr>
<?php foreach($tricanter as $r): ?>
<tr class="tri-row" data-id="<?=$r['id']?>">
<td><?=$r['id']?></td>
<td><?=$r['log_date']?></td>
<td><?=$r['log_time']?></td>
<td><?=fmt($r['feed_rate'],2)?></td>
<td><?=fmt($r['torque'],1)?></td>
<td><?=fmt($r['temp'],1)?></td>
<td><?=fmt($r['pressure'],3)?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
</div>

</div>

<script>

// NEW ROW FLASH
function flashRows(cls,key){
let last=localStorage.getItem(key)||0;
let max=last;

document.querySelectorAll(cls).forEach(r=>{
let id=parseInt(r.dataset.id);
if(id>last){r.classList.add('flash');}
if(id>max)max=id;
});

localStorage.setItem(key,max);
}

flashRows('.nozzle-row','nLast');
flashRows('.tri-row','tLast');

// CHARTS (auto-scale per dataset)
function chart(id,data,color){
new Chart(document.getElementById(id),{
type:'line',
data:{labels:data.map((_,i)=>i),datasets:[{data:data,borderColor:color,tension:.3}]},
options:{
responsive:true,
maintainAspectRatio:false,
scales:{x:{display:false},y:{display:false}}
}
});
}

chart('nozzleChart', <?=json_encode(array_column($nozzle,'flow'))?>, '#00ffff');
chart('triChart', <?=json_encode(array_column($tricanter,'feed_rate'))?>, '#00ff88');

</script>

</body>
</html>