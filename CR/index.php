<?php
date_default_timezone_set('Australia/Adelaide');

$navItems = [
    [
        'title' => 'Assets Dashboard',
        'description' => 'Asset management dashboard, certs and details.',
        'href' => '/CR/assets',
        'icon' => '⚙',
        'label' => 'Asset Management',
    ],

];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Site Navigation</title>
    <link rel="stylesheet" href="indexStyle.css">
</head>

<body>
    <main class="home-shell">
        <header class="hero">
            <div class="hero-copy">
                <div class="section-kicker">site portal</div>
                <h1>Home Navigation</h1>
                <p>Select a system below to continue.</p>
            </div>

            <div class="clock-card" aria-live="polite">
                <span class="clock-label">Local Site Time</span>
                <strong id="liveClock"><?= date('H:i:s') ?></strong>
                <span id="liveDate"><?= date('l, j F Y') ?></span>
            </div>
        </header>

        <section class="navigation-panel">
            <div class="panel-heading">
                <div>
                    <div class="section-kicker">available systems</div>
                    <h2>Applications</h2>
                </div>
                <span class="system-count"><?= count($navItems) ?> links</span>
            </div>

            <div class="navigation-grid">
                <?php foreach ($navItems as $item): ?>
                    <a class="navigation-card" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="card-top">
                            <span class="card-icon" aria-hidden="true"><?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="card-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div class="card-content">
                            <h3><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p><?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>

                        <div class="card-action">
                            <span>Open page</span>
                            <span class="arrow" aria-hidden="true">→</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <footer class="footer">
            <span>Zack Bradbury</span>
            <span class="footer-separator">•</span>
            <span>Site Portal</span>
        </footer>
    </main>

    <script>
        (function() {
            const clock = document.getElementById('liveClock');
            const date = document.getElementById('liveDate');

            function updateClock() {
                const now = new Date();

                clock.textContent = new Intl.DateTimeFormat('en-AU', {
                    timeZone: 'Australia/Adelaide',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                }).format(now);

                date.textContent = new Intl.DateTimeFormat('en-AU', {
                    timeZone: 'Australia/Adelaide',
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                }).format(now);
            }

            updateClock();
            window.setInterval(updateClock, 1000);
        }());
    </script>
</body>

</html>