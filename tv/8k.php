<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
*/
$m3uSource = 'http://factor80479.wd.business-cdn-8k.su/get.php?username=tpowell&password=ed491c02cb&type=m3u_plus&output=ts';

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function loadTextFile($source) {
    if (filter_var($source, FILTER_VALIDATE_URL)) {
        return @file_get_contents($source) ?: '';
    } else {
        return file_exists($source) ? @file_get_contents($source) : '';
    }
}

function parseAttributes($text) {
    $attrs = [];
    preg_match_all('/([a-zA-Z0-9\-_]+)="([^"]*)"/', $text, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $attrs[$match[1]] = $match[2];
    }
    return $attrs;
}

function parseM3U($content) {
    $channels = [];
    $lines = preg_split('/\r\n|\r|\n/', $content);

    for ($i = 0; $i < count($lines); $i++) {
        $line = trim($lines[$i]);

        if (stripos($line, '#EXTINF:') === 0) {
            $attrs = parseAttributes($line);

            $name = '';
            $commaPos = strrpos($line, ',');
            if ($commaPos !== false) {
                $name = trim(substr($line, $commaPos + 1));
            }

            $url = '';
            for ($j = $i + 1; $j < count($lines); $j++) {
                $next = trim($lines[$j]);
                if ($next === '' || strpos($next, '#') === 0) continue;

                $url = $next;
                $i = $j;
                break;
            }

            if ($url !== '') {
                $channels[] = [
                    'name' => $name ?: 'Unnamed',
                    'url' => $url,
                    'logo' => $attrs['tvg-logo'] ?? '',
                    'group' => $attrs['group-title'] ?? 'Other'
                ];
            }
        }
    }

    return $channels;
}

/*
|--------------------------------------------------------------------------
| LOAD CHANNELS
|--------------------------------------------------------------------------
*/
$m3uContent = loadTextFile($m3uSource);

if ($m3uContent === '') {
    die("Playlist failed to load.");
}

$channels = parseM3U($m3uContent);

/*
|--------------------------------------------------------------------------
| SELECT CHANNEL
|--------------------------------------------------------------------------
*/
$selectedIndex = isset($_GET['ch']) ? (int)$_GET['ch'] : 0;
$selectedChannel = $channels[$selectedIndex] ?? $channels[0];

/*
|--------------------------------------------------------------------------
| GROUP CHANNELS
|--------------------------------------------------------------------------
*/
$grouped = [];
foreach ($channels as $i => $c) {
    $group = $c['group'] ?: 'Other';
    $grouped[$group][] = ['index'=>$i,'channel'=>$c];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>TV Player</title>
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>

<style>
*{box-sizing:border-box;}

html,body{
    margin:0;
    height:100%;
    overflow:hidden;
    font-family:Arial;
    background:#0f1117;
    color:#fff;
}

.app{
    display:flex;
    height:100vh;
}

/* SIDEBAR */
.sidebar{
    width:360px;
    display:flex;
    flex-direction:column;
    background:#181c24;
    border-right:1px solid #2a2f3a;
}

.sidebar-header{
    padding:16px;
    border-bottom:1px solid #2a2f3a;
}

.search input{
    width:100%;
    padding:10px;
    background:#10141b;
    border:1px solid #333;
    color:#fff;
}

/* SCROLL AREA */
.channel-list{
    flex:1;
    overflow-y:auto;
    padding:10px;
}

/* CHANNEL */
.channel{
    display:flex;
    gap:10px;
    padding:10px;
    margin-bottom:6px;
    background:#222;
    border-radius:10px;
    cursor:pointer;
}

.channel.active{
    border:1px solid #4ea1ff;
}

.channel img{
    width:40px;
    height:40px;
    border-radius:6px;
}

/* MAIN */
.main{
    flex:1;
    padding:20px;
    overflow:hidden;
}

video{
    width:100%;
    max-height:75vh;
    background:#000;
}

.group-title{
    font-size:12px;
    color:#aaa;
    margin:10px 0 6px;
}
</style>
</head>

<body>

<div class="app">

<div class="sidebar">

<div class="sidebar-header">
<h2>Channels</h2>
<div class="search">
<input id="search" placeholder="Search...">
</div>
</div>

<div class="channel-list" id="list">

<?php foreach($grouped as $group=>$items): ?>
<div class="group-title"><?php echo htmlspecialchars($group); ?></div>

<?php foreach($items as $item): 
$c=$item['channel']; ?>
<div class="channel <?php echo $item['index']==0?'active':'';?>"
data-url="<?php echo htmlspecialchars($c['url']); ?>"
data-name="<?php echo htmlspecialchars($c['name']); ?>">

<img src="<?php echo $c['logo'] ?: 'https://via.placeholder.com/40'; ?>">

<div><?php echo htmlspecialchars($c['name']); ?></div>

</div>
<?php endforeach; ?>

<?php endforeach; ?>

</div>
</div>

<div class="main">
<h2 id="title"><?php echo htmlspecialchars($selectedChannel['name']); ?></h2>
<video id="video" controls autoplay></video>
</div>

</div>

<script>
let video = document.getElementById("video");
let hls;

function load(url,name){
    if(hls) hls.destroy();

    document.getElementById("title").innerText = name;

    if(Hls.isSupported()){
        hls = new Hls();
        hls.loadSource(url);
        hls.attachMedia(video);
    } else {
        video.src = url;
    }
}

/* CLICK CHANNEL */
document.querySelectorAll(".channel").forEach(el=>{
    el.onclick=()=>{
        document.querySelectorAll(".channel").forEach(c=>c.classList.remove("active"));
        el.classList.add("active");
        load(el.dataset.url, el.dataset.name);
    };
});

/* SEARCH */
document.getElementById("search").oninput=function(){
    let t=this.value.toLowerCase();

    document.querySelectorAll(".channel").forEach(el=>{
        el.style.display = el.dataset.name.toLowerCase().includes(t) ? "flex":"none";
    });
};

/* LOAD FIRST */
load(
"<?php echo $selectedChannel['url']; ?>",
"<?php echo $selectedChannel['name']; ?>"
);
</script>

</body>
</html>