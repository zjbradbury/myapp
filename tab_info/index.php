<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| TAB SA Racing Display
|--------------------------------------------------------------------------
| Jurisdiction is permanently set to SA.
|
| The checked meeting codes are combined into one hyphen-separated value:
| MR + MG + MH becomes page=MR-MG-MH
|--------------------------------------------------------------------------
*/

$codeGroups = [
    'Melbourne / Victoria' => [
        'MR' => 'Melbourne / VIC thoroughbreds',
        'MG' => 'Melbourne / VIC greyhounds',
        'MH' => 'Melbourne / VIC harness',
    ],
    'Sydney / NSW' => [
        'SR' => 'Sydney / NSW thoroughbreds',
        'SG' => 'Sydney / NSW greyhounds',
        'SH' => 'Sydney / NSW harness',
    ],
    'Brisbane / Queensland' => [
        'BR' => 'Brisbane / Queensland thoroughbreds',
        'BG' => 'Brisbane / Queensland greyhounds',
        'BH' => 'Brisbane / Queensland harness',
    ],
    'Adelaide / South Australia' => [
        'AR' => 'Adelaide / SA thoroughbreds',
        'AG' => 'Adelaide / SA greyhounds',
        'AH' => 'Adelaide / SA harness',
    ],
    'Country / Other Australian' => [
        'CR' => 'Country / additional thoroughbreds',
        'CG' => 'Country / additional greyhounds',
        'CH' => 'Country / additional harness',
        'PR' => 'Provincial thoroughbreds',
        'PG' => 'Provincial greyhounds',
        'PH' => 'Provincial harness',
    ],
    'Tasmania' => [
        'TR' => 'Tasmanian thoroughbreds',
        'TG' => 'Tasmanian greyhounds',
        'TH' => 'Tasmanian harness',
    ],
    'Western Australia' => [
        'WR' => 'WA thoroughbreds',
        'WG' => 'WA greyhounds',
        'WH' => 'WA harness',
    ],
    'New Zealand / International' => [
        'NR' => 'Additional / NZ thoroughbreds',
        'NG' => 'Additional / NZ greyhounds',
        'NH' => 'Additional / NZ harness',
        'YR' => 'New Zealand thoroughbreds',
        'YG' => 'New Zealand greyhounds',
        'YH' => 'New Zealand harness',
        'ER' => 'International thoroughbreds',
        'EG' => 'International greyhounds',
        'EH' => 'International harness',
        'FR' => 'International thoroughbreds 2',
        'FG' => 'International greyhounds 2',
        'FH' => 'International harness 2',
        'GR' => 'International thoroughbreds 3',
        'GG' => 'International greyhounds 3',
        'GH' => 'International harness 3',
        'UR' => 'International thoroughbreds 4',
        'UG' => 'International greyhounds 4',
        'UH' => 'International harness 4',
        'VR' => 'International thoroughbreds 5',
        'VG' => 'International greyhounds 5',
        'VH' => 'International harness 5',
        'XR' => 'International thoroughbreds 6',
        'XG' => 'International greyhounds 6',
        'XH' => 'International harness 6',
    ],
];

