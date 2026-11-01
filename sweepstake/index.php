<?php
require __DIR__ . '/config.php';
$isAdmin = admin($pdo);
$error = '';
$ready = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('sweepstake_pots','sweepstake_entries','sweepstake_runners','sweepstake_runner_details','sweepstake_field_sync')")->fetchColumn() === 5;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    if (!$isAdmin) {
        http_response_code(403);
        exit('Administrator access required.');
    }
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['pot_id'] ?? 0);
    try {
        if ($action === 'install') {
            foreach (explode(';', file_get_contents(__DIR__ . '/schema.sql')) as $sql) if (trim($sql) !== '') $pdo->exec($sql);
            $id = 0;
            $message = 'Sweepstake tables are ready.';
        } else {
            if (!$ready) throw new InvalidArgumentException('Initialize or update the sweepstake tables first.');
            if (in_array($action, ['refresh_field', 'refresh_auto'], true)) {
                $stmt = $pdo->prepare('SELECT * FROM sweepstake_pots WHERE id=?');
                $stmt->execute([$id]);
                $target = $stmt->fetch();
                if (!$target || $target['status'] !== 'open') throw new InvalidArgumentException('Only open pots can import runners.');
                $sync = fieldSync($pdo, $id);
                if (!$sync) {
                    createSyncRecord($pdo, $id, (int)$target['race_year']);
                    $sync = fieldSync($pdo, $id);
                }
                $due = !$sync['checked_at'] || strtotime($sync['checked_at']) <= time() - 900;
                if ($action === 'refresh_field' || ($sync['auto_refresh'] && $due)) {
                    // Do not hold a database transaction open while waiting on the source.
                    $field = fetchCupField((int)$target['race_year']);
                    $pdo->beginTransaction();
                    importCupField($pdo, $id, $field, $action === 'refresh_auto' || $action === 'create');
                    $pdo->commit();
                }
                $message = 'Cup field check completed.';
            } else {
                $pdo->beginTransaction();
                $id = mutate($pdo);
                $pdo->commit();
                $message = $action === 'delete_pot' ? 'Pot deleted.' : 'Changes saved.';
                if ($action === 'create') {
                    // The pot remains usable even when this year's field is not published yet.
                    try {
                        $field = fetchCupField((int)$_POST['year']);
                        $pdo->beginTransaction();
                        importCupField($pdo, $id, $field, $action === 'refresh_auto' || $action === 'create');
                        $pdo->commit();
                        $message = 'Pot created and Cup runners imported automatically.';
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        saveSyncError($pdo, $id, $e->getMessage());
                        $message = 'Pot created. The Cup field could not be imported yet; see the field status below.';
                    }
                }
            }
        }
        $_SESSION['sweepstake_message'] = $message;
        header('Location: index.php' . ($id ? '?pot=' . $id . '#pot' : ''), true, 303);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($ready && in_array($action, ['refresh_field', 'refresh_auto'], true)) saveSyncError($pdo, $id, $e->getMessage());
        if ($e instanceof PDOException) {
            error_log('Sweepstake action: ' . $e->getMessage());
            $error = 'Unable to save changes. Please try again or check the database setup.';
        } else {
            $error = $e->getMessage();
        }
    }
}
$pots = $ready ? $pdo->query('SELECT p.*, COUNT(e.id) AS entries, COALESCE(SUM(e.paid),0) AS paid_entries FROM sweepstake_pots p LEFT JOIN sweepstake_entries e ON e.pot_id=p.id GROUP BY p.id ORDER BY p.race_year DESC,p.id DESC')->fetchAll() : [];
$selected = (int)($_GET['pot'] ?? $_POST['pot_id'] ?? ($pots[0]['id'] ?? 0));
$pot = null;
foreach ($pots as $p) if ((int)$p['id'] === $selected) $pot = $p;
$runners = $entries = [];
$sync = null;
$activeRunners = 0;
$fieldWarning = false;
if ($pot) {
    $s = $pdo->prepare('SELECT r.*,d.jockey,d.trainer,d.barrier,d.weight,d.recent_form,COALESCE(d.scratched,0) AS scratched FROM sweepstake_runners r LEFT JOIN sweepstake_runner_details d ON d.runner_id=r.id WHERE r.pot_id=? ORDER BY r.number');
    $s->execute([$selected]);
    $runners = $s->fetchAll();
    $activeRunners = count(array_filter($runners, fn($r) => !$r['scratched']));
    $sync = fieldSync($pdo, $selected);
    $fieldWarning = $sync && $sync['auto_refresh'] && $sync['last_error'];
    $s = $pdo->prepare('SELECT e.*,r.number,r.name AS horse,r.placing FROM sweepstake_entries e LEFT JOIN sweepstake_runners r ON r.id=e.runner_id WHERE e.pot_id=? ORDER BY e.id');
    $s->execute([$selected]);
    $entries = $s->fetchAll();
}
$message = $_SESSION['sweepstake_message'] ?? '';
unset($_SESSION['sweepstake_message']);
function hidden(string $action, int $id = 0): void
{ ?>
    <input type="hidden" name="csrf" value="<?= h(token()) ?>"><input type="hidden" name="action" value="<?= h($action) ?>"><input type="hidden" name="pot_id" value="<?= $id ?>">
<?php }
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Melbourne Cup · The Sweep</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header class="topbar"><a class="brand" href="index.php"><span class="brand-icon">♞</span> THE SWEEP <span class="brand-sub">MELBOURNE CUP</span></a>
        <nav><a href="/">Home</a><?php if ($isAdmin): ?><span class="admin-badge">Admin · <?= h($_SESSION['username'] ?? '') ?></span><a href="logout.php">Sign out</a><?php else: ?><a href="login.php">Admin sign in ↗</a><?php endif; ?></nav>
    </header>
    <main>
        <section class="hero">
            <div>
                <p class="eyebrow">WIN SOME, LOSE MORE.</p>
                <h1>Melbourne Cup<br>Sweepstake</h1>
                <p class="hero-description">The Sweep<br>Brought to you by Contract Resources</p><a class="button gold" href="#pots">Explore the pots <span>↘</span></a>
            </div>
            <div class="cup-art" aria-hidden="true"><span class="orbit one"></span><span class="orbit two"></span><span class="horse">♞</span><span class="art-caption"> Melbourne Cup</span></div>
        </section>
        <?php if ($error): ?><p class="notice error" role="alert"><?= h($error) ?></p><?php endif; ?><?php if ($message): ?><p class="notice" role="status"><?= h($message) ?></p><?php endif; ?>
        <section class="section-heading" id="pots">
            <div>
                <p class="eyebrow">FIND YOUR FIELD</p>
                <h2>The pots</h2>
            </div><span><?= count($pots) ?> pots · One great race</span>
        </section>
        <?php if (!$ready): ?><div class="panel">
                <h3>Getting the sweep ready</h3>
                <p>An administrator needs to initialize or update the sweepstake tables. Existing pots and entries are preserved.</p><?php if ($isAdmin): ?><form method="post"><?php hidden('install'); ?><button>Initialize / update sweepstake</button></form><?php else: ?><a href="login.php">Sign in as an admin →</a><?php endif; ?>
            </div><?php endif; ?>
        <div class="pot-grid"><?php foreach ($pots as $p): ?><a class="pot-card <?= (int)$p['id'] === $selected ? 'selected' : '' ?>" href="?pot=<?= (int)$p['id'] ?>#pot">
                    <div class="card-top"><span><?= h($p['race_year']) ?> SWEEP</span><span class="badge"><?= h(ucfirst($p['status'])) ?></span></div>
                    <h3><?= h($p['name']) ?></h3>
                    <div class="price"><?= money((int)$p['entry_cents']) ?><small>per entry</small></div>
                    <div class="card-bottom"><span><?= (int)$p['entries'] ?> entries</span><strong><?= money((int)$p['entries'] * (int)$p['entry_cents']) ?> prize pool ↗</strong></div>
                </a><?php endforeach; ?></div>
        <?php if ($ready && !$pots): ?><div class="panel empty"><span class="empty-icon">♞</span>
                <h3>The starting gates are open.</h3>
                <p><?= $isAdmin ? 'Create your first pot below to get the sweep underway.' : 'Your organiser will add the pots here soon.' ?></p>
            </div><?php endif; ?>
        <?php if ($pot): $total = count($entries) * (int)$pot['entry_cents'];
            $prizes = prizes($total, $pot); ?>
            <section id="pot" class="pot-detail">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">THE INSIDE TRACK · <?= h($pot['race_year']) ?></p>
                        <h2><?= h($pot['name']) ?></h2>
                    </div><span class="badge"><?= h(ucfirst($pot['status'])) ?></span>
                </div>
                <div class="stats">
                    <div><span>Prize pool</span><strong><?= money($total) ?></strong></div>
                    <div><span>Collected</span><strong><?= money((int)$pot['paid_entries'] * (int)$pot['entry_cents']) ?></strong></div>
                    <div><span>Field filled</span><strong><?= count($entries) ?> / <?= $activeRunners ?></strong></div>
                </div>
                <div class="prizes"><?php foreach (['1st place', '2nd place', '3rd place'] as $i => $label): ?><div><span><?= $label ?> · <?= (int)$pot[['first_pct', 'second_pct', 'third_pct'][$i]] ?>%</span><strong><?= money($prizes[$i]) ?></strong><?php foreach ($entries as $entry) if ((int)$entry['placing'] === $i + 1): ?><p>♞ <?= h($entry['participant']) ?> <small>· <?= h($entry['horse']) ?></small></p><?php endif; ?></div><?php endforeach; ?></div>
                <p class="subtle">Prizes use all registered entries. Outstanding payments: <?= money($total - (int)$pot['paid_entries'] * (int)$pot['entry_cents']) ?>.</p>
                <div class="panel field-panel">
                    <div class="section-heading compact">
                        <h3>The Cup field</h3><span><?= $activeRunners ?> active · <?= count($runners) - $activeRunners ?> scratched</span>
                    </div>
                    <?php if ($sync): ?><p class="field-status"><?php if ($sync['synced_at']): ?>Imported <?= h($sync['synced_at']) ?> (Adelaide time). <?php endif; ?><?php if (!$sync['auto_refresh']): ?>Manual field · auto refresh paused.<?php elseif ($pot['status'] !== 'open'): ?>Field locked after draw.<?php else: ?>Automatic refresh enabled.<?php endif; ?> <a href="<?= h($sync['source_url']) ?>" target="_blank" rel="noopener noreferrer">Racing Australia ↗</a></p><?php if ($sync['last_error'] && $isAdmin): ?><p class="notice error" role="status"><?= h($sync['last_error']) ?><?php if ($sync['checked_at']): ?> Last checked <?= h($sync['checked_at']) ?> (Adelaide time).<?php endif; ?></p><?php endif; ?><?php endif; ?>
                            <div class="table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>Horse</th>
                                            <th>Jockey</th>
                                            <th>Trainer</th>
                                            <th>Barrier</th>
                                            <th>Weight</th>
                                            <th>Recent form</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($runners as $runner): ?><tr class="<?= $runner['scratched'] ? 'scratched-runner' : '' ?>">
                                                <td><?= (int)$runner['number'] ?></td>
                                                <td><strong><?= h($runner['name']) ?></strong><?php if ($runner['scratched']): ?> <span class="badge">Scratched</span><?php endif; ?></td><?php foreach (['jockey', 'trainer', 'barrier', 'weight', 'recent_form'] as $key): ?><td><?= h($runner[$key] ?: '—') ?></td><?php endforeach; ?>
                                            </tr><?php endforeach; ?><?php if (!$runners): ?><tr>
                                                <td colspan="7">The Cup field is awaiting import.</td>
                                            </tr><?php endif; ?></tbody>
                                </table>
                            </div>
                            <p class="subtle">Scratched runners are excluded from entry slots and the draw. Drawn pots keep the field saved at draw time.</p>
                </div>
                <div class="panel">
                    <div class="section-heading compact">
                        <h3>The line-up</h3><span><?= $pot['status'] === 'open' ? 'Horses revealed after the draw' : 'Draw complete' ?></span>
                    </div>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Participant</th>
                                    <th>Horse</th>
                                    <th>Payment</th><?php if ($isAdmin): ?><th>Manage</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($entries as $entry): ?><tr>
                                        <td><strong><?= h($entry['participant']) ?></strong></td>
                                        <td><?= $entry['horse'] ? h($entry['number'] . '. ' . $entry['horse']) : '<span class="subtle">Awaiting draw</span>' ?><?= $entry['placing'] ? ' · ' . (int)$entry['placing'] . ' place' : '' ?></td>
                                        <td><span class="badge <?= $entry['paid'] ? 'paid' : '' ?>"><?= $entry['paid'] ? 'Paid' : 'Unpaid' ?></span></td><?php if ($isAdmin): ?><td class="actions">
                                                <form method="post"><?php hidden('paid', $selected); ?><input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>"><button class="text-button">Mark <?= $entry['paid'] ? 'unpaid' : 'paid' ?></button></form><?php if ($pot['status'] === 'open'): ?><form method="post"><?php hidden('remove', $selected); ?><input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>"><button class="text-button">Remove</button></form><?php endif; ?>
                                            </td><?php endif; ?>
                                    </tr><?php endforeach; ?><?php if (!$entries): ?><tr>
                                        <td colspan="4" class="subtle">No entries yet. Ask the organiser to add your name.</td>
                                    </tr><?php endif; ?></tbody>
                        </table>
                    </div>
                </div>
                <?php if ($isAdmin): ?><div class="admin-grid">
                        <?php if ($pot['status'] === 'open'): ?>
                            <div class="panel">
                                <h3>Add a participant</h3>
                                <p>One entry gets one horse. Add the same name again for extra entries.</p>
                                <form method="post"><?php hidden('entry', $selected); ?><label>Participant name<input name="participant" maxlength="100" required></label><label class="check"><input type="checkbox" name="paid"> Entry paid · <?= money((int)$pot['entry_cents']) ?></label><button>Add entry</button></form>
                            </div>
                            <div class="panel">
                                <h3>Automatic Cup field</h3>
                                <p>Runners and their details come from Racing Australia for this pot’s race year. Open pots refresh every 15 minutes while an admin has this page open.</p>
                                <form method="post" id="refresh-field"><?php hidden('refresh_field', $selected); ?><button>Refresh Cup field ↻</button></form>
                                <details class="manual-field">
                                    <summary>Enter runners manually</summary>
                                    <p>Saving a manual field pauses automatic refresh for this pot. Use Refresh Cup field to resume.</p>
                                    <form method="post"><?php hidden('runners', $selected); ?><label>Runners<textarea name="runners" rows="7" required placeholder="1 Horse name&#10;2 Horse name&#10;3 Horse name"><?php foreach ($runners as $runner) echo h($runner['number'] . ' ' . $runner['name']) . "\n"; ?></textarea></label><button>Save manual field</button></form>
                                </details>
                            </div>
                            <div class="panel draw-panel">
                                <p class="eyebrow">LEAVE IT TO LUCK</p>
                                <h3>Ready, set, draw.</h3>
                                <p>Fill all <?= $activeRunners ?> active runner slots, then randomly assign one horse to each entry. The draw locks the entries and field.</p>
                                <form method="post" onsubmit="return confirm('Draw this pot now? Entries and horses will be locked.');"><?php hidden('draw', $selected); ?><?php if ($fieldWarning): ?><p class="notice error">Resolve the field import warning or save a manual field before drawing.</p><?php endif; ?><button <?= $fieldWarning || $activeRunners < 3 || count($entries) !== $activeRunners ? 'disabled' : '' ?>>Draw this pot ↗</button></form>
                            </div>
                        <?php else: ?><div class="panel">
                                <h3>Record the results</h3>
                                <p>Enter the confirmed first three horses. You can correct the results here later.</p>
                                <form method="post"><?php hidden('results', $selected); ?><?php foreach (['winner' => '1st place', 'runner_up' => '2nd place', 'third_place' => '3rd place'] as $key => $label): ?><label><?= $label ?><select name="<?= $key ?>" required>
                                            <option value="">Choose horse</option><?php foreach ($runners as $runner): ?><option value="<?= (int)$runner['id'] ?>" <?= (int)$runner['placing'] === array_search($key, ['winner', 'runner_up', 'third_place'], true) + 1 ? 'selected' : '' ?>><?= h($runner['number'] . '. ' . $runner['name']) ?></option><?php endforeach; ?>
                                        </select></label><?php endforeach; ?><button>Save results</button></form>
                            </div><?php endif; ?>
                    </div><?php endif; ?>
            </section><?php endif; ?>
        <?php if ($isAdmin && $pot): ?>
            <details class="panel delete-pot">
                <summary>Delete this pot</summary>
                <p>Permanently delete <strong><?= h($pot['name']) ?></strong>, including all entries, payments, runners, results, and import details. This cannot be undone.</p>
                <form method="post">
                    <?php hidden('delete_pot', $selected); ?>
                    <label class="check"><input type="checkbox" name="confirm_delete" value="1" required> I confirm that I want to permanently delete this pot.</label>
                    <button class="danger" type="submit">Delete pot</button>
                </form>
            </details>
        <?php endif; ?>
        <?php if ($isAdmin && $ready): ?><details class="panel create-pot" <?= !$pots ? 'open' : '' ?>>
                <summary>Create a new pot <span>＋</span></summary>
                <form method="post"><?php hidden('create'); ?><div class="form-grid"><label>Pot name<input name="name" placeholder="The $5 sweep" maxlength="100" required></label><label>Race year<input name="year" type="number" min="2000" max="2200" value="<?= date('Y') ?>" required></label><label>Entry price ($)<input name="price" type="number" min="0.01" max="99999.99" step="0.01" value="5" required></label></div>
                    <p>Prize split · must total 100%</p>
                    <div class="form-grid"><?php foreach (['first' => 60, 'second' => 25, 'third' => 15] as $key => $value): ?><label><?= ucfirst($key) ?> place %<input name="<?= $key ?>" type="number" min="0" max="100" value="<?= $value ?>" required></label><?php endforeach; ?></div><button>Create pot</button>
                </form>
            </details><?php endif; ?>
        <?php if ($isAdmin && $pot && $pot['status'] === 'open' && (!$sync || $sync['auto_refresh'])): ?>
            <form method="post" id="auto-refresh-field" hidden><?php hidden('refresh_auto', $selected); ?></form>
            <script>
                (() => {
                    const dueAt = <?= $sync && $sync['checked_at'] ? (strtotime($sync['checked_at']) + 900) * 1000 : 0 ?>;
                    const delay = Math.max(1500, dueAt - Date.now());
                    window.setTimeout(() => {
                        if (document.visibilityState !== 'visible') {
                            document.addEventListener('visibilitychange', () => {
                                if (document.visibilityState === 'visible') location.reload();
                            }, {
                                once: true
                            });
                            return;
                        }
                        // Avoid interrupting an organiser who is editing a form.
                        if (document.querySelector('input:focus, textarea:focus, select:focus') || window.sweepstakeFormDirty) return;
                        document.getElementById('auto-refresh-field').requestSubmit();
                    }, delay);
                    document.addEventListener('input', () => {
                        window.sweepstakeFormDirty = true;
                    });
                })();
            </script>
        <?php endif; ?>
        <footer><a class="brand" href="index.php">♞ THE SWEEP</a><span>Melbourne Cup Sweepstake · Zack Bradbury</span></footer>
    </main>
</body>

</html>