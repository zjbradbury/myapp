<?php
// Fetch only the official, date-specific Flemington acceptances page.
function cupDate(int $year): DateTimeImmutable {
    if ($year < 2000 || $year > 2200) throw new InvalidArgumentException('Invalid Cup year.');
    return new DateTimeImmutable("first Tuesday of November $year", new DateTimeZone('Australia/Melbourne'));
}
function cupFieldUrl(int $year): string {
    return 'https://www.racingaustralia.horse/FreeFields/Acceptances.aspx?Key=' . cupDate($year)->format('YMd') . ',VIC,Flemington';
}
function fetchCupField(int $year): array {
    if (!function_exists('curl_init')) throw new RuntimeException('Automatic field import needs the PHP cURL extension.');
    $body = ''; $limit = 3 * 1024 * 1024;
    $ch = curl_init(cupFieldUrl($year));
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'MelbourneCupSweepstake/1.0',
        CURLOPT_ENCODING => '',
        CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body, $limit): int {
            if (strlen($body) + strlen($chunk) > $limit) return 0;
            $body .= $chunk; return strlen($chunk);
        },
    ]);
    $ok = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    if ($ok === false || $status !== 200) throw new RuntimeException('Racing Australia is unavailable. The saved field has been kept; try again later.');
    return parseCupField($body, $year);
}
function parseCupField(string $html, int $year): array {
    if (!class_exists('DOMDocument')) throw new RuntimeException('Automatic field import needs the PHP DOM extension.');
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try { $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    if (!$loaded) throw new RuntimeException('The field page could not be read. The saved field has been kept.');
    $xp = new DOMXPath($dom);
    $hasClass = static fn(string $name): string => "contains(concat(' ',normalize-space(@class),' '),' $name ')";
    $text = static fn(?DOMNode $node): string => trim(preg_replace('/\s+/u', ' ', $node?->textContent ?? ''));
    $venue = $text($xp->query('//*[' . $hasClass('race-venue') . ']')->item(0));
    $date = cupDate($year);
    if (!str_contains($venue, 'Flemington') || !preg_match('/\b0?' . $date->format('j') . '\s+November\s+' . $year . '\b/i', $venue)) {
        throw new RuntimeException('No matching Flemington Cup meeting is published for ' . $year . '. The saved field has been kept.');
    }
    $raceTable = null;
    foreach ($xp->query('//table[' . $hasClass('race-title') . ']') as $title) {
        $heading = $text($xp->query('.//th/span[1]', $title)->item(0));
        if (preg_match('/\bMelbourne Cup\s*\(3200\s+METRES\)/i', $heading)) {
            if ($raceTable !== null) throw new RuntimeException('The Cup field is ambiguous. The saved field has been kept.');
            $raceTable = $xp->query('following-sibling::table[1]', $title)->item(0);
        }
    }
    if (!$raceTable || !$xp->evaluate('boolean(self::table[' . $hasClass('race-strip-fields') . '])', $raceTable)) {
        throw new RuntimeException('The final Melbourne Cup field is not available yet, or its page format has changed. Try again later.');
    }
    $runners = [];
    foreach ($xp->query('.//tr[td]', $raceTable) as $row) {
        $cell = static fn(string $name): ?DOMNode => $xp->query('./td[' . $hasClass($name) . ']', $row)->item(0);
        $number = $text($cell('no'));
        if ($number === '') continue;
        $horse = $cell('horse');
        $name = $text($horse ? $xp->query('.//a[1]', $horse)->item(0) : null);
        if (!preg_match('/^\d{1,2}$/', $number) || (int)$number < 1 || (int)$number > 24 || $name === '' || mb_strlen($name) > 100 || isset($runners[(int)$number])) {
            throw new RuntimeException('The source contains an invalid or duplicate runner. The saved field has been kept.');
        }
        $scratchText = $text($row) . ' ' . $row->getAttribute('class');
        // Racing Australia uses Scratched/Scr/Scratching styles and strike markup.
        foreach ($xp->query('.//*[@class or @title or @style]', $row) as $node) {
            $scratchText .= ' ' . $node->getAttribute('class') . ' ' . $node->getAttribute('title') . ' ' . $node->getAttribute('style');
        }
        $scratched = preg_match('/\b(?:scratched|scratching|scr|scratchedrow)\b|line-through/i', $scratchText)
            || $xp->query('.//s | .//strike | .//del', $row)->length > 0;
        $runner = ['number'=>(int)$number,'name'=>$name,'scratched'=>(int)(bool)$scratched];
        foreach (['jockey','trainer','barrier','weight','last'] as $key) {
            $value = $text($cell($key));
            if (mb_strlen($value) > 255) throw new RuntimeException('The source runner details could not be validated.');
            $runner[$key === 'last' ? 'recent_form' : $key] = $value;
        }
        $runners[(int)$number] = $runner;
    }
    if (count($runners) < 3 || count($runners) > 24 || count(array_filter($runners, fn($r) => !$r['scratched'])) < 3) {
        throw new RuntimeException('A complete usable Cup field has not been published. The saved field has been kept.');
    }
    ksort($runners);
    return array_values($runners);
}
function fieldSync(PDO $pdo, int $potId): ?array {
    $s = $pdo->prepare('SELECT * FROM sweepstake_field_sync WHERE pot_id=?'); $s->execute([$potId]);
    return $s->fetch() ?: null;
}
function saveSyncError(PDO $pdo, int $potId, string $error): void {
    $s = $pdo->prepare('UPDATE sweepstake_field_sync SET checked_at=?,last_error=? WHERE pot_id=?');
    $s->execute([date('Y-m-d H:i:s'),mb_substr($error,0,500),$potId]);
}
function createSyncRecord(PDO $pdo, int $potId, int $year): void {
    $pdo->prepare('INSERT INTO sweepstake_field_sync (pot_id,source_url,auto_refresh) VALUES (?,?,1)')->execute([$potId,cupFieldUrl($year)]);
}
function importCupField(PDO $pdo, int $potId, array $runners, bool $automatic = false): void {
    // Caller holds the pot row lock inside a transaction; never replace a drawn field.
    $s = $pdo->prepare('SELECT status FROM sweepstake_pots WHERE id=? FOR UPDATE'); $s->execute([$potId]);
    if ($s->fetchColumn() !== 'open') throw new InvalidArgumentException('Drawn pots are locked. Their runners cannot be refreshed.');
    // A manual edit made while the HTTP request was in flight wins over an automatic refresh.
    if ($automatic && !(fieldSync($pdo,$potId)['auto_refresh'] ?? false)) return;
    $active = count(array_filter($runners,fn($r)=>!$r['scratched']));
    $s = $pdo->prepare('SELECT COUNT(*) FROM sweepstake_entries WHERE pot_id=?'); $s->execute([$potId]);
    if ($active < (int)$s->fetchColumn()) throw new InvalidArgumentException('The updated field has fewer active horses than entries. Remove the excess entries before refreshing. The saved field has been kept.');
    $s = $pdo->prepare('SELECT id FROM sweepstake_runners WHERE pot_id=?'); $s->execute([$potId]);
    $delete = $pdo->prepare('DELETE FROM sweepstake_runner_details WHERE runner_id=?');
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) $delete->execute([$id]);
    $pdo->prepare('DELETE FROM sweepstake_runners WHERE pot_id=?')->execute([$potId]);
    $runnerInsert = $pdo->prepare('INSERT INTO sweepstake_runners(pot_id,number,name) VALUES(?,?,?)');
    $detailInsert = $pdo->prepare('INSERT INTO sweepstake_runner_details(runner_id,jockey,trainer,barrier,weight,recent_form,scratched) VALUES(?,?,?,?,?,?,?)');
    foreach ($runners as $r) {
        $runnerInsert->execute([$potId,$r['number'],$r['name']]);
        $detailInsert->execute([(int)$pdo->lastInsertId(),$r['jockey'],$r['trainer'],$r['barrier'],$r['weight'],$r['recent_form'],$r['scratched']]);
    }
    $now = date('Y-m-d H:i:s');
    $pdo->prepare('UPDATE sweepstake_field_sync SET synced_at=?,checked_at=?,last_error=NULL,auto_refresh=1 WHERE pot_id=?')->execute([$now,$now,$potId]);
}
