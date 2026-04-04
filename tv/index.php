<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
| Use either local file paths or remote URLs.
*/
$m3uSource = __DIR__ . '/playlist.m3u';   // or 'https://example.com/playlist.m3u'
$epgSource = __DIR__ . '/epg.xml';        // or 'https://example.com/epg.xml'

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
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

        if (stripos($line, '#EXTINF:') === 0) {
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

            if ($url !== '') {
                $channels[] = [
                    'name' => $name ?: ($attrs['tvg-name'] ?? 'Unnamed Channel'),
                    'url' => $url,
                    'logo' => $attrs['tvg-logo'] ?? '',
                    'group' => $attrs['group-title'] ?? 'Other',
                    'tvg_id' => $attrs['tvg-id'] ?? '',
                    'tvg_name' => $attrs['tvg-name'] ?? $name
                ];
            }
        }
    }

    return $channels;
}

function parseXmltvTime($timeString) {
    // XMLTV format example: 20260404180000 +0000
    $timeString = trim($timeString);
    if ($timeString === '') {
        return null;
    }

    if (preg_match('/^(\d{14})\s*([+\-]\d{4})?$/', $timeString, $m)) {
        $datePart = $m[1];
        $tzPart = $m[2] ?? '+0000';

        $formatted = substr($datePart, 0, 4) . '-' .
                     substr($datePart, 4, 2) . '-' .
                     substr($datePart, 6, 2) . ' ' .
                     substr($datePart, 8, 2) . ':' .
                     substr($datePart, 10, 2) . ':' .
                     substr($datePart, 12, 2) . ' ' .
                     $tzPart;

        $dt = DateTime::createFromFormat('Y-m-d H:i:s O', $formatted);
        return $dt ?: null;
    }

    return null;
}

function parseEPG($content) {
    $guide = [];

    if (trim($content) === '') {
        return $guide;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);

    if (!$xml) {
        return $guide;
    }

    foreach ($xml->programme as $programme) {
        $channelId = (string)$programme['channel'];
        $startRaw = (string)$programme['start'];
        $stopRaw = (string)$programme['stop'];

        $start = parseXmltvTime($startRaw);
        $stop = parseXmltvTime($stopRaw);

        if (!$start || !$stop || $channelId === '') {
            continue;
        }

        $title = trim((string)$programme->title);
        $desc = trim((string)$programme->desc);

        if (!isset($guide[$channelId])) {
            $guide[$channelId] = [];
        }

        $guide[$channelId][] = [
            'start' => $start,
            'stop' => $stop,
            'title' => $title !== '' ? $title : 'Untitled',
            'desc' => $desc
        ];
    }

    foreach ($guide as $channelId => $programmes) {
        usort($programmes, function ($a, $b) {
            return $a['start'] <=> $b['start'];
        });
        $guide[$channelId] = $programmes;
    }

    return $guide;
}

