<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
| Use either local file paths or remote URLs.
*/
$m3uSource = 'https://i.mjh.nz/au/Adelaide/raw-tv.m3u8';

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function loadTextFile($source) {
    if (filter_var($source, FILTER_VALIDATE_URL)) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'PHP IPTV Player'
            ],
            'https' => [
                'timeout' => 15,
                'user_agent' => 'PHP IPTV Player'
            ]
        ]);
        $content = @file_get_contents($source, false, $context);
    } else {
        $content = @file_get_contents($source);
    }

    return $content !== false ? $content : '';
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
    $count = count($lines);

    for ($i = 0; $i < $count; $i++) {
        $line = trim($lines[$i]);

        if (stripos($line, '#EXTINF:') !== 0) {
            continue;
        }

        $attrs = parseAttributes($line);

        $name = '';
        $commaPos = strrpos($line, ',');
        if ($commaPos !== false) {
            $name = trim(substr($line, $commaPos + 1));
        }

        $url = '';
        for ($j = $i + 1; $j < $count; $j++) {
            $nextLine = trim($lines[$j]);

            if ($nextLine === '' || strpos($nextLine, '#') === 0) {
                continue;
            }

            $url = $nextLine;
            $i = $j;
            break;
        }

        if ($url === '') {
            continue;
        }

        $channels[] = [
            'name' => $name ?: ($attrs['tvg-name'] ?? 'Unnamed Channel'),
            'url' => $url,
            'logo' => $attrs['tvg-logo'] ?? '',
            'group' => $attrs['group-title'] ?? 'Other'
        ];
    }

    return $channels;
}

/*
|--------------------------------------------------------------------------
| LOAD DATA
|--------------------------------------------------------------------------
*/
$m3uContent = loadTextFile($m3uSource);
$channels = parseM3U($m3uContent);

$selectedIndex = 0;
if (isset($_GET['ch']) && is_numeric($_GET['ch']) && count($channels) > 0) {
    $selectedIndex = max(0, min((int)$_GET['ch'], count($channels) - 1));
}
$selectedChannel = $channels[$selectedIndex] ?? null;

