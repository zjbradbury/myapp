<?php
require __DIR__ . '/field_import.php';
// Enforce the production relationships, especially the entry-to-runner restriction.
$deleteDb = new TestDatabase('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$deleteDb->exec('PRAGMA foreign_keys=ON');
$deleteDb->exec("CREATE TABLE sweepstake_pots(id INTEGER PRIMARY KEY,name TEXT,race_year INTEGER,entry_cents INTEGER,first_pct INTEGER,second_pct INTEGER,third_pct INTEGER,status TEXT DEFAULT 'open');
CREATE TABLE sweepstake_runners(id INTEGER PRIMARY KEY,pot_id INTEGER REFERENCES sweepstake_pots(id) ON DELETE CASCADE,number INTEGER,name TEXT,placing INTEGER);
CREATE TABLE sweepstake_entries(id INTEGER PRIMARY KEY,pot_id INTEGER REFERENCES sweepstake_pots(id) ON DELETE CASCADE,participant TEXT,paid INTEGER,runner_id INTEGER UNIQUE REFERENCES sweepstake_runners(id));
CREATE TABLE sweepstake_runner_details(runner_id INTEGER PRIMARY KEY REFERENCES sweepstake_runners(id) ON DELETE CASCADE,jockey TEXT,trainer TEXT,barrier TEXT,weight TEXT,recent_form TEXT,scratched INTEGER);
CREATE TABLE sweepstake_field_sync(pot_id INTEGER PRIMARY KEY REFERENCES sweepstake_pots(id) ON DELETE CASCADE,source_url TEXT,auto_refresh INTEGER DEFAULT 1,checked_at TEXT,synced_at TEXT,last_error TEXT)");
$kept = action($deleteDb,$create);
importAction($deleteDb,$kept,$field);
action($deleteDb,['action'=>'entry','pot_id'=>$kept,'participant'=>'Keep me','paid'=>'1']);
foreach (['open','drawn','finished'] as $status) {
    $target=action($deleteDb,$create);
    importAction($deleteDb,$target,$field);
    for ($i=0;$i<4;$i++) action($deleteDb,['action'=>'entry','pot_id'=>$target,'participant'=>'Guest '.$i]);
    if ($status !== 'open') action($deleteDb,['action'=>'draw','pot_id'=>$target]);
    if ($status === 'finished') {
        $s=$deleteDb->prepare('SELECT id FROM sweepstake_runners WHERE pot_id=? AND number<4 ORDER BY number'); $s->execute([$target]); $ids=$s->fetchAll(PDO::FETCH_COLUMN);
        action($deleteDb,['action'=>'results','pot_id'=>$target,'winner'=>$ids[0],'runner_up'=>$ids[1],'third_place'=>$ids[2]]);
    }
    try { action($deleteDb,['action'=>'delete_pot','pot_id'=>$target]); throw new LogicException('Unconfirmed deletion accepted'); } catch (InvalidArgumentException $e) {}
    check((int)$deleteDb->query("SELECT COUNT(*) FROM sweepstake_entries WHERE pot_id=$target")->fetchColumn()===4,'Unconfirmed deletion must preserve entries');
    check(action($deleteDb,['action'=>'delete_pot','pot_id'=>$target,'confirm_delete'=>'1'])===0,'Deletion must redirect to the pot listing');
    foreach (['sweepstake_pots'=>'id','sweepstake_runners'=>'pot_id','sweepstake_entries'=>'pot_id','sweepstake_field_sync'=>'pot_id'] as $table=>$column) {
        check((int)$deleteDb->query("SELECT COUNT(*) FROM $table WHERE $column=$target")->fetchColumn()===0,'Deleted pot must leave no related rows');
    }
    check((int)$deleteDb->query('SELECT COUNT(*) FROM sweepstake_runner_details')->fetchColumn()===5,'Only the retained pot runner details should remain');
    check((int)$deleteDb->query("SELECT COUNT(*) FROM sweepstake_entries WHERE pot_id=$kept AND paid=1")->fetchColumn()===1,'Other pot entries and payments must be preserved');
    check($deleteDb->query('PRAGMA foreign_key_check')->fetchAll()===[],'Foreign key relationships must remain valid');
}
echo "PASS: confirmed deletion of open, drawn and finished pots; dependent record cleanup; other pots preserved; foreign keys enforced.\n";