function getNowNextForChannel($tvgId, $guide, $timezone = 'Australia/Adelaide') {
    $result = [
        'now' => null,
        'next' => null
    ];

    if (!$tvgId || !isset($guide[$tvgId])) {
        return $result;
    }

    $now = new DateTime('now', new DateTimeZone($timezone));

    foreach ($guide[$tvgId] as $index => $program) {
        $start = clone $program['start'];
        $stop = clone $program['stop'];

        $start->setTimezone(new DateTimeZone($timezone));
        $stop->setTimezone(new DateTimeZone($timezone));

        if ($now >= $start && $now < $stop) {
            $result['now'] = [
                'title' => $program['title'],
                'desc' => $program['desc'],
                'start' => $start->format('g:i A'),
                'stop' => $stop->format('g:i A')
            ];

            if (isset($guide[$tvgId][$index + 1])) {
                $nextProgram = $guide[$tvgId][$index + 1];
                $nextStart = clone $nextProgram['start'];
                $nextStop = clone $nextProgram['stop'];
                $nextStart->setTimezone(new DateTimeZone($timezone));
                $nextStop->setTimezone(new DateTimeZone($timezone));

                $result['next'] = [
                    'title' => $nextProgram['title'],
                    'desc' => $nextProgram['desc'],
                    'start' => $nextStart->format('g:i A'),
                    'stop' => $nextStop->format('g:i A')
                ];
            }
            break;
        }

        if ($now < $start) {
            $result['next'] = [
                'title' => $program['title'],
                'desc' => $program['desc'],
                'start' => $start->format('g:i A'),
                'stop' => $stop->format('g:i A')
            ];
            break;
        }
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| LOAD DATA
|--------------------------------------------------------------------------
*/
$m3uContent = loadTextFile($m3uSource);
$epgContent = loadTextFile($epgSource);

$channels = parseM3U($m3uContent);
$guide = parseEPG($epgContent);

// Add now/next data to each channel
foreach ($channels as &$channel) {
    $channel['guide'] = getNowNextForChannel($channel['tvg_id'], $guide);
}
unset($channel);

// Default selected channel
$selectedIndex = 0;
if (isset($_GET['ch']) && is_numeric($_GET['ch'])) {
    $selectedIndex = max(0, min((int)$_GET['ch'], count($channels) - 1));
}
$selectedChannel = $channels[$selectedIndex] ?? null;

// Group channels
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
    <title>IPTV Player with M3U + EPG</title>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #0f1117;
            color: #fff;
        }

        .app {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 360px;
            background: #181c24;
            border-right: 1px solid #2a2f3a;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 16px;
            border-bottom: 1px solid #2a2f3a;
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
            overflow-y: auto;
            padding: 10px;
            flex: 1;
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

        .channel-program {
            font-size: 12px;
            color: #9da7b8;
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
            gap: 16px;
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
        }

        video {
            width: 100%;
            max-height: 70vh;
            background: #000;
            display: block;
        }

        .guide-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .guide-card {
            background: #181c24;
            border: 1px solid #2a2f3a;
            border-radius: 14px;
            padding: 16px;
        }

        .guide-label {
            font-size: 12px;
            color: #9da7b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .guide-time {
            font-size: 13px;
            color: #8ec5ff;
            margin-bottom: 6px;
        }

        .guide-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .guide-desc {
            font-size: 14px;
            color: #c9d1d9;
            line-height: 1.4;
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

        .empty-guide {
            color: #9da7b8;
            font-size: 14px;
        }

        @media (max-width: 980px) {
            .app {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                max-height: 360px;
            }

            .mobile-toggle {
                display: inline-block;
            }

            .channel-list {
                display: none;
            }

            .channel-list.show {
                display: block;
            }

            .guide-grid {
                grid-template-columns: 1fr;
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
                <button class="mobile-toggle" id="toggleChannels">Show / Hide Channels</button>
            </div>

            <div class="channel-list" id="channelList">
                <?php foreach ($groupedChannels as $groupName => $items): ?>
                    <div class="channel-group">
                        <div class="group-title"><?php echo htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8'); ?></div>

                        <?php foreach ($items as $item): ?>
                            <?php
                            $channel = $item['channel'];
                            $index = $item['index'];
                            $nowText = 'No guide data';
                            if (!empty($channel['guide']['now'])) {
                                $nowText = 'Now: ' . $channel['guide']['now']['title'];
                            } elseif (!empty($channel['guide']['next'])) {
                                $nowText = 'Next: ' . $channel['guide']['next']['title'];
                            }
                            ?>
                            <button
                                class="channel-item <?php echo $selectedIndex === $index ? 'active' : ''; ?>"
                                data-url="<?php echo htmlspecialchars($channel['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-name="<?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                onclick="selectChannel(this, <?php echo $index; ?>)"
                            >
                                <img
                                    class="channel-logo"
                                    src="<?php echo htmlspecialchars($channel['logo'] ?: 'https://via.placeholder.com/44x44?text=TV', ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    onerror="this.src='https://via.placeholder.com/44x44?text=TV';"
                                >
                                <div class="channel-meta">
                                    <div class="channel-name"><?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="channel-program"><?php echo htmlspecialchars($nowText, ENT_QUOTES, 'UTF-8'); ?></div>
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
                    <h2 id="currentChannel"><?php echo htmlspecialchars($selectedChannel['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <div class="status" id="playerStatus">Ready</div>
                </div>

                <div class="video-wrap">
                    <video id="video" controls autoplay playsinline></video>
                </div>

                <div class="guide-grid">
                    <div class="guide-card">
                        <div class="guide-label">Now Playing</div>
                        <?php if (!empty($selectedChannel['guide']['now'])): ?>
                            <div class="guide-time">
                                <?php echo htmlspecialchars($selectedChannel['guide']['now']['start'] . ' - ' . $selectedChannel['guide']['now']['stop'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="guide-title">
                                <?php echo htmlspecialchars($selectedChannel['guide']['now']['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="guide-desc">
                                <?php echo nl2br(htmlspecialchars($selectedChannel['guide']['now']['desc'] ?: 'No description.', ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-guide">No current program found for this channel.</div>
                        <?php endif; ?>
                    </div>

                    <div class="guide-card">
                        <div class="guide-label">Up Next</div>
                        <?php if (!empty($selectedChannel['guide']['next'])): ?>
                            <div class="guide-time">
                                <?php echo htmlspecialchars($selectedChannel['guide']['next']['start'] . ' - ' . $selectedChannel['guide']['next']['stop'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="guide-title">
                                <?php echo htmlspecialchars($selectedChannel['guide']['next']['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="guide-desc">
                                <?php echo nl2br(htmlspecialchars($selectedChannel['guide']['next']['desc'] ?: 'No description.', ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-guide">No upcoming program found for this channel.</div>
                        <?php endif; ?>
                    </div>
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

        if (toggleChannels) {
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