$groupedChannels = [];
foreach ($channels as $index => $channel) {
    $groupName = $channel['group'] ?: 'Other';

    if (!isset($groupedChannels[$groupName])) {
        $groupedChannels[$groupName] = [];
    }

    $groupedChannels[$groupName][] = [
        'index' => $index,
        'channel' => $channel
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TV Player</title>
    <link rel="icon" type="image/x-icon" href="/images/favicon-tv.ico">
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            font-family: Arial, sans-serif;
            background: #0f1117;
            color: #fff;
        }

        .app {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar {
            width: 360px;
            height: 100vh;
            background: #181c24;
            border-right: 1px solid #2a2f3a;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            flex-shrink: 0;
        }

        .sidebar-header {
            padding: 16px;
            border-bottom: 1px solid #2a2f3a;
            flex-shrink: 0;
        }

        .sidebar-header h1 {
            margin: 0 0 12px;
            font-size: 22px;
        }

        .search-box input {
            width: 100%;
            padding: 12px;
            border: 1px solid #333a46;
            border-radius: 10px;
            background: #10141b;
            color: #fff;
            font-size: 15px;
            outline: none;
        }

        .channel-list {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 10px;
        }

        .group-title {
            margin: 14px 8px 8px;
            font-size: 13px;
            color: #9da7b8;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .channel-item {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 12px;
            margin-bottom: 8px;
            background: #202633;
            border: 1px solid transparent;
            border-radius: 12px;
            color: #fff;
            cursor: pointer;
            text-align: left;
            transition: 0.2s ease;
        }

        .channel-item:hover {
            background: #2a3140;
        }

        .channel-item.active {
            border-color: #4ea1ff;
            background: #253246;
        }

        .channel-logo {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            object-fit: cover;
            background: #111;
            flex-shrink: 0;
        }

        .channel-meta {
            min-width: 0;
            flex: 1;
        }

        .channel-name {
            font-size: 15px;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .channel-group-label {
            font-size: 12px;
            color: #9da7b8;
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .main {
            flex: 1;
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-width: 0;
        }

        .player-header h2 {
            margin: 0 0 6px;
            font-size: 26px;
        }

        .status {
            color: #9da7b8;
            font-size: 14px;
        }

        .video-wrap {
            background: #000;
            border: 1px solid #2a2f3a;
            border-radius: 16px;
            overflow: hidden;
            flex-shrink: 0;
        }

        video {
            width: 100%;
            max-height: 70vh;
            background: #000;
            display: block;
        }

        .mobile-toggle {
            display: none;
            margin-top: 10px;
            background: #202633;
            color: #fff;
            border: 1px solid #333a46;
            border-radius: 10px;
            padding: 10px 14px;
            cursor: pointer;
        }

        @media (max-width: 980px) {
            html, body {
                height: auto;
                overflow: auto;
            }

            .app {
                flex-direction: column;
                height: auto;
                overflow: visible;
            }

            .sidebar {
                width: 100%;
                height: auto;
                max-height: 360px;
            }

            .mobile-toggle {
                display: inline-block;
            }

            .channel-list {
                display: none;
                min-height: auto;
                max-height: 300px;
            }

            .channel-list.show {
                display: block;
            }

            .main {
                height: auto;
                overflow: visible;
            }

            video {
                max-height: 45vh;
            }
        }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>Channels</h1>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search channels...">
                </div>
                <button class="mobile-toggle" id="toggleChannels" type="button">Show / Hide Channels</button>
            </div>

            <div class="channel-list" id="channelList">
                <?php foreach ($groupedChannels as $groupName => $items): ?>
                    <div class="channel-group">
                        <div class="group-title"><?php echo h($groupName); ?></div>

                        <?php foreach ($items as $item): ?>
                            <?php
                            $channel = $item['channel'];
                            $index = $item['index'];
                            $fallbackLogo = 'https://via.placeholder.com/44x44?text=TV';
                            ?>
                            <button
                                class="channel-item <?php echo $selectedIndex === $index ? 'active' : ''; ?>"
                                data-url="<?php echo h($channel['url']); ?>"
                                data-name="<?php echo h($channel['name']); ?>"
                                onclick="selectChannel(this, <?php echo (int)$index; ?>)"
                                type="button"
                            >
                                <img
                                    class="channel-logo"
                                    src="<?php echo h($channel['logo'] ?: $fallbackLogo); ?>"
                                    alt="<?php echo h($channel['name']); ?>"
                                    onerror="this.src='<?php echo h($fallbackLogo); ?>';"
                                >
                                <div class="channel-meta">
                                    <div class="channel-name"><?php echo h($channel['name']); ?></div>
                                    <div class="channel-group-label"><?php echo h($channel['group'] ?: 'TV Channel'); ?></div>
                                </div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <main class="main">
            <?php if ($selectedChannel): ?>
                <div class="player-header">
                    <h2 id="currentChannel"><?php echo h($selectedChannel['name']); ?></h2>
                    <div class="status" id="playerStatus">Ready</div>
                </div>

                <div class="video-wrap">
                    <video id="video" controls autoplay playsinline></video>
                </div>
            <?php else: ?>
                <h2>No channels loaded</h2>
                <p>Check your playlist path or URL.</p>
            <?php endif; ?>
        </main>
    </div>

    <script>
        const video = document.getElementById('video');
        const playerStatus = document.getElementById('playerStatus');
        const currentChannel = document.getElementById('currentChannel');
        const searchInput = document.getElementById('searchInput');
        const toggleChannels = document.getElementById('toggleChannels');
        const channelList = document.getElementById('channelList');

        let hls = null;

        function updateStatus(text) {
            if (playerStatus) {
                playerStatus.textContent = text;
            }
        }

        function destroyPlayer() {
            if (hls) {
                hls.destroy();
                hls = null;
            }

            if (video) {
                video.pause();
                video.removeAttribute('src');
                video.load();
            }
        }

        function loadStream(url, name) {
            if (!video) return;

            destroyPlayer();

            if (currentChannel) {
                currentChannel.textContent = name;
            }

            updateStatus('Loading...');

            if (Hls.isSupported()) {
                hls = new Hls({
                    enableWorker: true,
                    lowLatencyMode: true
                });

                hls.loadSource(url);
                hls.attachMedia(video);

                hls.on(Hls.Events.MANIFEST_PARSED, function () {
                    updateStatus('Playing');
                    video.play().catch(function () {
                        updateStatus('Loaded - press play');
                    });
                });

                hls.on(Hls.Events.ERROR, function (event, data) {
                    if (data.fatal) {
                        updateStatus('Playback error');
                        console.error(data);
                    }
                });
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = url;
                video.addEventListener('loadedmetadata', function onLoaded() {
                    updateStatus('Playing');
                    video.play().catch(function () {
                        updateStatus('Loaded - press play');
                    });
                    video.removeEventListener('loadedmetadata', onLoaded);
                });
            } else {
                updateStatus('HLS not supported');
                alert('This browser does not support HLS playback.');
            }
        }

        function selectChannel(button, index) {
            document.querySelectorAll('.channel-item').forEach(function (item) {
                item.classList.remove('active');
            });
            button.classList.add('active');

            const url = button.getAttribute('data-url');
            const name = button.getAttribute('data-name');

            loadStream(url, name);

            const newUrl = new URL(window.location.href);
            newUrl.searchParams.set('ch', index);
            window.history.replaceState({}, '', newUrl);
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const term = this.value.toLowerCase().trim();

                document.querySelectorAll('.channel-item').forEach(function (item) {
                    const name = (item.getAttribute('data-name') || '').toLowerCase();
                    item.style.display = name.includes(term) ? 'flex' : 'none';
                });

                document.querySelectorAll('.channel-group').forEach(function (group) {
                    const visibleItems = Array.from(group.querySelectorAll('.channel-item'))
                        .filter(item => item.style.display !== 'none');
                    group.style.display = visibleItems.length ? 'block' : 'none';
                });
            });
        }

        if (toggleChannels && channelList) {
            toggleChannels.addEventListener('click', function () {
                channelList.classList.toggle('show');
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($selectedChannel): ?>
                loadStream(
                    <?php echo json_encode($selectedChannel['url']); ?>,
                    <?php echo json_encode($selectedChannel['name']); ?>
                );
            <?php endif; ?>
        });
    </script>
</body>
</html>