/*
 * TAB can rotate or reuse codes depending on the day's meeting schedule.
 * The custom-code input allows any current code to be added without editing PHP.
 */

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$config = [
    'jurisdiction' => 'SA',
    'channelType' => 'retail',
    'racingDetailUrl' => 'https://infodisplay.tab.com.au/racing-detail',
    'galleryUrl' => 'https://infodisplay.tab.com.au/deck-gallery-racing-with-triple-results',
    'allNextToJumpUrl' => 'https://infodisplay.tab.com.au/racing-detail?jurisdiction=SA&channelType=retail&page=0',
    'allSecondNextToJumpUrl' => 'https://infodisplay.tab.com.au/racing-detail?jurisdiction=SA&channelType=retail&page=1',
    'tripleResultsUrl' => 'https://infodisplay.tab.com.au/racing-triple-results?jurisdiction=SA&channelType=retail',
    'codeGroups' => $codeGroups,
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TAB SA Racing Display</title>
    <link rel="stylesheet" href="tab-display.css">
</head>
<body>
<div class="config-backdrop" id="configBackdrop" hidden></div>
<div class="page-shell">
    <aside class="config-panel" id="configPanel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">South Australia</p>
                <h1>TAB display setup</h1>
            </div>
            <button type="button" class="icon-button" id="hideConfig" aria-label="Hide configuration">×</button>
        </div>

        <form id="displayConfig">
            <section class="config-section">
                <h2>Displays</h2>
                <div class="check-grid">
                    <label class="check-card"><input type="checkbox" name="showSelectableNextToJump" checked><span class="custom-check"></span><span><strong>Selectable Next To Jump</strong><small>Uses selected meeting codes</small></span></label>
                    <label class="check-card"><input type="checkbox" name="showAllNextToJump"><span class="custom-check"></span><span><strong>All Races Next To Jump</strong><small>Fixed page 0; meeting codes do not apply</small></span></label>
                    <label class="check-card"><input type="checkbox" name="showAllSecondNextToJump"><span class="custom-check"></span><span><strong>All Second Next To Jump</strong><small>Fixed page 1; meeting codes do not apply</small></span></label>
                    <label class="check-card"><input type="checkbox" name="showTripleResults"><span class="custom-check"></span><span><strong>All Triple Race Results</strong><small>Fixed page; meeting codes do not apply</small></span></label>
                    <label class="check-card"><input type="checkbox" name="showGallery"><span class="custom-check"></span><span><strong>Gallery with Triple Results</strong><small>Fixed gallery; meeting codes do not apply</small></span></label>
                </div>
            </section>

            <section class="config-section">
                <div class="section-title-row">
                    <div>
                        <h2>Meeting codes</h2>
                        <p class="help-text">Checked codes are joined with hyphens.</p>
                    </div>
                    <button type="button" class="text-button" id="clearCodes">Clear</button>
                </div>

                <div class="preset-row">
                    <button type="button" class="preset-button" data-codes="MR,SR,BR,AR">MR-SR-BR-AR</button>
                    <button type="button" class="preset-button" data-codes="AR,AG,AH">AR-AG-AH</button>
                    <button type="button" class="preset-button" data-codes="MR,MG,MH,AR,AG,AH">Main + SA</button>
                </div>

                <?php foreach ($codeGroups as $groupName => $codes): ?>
                    <details class="code-group" <?= $groupName === 'Primary / Metropolitan' || $groupName === 'Adelaide / South Australia' ? 'open' : '' ?>>
                        <summary><?= h($groupName) ?></summary>
                        <div class="code-grid">
                            <?php foreach ($codes as $code => $description): ?>
                                <label class="code-card">
                                    <input
                                        type="checkbox"
                                        name="meetingCode[]"
                                        value="<?= h($code) ?>"
                                        <?= in_array($code, ['MR', 'SR', 'BR', 'AR'], true) ? 'checked' : '' ?>
                                    >
                                    <span class="custom-check"></span>
                                    <span>
                                        <strong><?= h($code) ?></strong>
                                        <small><?= h($description) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>

                <label class="field-label" for="customCodes">Additional TAB codes</label>
                <input
                    id="customCodes"
                    name="customCodes"
                    class="text-input"
                    type="text"
                    placeholder="Example: LR,LG,LH"
                    autocomplete="off"
                >
                <p class="help-text">
                    Separate additional codes with commas, spaces or hyphens.
                </p>

                <div class="page-preview">
                    <span>Page parameter</span>
                    <strong id="pageCodePreview">MR-SR-BR-AR</strong>
                </div>
            </section>

            <section class="config-section">
                <h2>Panel options</h2>

                <label class="field-label" for="gridColumns">Grid layout</label>
                <select id="gridColumns" name="gridColumns">
                    <option value="1">1 column</option>
                    <option value="2" selected>2 columns</option>
                    <option value="4">4 columns</option>
                </select>
                <p class="help-text">Select up to four pages for a 2 × 2 grid.</p>

                <label class="field-label" for="panelHeight">Panel height</label>
                <select id="panelHeight" name="panelHeight">
                    <option value="540">540 px</option>
                    <option value="700" selected>700 px</option>
                    <option value="850">850 px</option>
                    <option value="1000">1000 px</option>
                    <option value="1200">1200 px</option>
                </select>

                <div class="check-grid">
                    <label class="check-card">
                        <input type="checkbox" name="showPanelHeading" checked>
                        <span class="custom-check"></span>
                        <span>Show panel headings</span>
                    </label>
                </div>
            </section>

            <div class="action-row">
                <button type="submit" class="primary-button">Apply display</button>
                <button type="button" class="secondary-button" id="resetConfig">Reset</button>
            </div>
        </form>
    </aside>

    <main class="display-area">
        <header class="display-toolbar">
            <div>
                <p class="eyebrow">Jurisdiction fixed to SA</p>
                <h2 id="displayTitle">TAB racing display</h2>
            </div>

            <div class="toolbar-actions">
                <button type="button" class="secondary-button" id="showConfig">Configuration</button>
                <button type="button" class="secondary-button" id="fullscreenGrid">Fullscreen grid</button>
            </div>
        </header>

        <div id="displayGrid" class="display-grid"></div>
    </main>
</div>

<script>
const TAB_CONFIG = <?= json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="tab-display.js"></script>
</body>
</html>
