<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| TAB Racing Information Display Selector
|--------------------------------------------------------------------------
| Upload this folder to your PHP website.
|
| Meeting locations:
| Add or edit entries below. The "page" value is the TAB display page code
| seen in the TAB display URL, for example:
|   .../racing-results-next-to-go?...&page=MR7
|
| The location list is deliberately editable because TAB page codes can vary
| by meeting and day.
*/

$jurisdictions = [
    'NSW' => 'New South Wales',
    'VIC' => 'Victoria',
    'QLD' => 'Queensland',
    'SA'  => 'South Australia',
    'WA'  => 'Western Australia',
    'TAS' => 'Tasmania',
    'NT'  => 'Northern Territory',
    'ACT' => 'Australian Capital Territory',
];

$displayTypes = [
    'next-to-go' => [
        'label' => 'Next to Jump',
        'path'  => '/racing-next-to-go',
        'supports_page' => false,
    ],
    'results-next-to-go' => [
        'label' => 'Race / Runner Display',
        'path'  => '/racing-results-next-to-go',
        'supports_page' => true,
    ],
    'upcoming' => [
        'label' => 'Upcoming Races',
        'path'  => '/racing-upcoming-races',
        'supports_page' => false,
    ],
    'gallery' => [
        'label' => 'Racing Gallery',
        'path'  => '/racing-gallery',
        'supports_page' => false,
    ],
];

/*
|--------------------------------------------------------------------------
| Meeting location presets
|--------------------------------------------------------------------------
| Add your regular meeting/page codes here.
| Example format:
| [
|   'id' => 'bendigo-r7',
|   'label' => 'Bendigo Race 7',
|   'jurisdiction' => 'VIC',
|   'page' => 'MR7',
| ]
*/
$meetingLocations = [
    [
        'id' => 'example-vic-mr7',
        'label' => 'Example VIC Meeting / Race 7',
        'jurisdiction' => 'VIC',
        'page' => 'MR7',
    ],
];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$config = [
    'baseUrl' => 'https://infodisplay.tab.com.au',
    'jurisdictions' => $jurisdictions,
    'displayTypes' => $displayTypes,
    'meetingLocations' => $meetingLocations,
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TAB Racing Display Selector</title>
    <link rel="stylesheet" href="tab-display.css">
</head>
<body>
<div class="app-shell">
    <aside class="config-panel" aria-label="Racing display configuration">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Display setup</p>
                <h1>TAB Racing</h1>
            </div>
            <button class="icon-button" id="collapseConfig" type="button" title="Hide configuration">×</button>
        </div>

        <form id="displayConfig">
            <section class="config-section">
                <div class="section-title-row">
                    <h2>Display items</h2>
                    <button class="text-button" type="button" data-toggle-group="displayType">Toggle all</button>
                </div>

                <div class="check-grid">
                    <?php foreach ($displayTypes as $key => $display): ?>
                        <label class="check-card">
                            <input
                                type="checkbox"
                                name="displayType[]"
                                value="<?= h($key) ?>"
                                <?= $key === 'next-to-go' ? 'checked' : '' ?>
                            >
                            <span class="custom-check"></span>
                            <span><?= h($display['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="config-section">
                <div class="section-title-row">
                    <h2>Jurisdiction</h2>
                    <button class="text-button" type="button" data-toggle-group="jurisdiction">Toggle all</button>
                </div>

                <div class="check-grid two-column">
                    <?php foreach ($jurisdictions as $code => $name): ?>
                        <label class="check-card compact">
                            <input
                                type="checkbox"
                                name="jurisdiction[]"
                                value="<?= h($code) ?>"
                                <?= in_array($code, ['NSW', 'VIC', 'QLD', 'SA'], true) ? 'checked' : '' ?>
                            >
                            <span class="custom-check"></span>
                            <span>
                                <strong><?= h($code) ?></strong>
                                <small><?= h($name) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="config-section">
                <div class="section-title-row">
                    <h2>Meeting locations</h2>
                    <button class="text-button" type="button" data-toggle-group="meeting">Toggle all</button>
                </div>

                <p class="help-text">
                    These are editable page-code presets from the PHP configuration.
                </p>

                <div class="check-grid">
                    <?php foreach ($meetingLocations as $meeting): ?>
                        <label class="check-card meeting-card">
                            <input
                                type="checkbox"
                                name="meeting[]"
                                value="<?= h($meeting['id']) ?>"
                            >
                            <span class="custom-check"></span>
                            <span>
                                <strong><?= h($meeting['label']) ?></strong>
                                <small><?= h($meeting['jurisdiction']) ?> · <?= h($meeting['page']) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>

                    <?php if (!$meetingLocations): ?>
                        <div class="empty-note">No meeting presets have been configured.</div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="config-section">
                <h2>Information shown</h2>

                <div class="check-grid two-column">
                    <label class="check-card compact">
                        <input type="checkbox" name="showHeader" checked>
                        <span class="custom-check"></span>
                        <span>Panel heading</span>
                    </label>

                    <label class="check-card compact">
                        <input type="checkbox" name="showJurisdiction" checked>
                        <span class="custom-check"></span>
                        <span>Jurisdiction label</span>
                    </label>

                    <label class="check-card compact">
                        <input type="checkbox" name="showRefresh" checked>
                        <span class="custom-check"></span>
                        <span>Last refreshed</span>
                    </label>

                    <label class="check-card compact">
                        <input type="checkbox" name="showOpenLink">
                        <span class="custom-check"></span>
                        <span>Open TAB link</span>
                    </label>
                </div>
            </section>

            <section class="config-section">
                <h2>Layout</h2>

                <label class="field-label" for="columns">Columns</label>
                <select id="columns" name="columns">
                    <option value="1">1 column</option>
                    <option value="2" selected>2 columns</option>
                    <option value="3">3 columns</option>
                </select>

                <label class="field-label" for="panelHeight">Panel height</label>
                <select id="panelHeight" name="panelHeight">
                    <option value="520">Compact — 520 px</option>
                    <option value="700" selected>Standard — 700 px</option>
                    <option value="900">Tall — 900 px</option>
                    <option value="1100">Extra tall — 1100 px</option>
                </select>

                <label class="field-label" for="refreshSeconds">Auto refresh</label>
                <select id="refreshSeconds" name="refreshSeconds">
                    <option value="0">Off</option>
                    <option value="30">Every 30 seconds</option>
                    <option value="60" selected>Every minute</option>
                    <option value="120">Every 2 minutes</option>
                    <option value="300">Every 5 minutes</option>
                </select>
            </section>

            <div class="action-row">
                <button class="primary-button" type="submit">Apply display</button>
                <button class="secondary-button" id="resetConfig" type="button">Reset</button>
            </div>
        </form>
    </aside>

    <main class="display-area">
        <header class="display-toolbar">
            <div>
                <p class="eyebrow">Live information panels</p>
                <h2 id="displayTitle">Racing display</h2>
            </div>

            <div class="toolbar-actions">
                <button class="secondary-button" id="showConfig" type="button">Configuration</button>
                <button class="secondary-button" id="refreshNow" type="button">Refresh now</button>
                <button class="secondary-button" id="fullscreenButton" type="button">Fullscreen</button>
            </div>
        </header>

        <div class="notice">
            The panels below embed TAB’s public information display pages. TAB controls the content and availability.
        </div>

        <div id="displayGrid" class="display-grid" aria-live="polite"></div>
    </main>
</div>

<script>
const TAB_CONFIG = <?= json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="tab-display.js"></script>
</body>
</html>
