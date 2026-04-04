<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
*/
$m3uSource = 'http://cf.business-cdn-8k.su/get.php?username=tpowell&password=ed491c02cb&type=m3u_plus';

/*
|--------------------------------------------------------------------------
| LOAD FILE SAFE
|--------------------------------------------------------------------------
*/
function loadTextFile($source) {
    if (!is_string($source) || trim($source) === '') return '';

    if (filter_var($source, FILTER_VALIDATE_URL)) {
        return @file_get_contents($source) ?: '';
    }

    if (!file_exists($source)) return '';

    return @file_get_contents($source) ?: '';
}

/*
|--------------------------------------------------------------------------
| PARSE M3U
|--------------------------------------------------------------------------
*/
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
                $nextLine = trim($lines[$j]);
                if ($nextLine === '' || strpos($nextLine, '#') === 0) continue;

                $url = $nextLine;
                $i = $j;
                break;
            }

            if ($url !== '') {
                $channels[] = [
                    'id' => md5($name . $url),
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
    die("Playlist not found or empty.");
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
| GET GROUPS
|--------------------------------------------------------------------------
*/
$groups = [];
foreach ($channels as $c) {
    $groups[$c['group']] = true;
}
$groupNames = array_keys($groups);
sort($groupNames);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>IPTV Player</title>
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>

<style>
body { margin:0; font-family:Arial; background:#0f1117; color:#fff; }
.app { display:flex; height:100vh; }

.sidebar {
    width:360px;
    background:#181c24;
    display:flex;
    flex-direction:column;
}

.search { padding:10px; }
.search input {
    width:100%;
    padding:10px;
    background:#10141b;
    border:1px solid #333;
    color:#fff;
}

.tabs {
    padding:10px;
    display:flex;
    flex-wrap:wrap;
    gap:6px;
}

.tab {
    padding:6px 10px;
    background:#222;
    cursor:pointer;
    border-radius:20px;
}

.tab.active {
    background:#4ea1ff;
    color:#000;
}

.list { overflow:auto; flex:1; padding:10px; }

.channel {
    display:flex;
    gap:10px;
    padding:10px;
    margin-bottom:6px;
    background:#222;
    border-radius:10px;
    cursor:pointer;
}

.channel.active { border:1px solid #4ea1ff; }

.channel img {
    width:40px;
    height:40px;
    border-radius:6px;
}

.fav {
    margin-left:auto;
    cursor:pointer;
    font-size:18px;
}

.main { flex:1; padding:20px; }

video {
    width:100%;
    max-height:70vh;
    background:#000;
}
</style>
</head>

<body>

<div class="app">

<div class="sidebar">

<div class="search">
<input type="text" id="search" placeholder="Search...">
</div>

<div class="tabs" id="tabs">
<div class="tab active" data-cat="all">All</div>
<div class="tab" data-cat="fav">★ Fav</div>
<?php foreach($groupNames as $g): ?>
<div class="tab" data-cat="<?php echo htmlspecialchars($g); ?>">
<?php echo htmlspecialchars($g); ?>
</div>
<?php endforeach; ?>
</div>

<div class="list" id="list">
<?php foreach($channels as $i=>$c): ?>
<div class="channel <?php echo $i==0?'active':'';?>"
data-index="<?php echo $i;?>"
data-id="<?php echo $c['id'];?>"
data-cat="<?php echo htmlspecialchars($c['group']);?>"
data-url="<?php echo htmlspecialchars($c['url']);?>"
data-name="<?php echo htmlspecialchars($c['name']);?>">

<img src="<?php echo $c['logo'] ?: 'https://via.placeholder.com/40'; ?>">

<div><?php echo htmlspecialchars($c['name']);?></div>

<div class="fav" onclick="toggleFav(event,'<?php echo $c['id'];?>')">☆</div>

</div>
<?php endforeach;?>
</div>

</div>

<div class="main">
<h2 id="title"><?php echo $selectedChannel['name'];?></h2>
<video id="video" controls autoplay></video>
</div>

</div>

<script>
let hls;
const video = document.getElementById('video');
const favKey = "iptv_fav";

function getFav() {
    return JSON.parse(localStorage.getItem(favKey) || "[]");
}

function setFav(f) {
    localStorage.setItem(favKey, JSON.stringify(f));
}

function toggleFav(e,id) {
    e.stopPropagation();
    let f = getFav();

    if(f.includes(id)) f = f.filter(x=>x!==id);
    else f.push(id);

    setFav(f);
    renderFav();
    filter();
}

function renderFav() {
    let f = getFav();
    document.querySelectorAll(".fav").forEach(btn=>{
        let id = btn.parentElement.dataset.id;
        btn.textContent = f.includes(id) ? "★" : "☆";
    });
}

function load(url,name) {
    if(hls) hls.destroy();

    document.getElementById("title").innerText = name;

    if(Hls.isSupported()) {
        hls = new Hls();
        hls.loadSource(url);
        hls.attachMedia(video);
    } else {
        video.src = url;
    }
}

document.querySelectorAll(".channel").forEach(el=>{
    el.onclick = ()=>{
        document.querySelectorAll(".channel").forEach(x=>x.classList.remove("active"));
        el.classList.add("active");
        load(el.dataset.url, el.dataset.name);
    };
});

let activeCat="all";

document.querySelectorAll(".tab").forEach(tab=>{
    tab.onclick=()=>{
        document.querySelectorAll(".tab").forEach(t=>t.classList.remove("active"));
        tab.classList.add("active");
        activeCat = tab.dataset.cat;
        filter();
    };
});

document.getElementById("search").oninput = filter;

function filter() {
    let term = document.getElementById("search").value.toLowerCase();
    let fav = getFav();

    document.querySelectorAll(".channel").forEach(el=>{
        let name = el.dataset.name.toLowerCase();
        let cat = el.dataset.cat;
        let id = el.dataset.id;

        let show = name.includes(term);

        if(activeCat==="fav") show &= fav.includes(id);
        else if(activeCat!=="all") show &= cat===activeCat;

        el.style.display = show ? "flex" : "none";
    });
}

renderFav();
load("<?php echo $selectedChannel['url'];?>","<?php echo $selectedChannel['name'];?>");
</script>

</body>
</html>