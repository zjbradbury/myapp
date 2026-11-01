<?php
require_once __DIR__ . "/field_import.php";
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function money(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}
function admin(PDO $pdo): bool
{
    if (empty($_SESSION['user_id'])) return false;
    $s = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $s->execute([$_SESSION['user_id']]);
    return $s->fetchColumn() === 'admin';
}
function token(): string
{
    return $_SESSION['sweepstake_csrf'] ??= bin2hex(random_bytes(32));
}
function csrf(): void
{
    if (!hash_equals(token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid form token. Refresh the page and try again.');
    }
}
function field(string $key, int $max = 100): string
{
    $value = trim((string)($_POST[$key] ?? ''));
    if ($value === '' || mb_strlen($value) > $max) throw new InvalidArgumentException('Please enter a valid ' . str_replace('_', ' ', $key) . '.');
    return $value;
}
function secureShuffle(array $items): array
{
    for ($i = count($items) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
    }
    return $items;
}
function prizes(int $total, array $pot): array
{
    $first = intdiv($total * (int)$pot['first_pct'], 100);
    $second = intdiv($total * (int)$pot['second_pct'], 100);
    return [$first, $second, $total - $first - $second];
}
function mutate(PDO $pdo): int
{
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name = field('name');
        $price = field('price');
        if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $price) || (float)$price <= 0) throw new InvalidArgumentException('Entry price must be between $0.01 and $99,999.99.');
        $year = filter_var($_POST['year'] ?? '', FILTER_VALIDATE_INT);
        $pcts = [];
        foreach (['first', 'second', 'third'] as $key) {
            $v = filter_var($_POST[$key] ?? '', FILTER_VALIDATE_INT);
            if ($v === false || $v < 0 || $v > 100) throw new InvalidArgumentException('Enter whole prize percentages from 0 to 100.');
            $pcts[] = $v;
        }
        if (array_sum($pcts) !== 100 || $year < 2000 || $year > 2200) throw new InvalidArgumentException('Prize percentages must total 100 and the year must be valid.');
        $s = $pdo->prepare('INSERT INTO sweepstake_pots (name,race_year,entry_cents,first_pct,second_pct,third_pct) VALUES (?,?,?,?,?,?)');
        $s->execute([$name, $year, (int)round((float)$price * 100), ...$pcts]);
        $id = (int)$pdo->lastInsertId();
        createSyncRecord($pdo, $id, (int)$year);
        return $id;
    }
    $id = (int)($_POST['pot_id'] ?? 0);
    $s = $pdo->prepare('SELECT * FROM sweepstake_pots WHERE id = ? FOR UPDATE');
    $s->execute([$id]);
    $pot = $s->fetch();
    if (!$pot) throw new InvalidArgumentException('Pot not found.');
    if ($action === 'runners' && $pot['status'] === 'open') {
        $s = $pdo->prepare('SELECT COUNT(*) FROM sweepstake_entries WHERE pot_id=?');
        $s->execute([$id]);
        $lines = preg_split('/\R/', field('runners', 10000));
        $runners = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            if (!preg_match('/^\s*(\d{1,3})\s+(.{1,100})\s*$/u', $line, $m) || (int)$m[1] < 1 || isset($runners[(int)$m[1]])) throw new InvalidArgumentException('Use one unique runner per line: number followed by horse name.');
            $runners[(int)$m[1]] = trim($m[2]);
        }
        if (count($runners) < 3 || count($runners) > 100 || count($runners) < (int)$s->fetchColumn()) throw new InvalidArgumentException('Enter 3–100 runners, with at least one runner per existing entry.');
        $details = $pdo->prepare('DELETE FROM sweepstake_runner_details WHERE runner_id IN (SELECT id FROM sweepstake_runners WHERE pot_id=?)');
        $details->execute([$id]);
        $pdo->prepare('DELETE FROM sweepstake_runners WHERE pot_id=?')->execute([$id]);
        if (!fieldSync($pdo, $id)) createSyncRecord($pdo, $id, (int)$pot['race_year']);
        $pdo->prepare('UPDATE sweepstake_field_sync SET auto_refresh=0,synced_at=NULL,last_error=NULL WHERE pot_id=?')->execute([$id]);
        $s = $pdo->prepare('INSERT INTO sweepstake_runners(pot_id,number,name) VALUES(?,?,?)');
        foreach ($runners as $number => $name) $s->execute([$id, $number, $name]);
    } elseif ($action === 'entry' && $pot['status'] === 'open') {
        $s = $pdo->prepare('SELECT (SELECT COUNT(*) FROM sweepstake_runners r LEFT JOIN sweepstake_runner_details d ON d.runner_id=r.id WHERE r.pot_id=? AND COALESCE(d.scratched,0)=0) - (SELECT COUNT(*) FROM sweepstake_entries WHERE pot_id=?)');
        $s->execute([$id, $id]);
        if ((int)$s->fetchColumn() <= 0) throw new InvalidArgumentException('Add runners first, or create another pot when this one is full.');
        $pdo->prepare('INSERT INTO sweepstake_entries(pot_id,participant,paid) VALUES(?,?,?)')->execute([$id, field('participant'), isset($_POST['paid']) ? 1 : 0]);
    } elseif ($action === 'paid') {
        $pdo->prepare('UPDATE sweepstake_entries SET paid=1-paid WHERE id=? AND pot_id=?')->execute([(int)($_POST['entry_id'] ?? 0), $id]);
    } elseif ($action === 'remove' && $pot['status'] === 'open') {
        $pdo->prepare('DELETE FROM sweepstake_entries WHERE id=? AND pot_id=?')->execute([(int)($_POST['entry_id'] ?? 0), $id]);
    } elseif ($action === 'draw' && $pot['status'] === 'open') {
        $sync = fieldSync($pdo, $id);
        if ($sync && $sync['auto_refresh'] && $sync['last_error']) throw new InvalidArgumentException('Resolve the field import warning or save a manual field before drawing.');
        $s = $pdo->prepare('SELECT r.id FROM sweepstake_runners r LEFT JOIN sweepstake_runner_details d ON d.runner_id=r.id WHERE r.pot_id=? AND COALESCE(d.scratched,0)=0 ORDER BY r.number');
        $s->execute([$id]);
        $runners = secureShuffle($s->fetchAll(PDO::FETCH_COLUMN));
        $s = $pdo->prepare('SELECT id FROM sweepstake_entries WHERE pot_id=? ORDER BY id');
        $s->execute([$id]);
        $entries = $s->fetchAll(PDO::FETCH_COLUMN);
        if (count($runners) < 3 || count($runners) !== count($entries)) throw new InvalidArgumentException('Fill every runner slot before drawing.');
        $s = $pdo->prepare('UPDATE sweepstake_entries SET runner_id=? WHERE id=? AND pot_id=?');
        foreach ($entries as $i => $entry) $s->execute([$runners[$i], $entry, $id]);
        $pdo->prepare("UPDATE sweepstake_pots SET status='drawn' WHERE id=?")->execute([$id]);
    } elseif ($action === 'results' && in_array($pot['status'], ['drawn', 'finished'], true)) {
        $places = array_map(fn($key) => (int)($_POST[$key] ?? 0), ['winner', 'runner_up', 'third_place']);
        if (count(array_unique($places)) !== 3 || min($places) <= 0) throw new InvalidArgumentException('Select three different horses.');
        $s = $pdo->prepare('SELECT id FROM sweepstake_runners WHERE pot_id=?');
        $s->execute([$id]);
        if (count(array_intersect($places, $s->fetchAll(PDO::FETCH_COLUMN))) !== 3) throw new InvalidArgumentException('Choose horses from this pot.');
        $pdo->prepare('UPDATE sweepstake_runners SET placing=NULL WHERE pot_id=?')->execute([$id]);
        $s = $pdo->prepare('UPDATE sweepstake_runners SET placing=? WHERE id=? AND pot_id=?');
        foreach ($places as $i => $runner) $s->execute([$i + 1, $runner, $id]);
        $pdo->prepare("UPDATE sweepstake_pots SET status='finished' WHERE id=?")->execute([$id]);
    } else {
        throw new InvalidArgumentException('This action is unavailable for the current pot status.');
    }
    return $id;
}
