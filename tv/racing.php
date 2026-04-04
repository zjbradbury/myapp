<?php
$channels = [
    [
        "name" => "Sky 1",
        "url" => "https://skylivetab-new.akamaized.net/hls/live/2038780/sky1/index.m3u8",
        "logo" => ""
    ],
    [
        "name" => "Sky 2",
      "url" => "https://skylivetab-new.akamaized.net/hls/live/2038781/sky2/index.m3u8",
        "logo" => ""
    ]
];
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
            width: 320px;
            background: #181c24;
            border-right: 1px solid #2a2f3a;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 20px;
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
            width: 42px;
            height: 42px;
            border-radius: 8px;
            object-fit: cover;
            background: #111;
            flex-shrink: 0;
        }

        .channel-meta {
            overflow: hidden;
        }

        .channel-name {
            font-size: 15px;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .channel-url {
            font-size: 12px;
            color: #9da7b8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 4px;
        }

        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }

        .player-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .player-header h2 {
            margin: 0;
            font-size: 24px;
        }

        .status {
            font-size: 14px;
            color: #9da7b8;
        }

        .video-wrap {
            background: #000;
            border: 1px solid #2a2f3a;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
        }

        video {
            width: 100%;
            height: auto;
            display: block;
            background: #000;
            max-height: 75vh;
        }

        .footer-info {
            margin-top: 12px;
            color: #9da7b8;
            font-size: 13px;
        }

        .mobile-toggle {
            display: none;
            background: #202633;
            color: #fff;
            border: 1px solid #333a46;
            border-radius: 10px;
            padding: 10px 14px;
            cursor: pointer;
        }

        @media (max-width: 900px) {
            .app {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                max-height: 320px;
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

            video {
                max-height: 50vh;
            }
        }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>IPTV Channels</h1>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search channels...">
                </div>
                <div style="margin-top: 12px;">
                    <button class="mobile-toggle" id="toggleChannels">Show / Hide Channels</button>
                </div>
            </div>

            <div class="channel-list" id="channelList">
                <?php foreach ($channels as $index => $channel): ?>
                    <button
                        class="channel-item <?php echo $index === 0 ? 'active' : ''; ?>"
                        data-name="<?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-url="<?php echo htmlspecialchars($channel['url'], ENT_QUOTES, 'UTF-8'); ?>"
                        onclick="loadStreamFromButton(this)"
                    >
                        <img
                            class="channel-logo"
                            src="<?php echo htmlspecialchars($channel['logo'], ENT_QUOTES, 'UTF-8'); ?>"
                            alt="<?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?>"
                            onerror="this.src='https://via.placeholder.com/40x40?text=TV';"
                        >
                        <div class="channel-meta">
                            <div class="channel-name">
                                <?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="channel-url">
                                <?php echo htmlspecialchars($channel['url'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>
                    </button>
                <?php endforeach; ?>
            </div>
        </aside>

        <main class="main">
            <div class="player-header">
                <div>
                    <h2 id="currentChannel"><?php echo htmlspecialchars($channels[0]['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <div class="status" id="playerStatus">Ready</div>
                </div>
            </div>

            <div class="video-wrap">
                <video id="video" controls autoplay playsinline></video>
            </div>

            <div class="footer-info">
                Use the search box to filter channels. Click a channel to switch instantly.
            </div>
        </main>
    </div>

    <script>
        const video = document.getElementById('video');
        const currentChannel = document.getElementById('currentChannel');
        const playerStatus = document.getElementById('playerStatus');
        const searchInput = document.getElementById('searchInput');
        const channelList = document.getElementById('channelList');
        const toggleChannels = document.getElementById('toggleChannels');

        let hls = null;

        function setActiveButton(button) {
            document.querySelectorAll('.channel-item').forEach(item => {
                item.classList.remove('active');
            });
            button.classList.add('active');
        }

        function updateStatus(message) {
            playerStatus.textContent = message;
        }

        function destroyPlayer() {
            if (hls) {
                hls.destroy();
                hls = null;
            }
            video.pause();
            video.removeAttribute('src');
            video.load();
        }

        function loadStream(url, name) {
            destroyPlayer();

            currentChannel.textContent = name;
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
                    video.play().catch(() => {
                        updateStatus('Loaded - press play');
                    });
                });

                hls.on(Hls.Events.ERROR, function (event, data) {
                    if (data.fatal) {
                        updateStatus('Playback error');
                        console.error('HLS fatal error:', data);
                    }
                });
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = url;
                video.addEventListener('loadedmetadata', function onLoaded() {
                    updateStatus('Playing');
                    video.play().catch(() => {
                        updateStatus('Loaded - press play');
                    });
                    video.removeEventListener('loadedmetadata', onLoaded);
                });
            } else {
                updateStatus('HLS not supported in this browser');
                alert('Your browser does not support HLS playback.');
            }
        }

        function loadStreamFromButton(button) {
            const url = button.getAttribute('data-url');
            const name = button.getAttribute('data-name');
            setActiveButton(button);
            loadStream(url, name);
        }

        searchInput.addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.channel-item');

            items.forEach(item => {
                const name = item.getAttribute('data-name').toLowerCase();
                item.style.display = name.includes(term) ? 'flex' : 'none';
            });
        });

        toggleChannels.addEventListener('click', function () {
            channelList.classList.toggle('show');
        });

        // Load first channel on page load
        document.addEventListener('DOMContentLoaded', function () {
            const firstButton = document.querySelector('.channel-item');
            if (firstButton) {
                loadStreamFromButton(firstButton);
            }
        });
    </script>
</body>
</html>