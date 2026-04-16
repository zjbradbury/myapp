<?php
require_once "config.php";
requireRole(['admin','operator','viewer']);

$canEdit = in_array(currentRole(), ['admin','operator'], true);
$monitorData = buildMonitoringData($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard</title>

<link rel="stylesheet" href="indexStyle.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<?php require_once "nav.php"; ?>

<div class="logo-row">
    <img src="MoombaTankCleaningLogoTransparent.PNG">
    <img src="Contract69TanksLogoTransparent.png">
</div>

<!-- KEEP YOUR MONITOR BLOCK EXACTLY AS IS -->
<?php /* keep your existing monitor-shell block here unchanged */ ?>

<div class="grid">

<!-- ================= TRICANTER ================= -->
<div class="panel">
<h2>Tricanter</h2>

<div class="chart-wrap">
<canvas id="tricanterCombinedChart"></canvas>
</div>

<div class="table">
<table id="tricanterTable">
<thead>
<tr>
<th>Date</th><th>Time</th><th>Bowl</th><th>Screw</th><th>RPM</th>
</tr>
</thead>
<tbody></tbody>
</table>
</div>
</div>

<!-- ================= SOLID ================= -->
<div class="panel">
<h2>Solid Waste</h2>

<div class="chart-wrap">
<canvas id="solidWasteCombinedChart"></canvas>
</div>

<div class="table">
<table id="solidTable">
<thead>
<tr>
<th>Date</th><th>Time</th><th>Amount</th><th>Diff</th>
</tr>
</thead>
<tbody></tbody>
</table>
</div>
</div>

<!-- ================= NOZZLE ================= -->
<div class="panel">
<h2>Nozzle</h2>

<div class="chart-wrap">
<canvas id="nozzleCombinedChart"></canvas>
</div>

<div class="table">
<table id="nozzleTable">
<thead>
<tr>
<th>Date</th><th>Time</th><th>Nozzle</th><th>Flow</th><th>Pressure</th>
</tr>
</thead>
<tbody></tbody>
</table>
</div>
</div>

<!-- ================= GAS ================= -->
<div class="panel">
<h2>Gas Test</h2>

<div class="chart-wrap">
<canvas id="gasTestCombinedChart"></canvas>
</div>

<div class="table">
<table id="gasTable">
<thead>
<tr>
<th>Date</th><th>Time</th><th>Device</th><th>Operator</th>
</tr>
</thead>
<tbody></tbody>
</table>
</div>
</div>

<!-- ================= PROJECT FLOW ================= -->
<div class="panel">
<h2>Project Flow</h2>

<div class="kpis">
<b data-kpi="pf_oil">-</b>
<b data-kpi="pf_water">-</b>
<b data-kpi="pf_solid">-</b>
</div>

<div class="table">
<table id="projectFlowTable">
<thead>
<tr>
<th>Date</th><th>Time</th><th>Oil</th><th>Water</th>
</tr>
</thead>
<tbody></tbody>
</table>
</div>
</div>

</div>

<script>

/* =======================
   CHARTS
======================= */
let charts = {};

function initCharts(){

charts.nozzle = new Chart(nozzleCombinedChart,{
type:'line',
data:{labels:[],datasets:[
{label:'Flow',data:[]},
{label:'Pressure',data:[]}
]},
options:{animation:false}
});

charts.tricanter = new Chart(tricanterCombinedChart,{
type:'line',
data:{labels:[],datasets:[
{label:'Bowl',data:[]},
{label:'Screw',data:[]}
]},
options:{animation:false}
});

charts.solid = new Chart(solidWasteCombinedChart,{
type:'line',
data:{labels:[],datasets:[
{label:'Amount',data:[]},
{label:'Diff',data:[]}
]}
});

charts.gas = new Chart(gasTestCombinedChart,{
type:'line',
data:{labels:[],datasets:[
{label:'H2S',data:[]},
{label:'LEL',data:[]}
]}
});
}

/* =======================
   UPDATE CHARTS
======================= */
function updateCharts(d){

charts.nozzle.data.labels=d.nozzle.labels;
charts.nozzle.data.datasets[0].data=d.nozzle.flow;
charts.nozzle.data.datasets[1].data=d.nozzle.pressure;
charts.nozzle.update('none');

charts.tricanter.data.labels=d.tricanter.labels;
charts.tricanter.data.datasets[0].data=d.tricanter.bowl;
charts.tricanter.data.datasets[1].data=d.tricanter.screw;
charts.tricanter.update('none');

charts.solid.data.labels=d.solid.labels;
charts.solid.data.datasets[0].data=d.solid.amount;
charts.solid.data.datasets[1].data=d.solid.diff;
charts.solid.update('none');

charts.gas.data.labels=d.gas.labels;
charts.gas.data.datasets[0].data=d.gas.h2s;
charts.gas.data.datasets[1].data=d.gas.lel;
charts.gas.update('none');
}

/* =======================
   TABLES
======================= */
function updateTable(id,rows,cols){

const tbody=document.querySelector(`#${id} tbody`);
if(!tbody) return;

tbody.innerHTML='';

rows.forEach(r=>{
let tr='<tr>';
cols.forEach(c=>{
tr+=`<td>${r[c]??'-'}</td>`;
});
tr+='</tr>';
tbody.innerHTML+=tr;
});
}

/* =======================
   KPI
======================= */
function updateKpis(k){
document.querySelector('[data-kpi="pf_oil"]').innerText=k.project.oil ?? '-';
document.querySelector('[data-kpi="pf_water"]').innerText=k.project.water ?? '-';
document.querySelector('[data-kpi="pf_solid"]').innerText=k.project.solid_waste ?? '-';
}

/* =======================
   MAIN LOOP
======================= */
function refreshAll(){

fetch('dashboard_data.php')
.then(r=>r.json())
.then(d=>{

updateCharts(d.charts);
updateKpis(d.kpis);

updateTable('tricanterTable',d.tables.tricanter,
['log_date','log_time','bowl_speed','screw_speed','bowl_rpm']);

updateTable('solidTable',d.tables.solid,
['log_date','log_time','amount','_diff_minutes']);

updateTable('nozzleTable',d.tables.nozzle,
['log_date','log_time','nozzle','flow','pressure']);

updateTable('gasTable',d.tables.gas,
['log_date','log_time','device','operator']);

updateTable('projectFlowTable',d.tables.project,
['log_date','log_time','total_recovered_oil','total_recovered_water']);

});
}

/* =======================
   START
======================= */
initCharts();
refreshAll();

setInterval(refreshAll,30000);
setInterval(updateMonitorTimers,1000);

</script>

</body>
</html>