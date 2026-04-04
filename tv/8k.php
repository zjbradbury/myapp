<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$m3uSource = 'http://cf.business-cdn-8k.su/get.php?username=tpowell&password=ed491c02cb&type=m3u_plus';
$epgSource = '';

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
                    'id' => md5(($attrs['tvg-id'] ?? '') . '|' . $name . '|' . $url),
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

$m3uContent = loadTextFile($m3uSource);
$epgContent = loadTextFile($epgSource);

$channels = parseM3U($m3uContent);
$guide = parseEPG($epgContent);

foreach ($channels as &$channel) {
    $channel['guide'] = getNowNextForChannel($channel['tvg_id'], $guide);
}
unset($channel);

$selectedIndex = 0;
if (isset($_GET['ch']) && is_numeric($_GET['ch'])) {
    $selectedIndex = max(0, min((int)$_GET['ch'], count($channels) - 1));
}
$selectedChannel = $channels[$selectedIndex] ?? null;

$groups = [];
foreach ($channels as $channel) {
    $groupName = trim($channel['group']) !== '' ? trim($channel['group']) : 'Other';
    $groups[$groupName] = true;
}
$groupNames = array_keys($groups);
sort($groupNames, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPTV Player</title>
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
            width: 380px;
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

        .tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .tab-btn {
            padding: 8px 12px;
            border: 1px solid #333a46;
            background: #202633;
            color: #fff;
            border-radius: 999px;
            cursor: pointer;
            font-size: 13px;
        }

        .tab-btn.active {
            background: #4ea1ff;
            border-color: #4ea1ff;
            color: #08111d;
            font-weight: bold;
        }

        .channel-list {
            overflow-y: auto;
            padding: 10px;
            flex: 1;
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

        .channel-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
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

        .channel-group-label {
            font-size: 11px;
            color: #7f8a9a;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .fav-btn {
            border: none;
            background: transparent;
            color: #9da7b8;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
            padding: 0;
            flex-shrink: 0;
        }

        .fav-btn.active {
            color: #ffd54f;
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

        .empty-guide,
        .empty-list {
            color: #9da7b8;
            font-size: 14px;
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
            .app {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                max-height: 420px;
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

                <div class="tabs" id="categoryTabs">
                    <button class="tab-btn active" data-category="all">All</button>
                    <button class="tab-btn" data-category="favourites">★ Favourites</button>
                    <?php foreach ($groupNames as $groupName): ?>
                        <button class="tab-btn" data-category="<?php echo htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <button class="mobile-toggle" id="toggleChannels">Show / Hide Channels</button>
            </div>

            <div class="channel-list" id="channelList">
                <?php if (count($channels) > 0): ?>
                    <?php foreach ($channels as $index => $channel): ?>
                        <?php
                        $nowText = 'No guide data';
                        if (!empty($channel['guide']['now'])) {
                            $nowText = 'Now: ' . $channel['guide']['now']['title'];
                        } elseif (!empty($channel['guide']['next'])) {
                            $nowText = 'Next: ' . $channel['guide']['next']['title'];
                        }
                        ?>
                        <button
                            class="channel-item <?php echo $selectedIndex === $index ? 'active' : ''; ?>"
                            data-index="<?php echo $index; ?>"
                            data-channel-id="<?php echo htmlspecialchars($channel['id'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-category="<?php echo htmlspecialchars($channel['group'], ENT_QUOTES, 'UTF-8'); ?>"
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
                                <div class="channel-top">
                                    <div class="channel-name"><?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <button
                                        type="button"
                                        class="fav-btn"
                                        data-fav-id="<?php echo htmlspecialchars($channel['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                        title="Toggle favourite"
                                        onclick="toggleFavourite(event, '<?php echo htmlspecialchars($channel['id'], ENT_QUOTES, 'UTF-8'); ?>')"
                                    >☆</button>
                                </div>

                                <div class="channel-program"><?php echo htmlspecialchars($nowText, ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="channel-group-label"><?php echo htmlspecialchars($channel['group'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </button>
                    <?php endforeach; ?>
                    <div id="emptyListMessage" class="empty-list" style="display:none; padding: 10px;">
                        No channels match this filter.
                    </div>
                <?php else: ?>
                    <div class="empty-list">No channels loaded. Check your playlist path.</div>
                <?php endif; ?>
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
        const categoryTabs = document.getElementById('categoryTabs');
        const emptyListMessage = document.getElementById('emptyListMessage');

        let hls = null;
        let activeCategory = 'all';

        function getFavourites() {
            try {
                return JSON.parse(localStorage.getItem('iptv_favourites') || '[]');
            } catch (e) {
                return [];
            }
        }

        function saveFavourites(favs) {
            localStorage.setItem('iptv_favourites', JSON.stringify(favs));
        }

        function isFavourite(channelId) {
            return getFavourites().includes(channelId);
        }

        function toggleFavourite(event, channelId) {
            event.stopPropagation();

            let favs = getFavourites();

            if (favs.includes(channelId)) {
                favs = favs.filter(id => id !== channelId);
            } else {
                favs.push(channelId);
            }

            saveFavourites(favs);
            updateFavouriteButtons();
            applyFilters();
        }

        function updateFavouriteButtons() {
            const favs = getFavourites();

            document.querySelectorAll('.fav-btn').forEach(btn => {
                const id = btn.getAttribute('data-fav-id');
                const active = favs.includes(id);
                btn.textContent = active ? '★' : '☆';
                btn.classList.toggle('active', active);
            });
        }

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
            document.querySelectorAll('.channel-item').forEach(function(item) {
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

        function applyFilters() {
            const searchTerm = (searchInput?.value || '').toLowerCase().trim();
            const favs = getFavourites();
            let visibleCount = 0;

            document.querySelectorAll('.channel-item').forEach(item => {
                const name = (item.getAttribute('data-name') || '').toLowerCase();
                const category = item.getAttribute('data-category') || '';
                const channelId = item.getAttribute('data-channel-id') || '';

                let show = true;

                if (searchTerm && !name.includes(searchTerm)) {
                    show = false;
                }

                if (activeCategory !== 'all') {
                    if (activeCategory === 'favourites') {
                        if (!favs.includes(channelId)) {
                            show = false;
                        }
                    } else if (category !== activeCategory) {
                        show = false;
                    }
                }

                item.style.display = show ? 'flex' : 'none';

                if (show) {
                    visibleCount++;
                }
            });

            if (emptyListMessage) {
                emptyListMessage.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }

        if (categoryTabs) {
            categoryTabs.addEventListener('click', function(e) {
                const btn = e.target.closest('.tab-btn');
                if (!btn) return;

                activeCategory = btn.getAttribute('data-category') || 'all';

                document.querySelectorAll('.tab-btn').forEach(tab => {
                    tab.classList.remove('active');
                });
                btn.classList.add('active');

                applyFilters();
            });
        }

        if (toggleChannels) {
            toggleChannels.addEventListener('click', function() {
                channelList.classList.toggle('show');
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateFavouriteButtons();
            applyFilters();

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