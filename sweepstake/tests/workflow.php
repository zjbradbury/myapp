<?php
// Isolated workflow checks: no connection to, or mutations of, production data.
require dirname(__DIR__) . '/functions.php';
class TestDatabase extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function action(PDO $db, array $post): int {
    $_POST=$post; $db->beginTransaction();
    try { $id=mutate($db); $db->commit(); return $id; }
    catch (Throwable $e) { $db->rollBack(); throw $e; }
}
$db=new TestDatabase('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE sweepstake_pots(id INTEGER PRIMARY KEY, name TEXT, race_year INTEGER, entry_cents INTEGER, first_pct INTEGER, second_pct INTEGER, third_pct INTEGER, status TEXT DEFAULT "open"); CREATE TABLE sweepstake_runners(id INTEGER PRIMARY KEY,pot_id INTEGER,number INTEGER,name TEXT,placing INTEGER,UNIQUE(pot_id,number),UNIQUE(pot_id,placing)); CREATE TABLE sweepstake_entries(id INTEGER PRIMARY KEY,pot_id INTEGER,participant TEXT,paid INTEGER,runner_id INTEGER UNIQUE)');
$db->exec("CREATE TABLE sweepstake_runner_details(runner_id INTEGER PRIMARY KEY,jockey TEXT,trainer TEXT,barrier TEXT,weight TEXT,recent_form TEXT,scratched INTEGER DEFAULT 0); CREATE TABLE sweepstake_field_sync(pot_id INTEGER PRIMARY KEY,source_url TEXT,auto_refresh INTEGER DEFAULT 1,checked_at TEXT,synced_at TEXT,last_error TEXT)");
$create=['action'=>'create','name'=>'Test pot','price'=>'5.25','year'=>'2026','first'=>'60','second'=>'25','third'=>'15'];
$id=action($db,$create); $other=action($db,$create);
action($db,['action'=>'runners','pot_id'=>$id,'runners'=>"1 Alpha\n2 Bravo\n3 Charlie"]);
action($db,['action'=>'entry','pot_id'=>$id,'participant'=>'Alex','paid'=>'on']);
try { action($db,['action'=>'draw','pot_id'=>$id]); throw new RuntimeException('Partial draw accepted'); } catch (InvalidArgumentException $e) {}
foreach (['Alex','Sam'] as $name) action($db,['action'=>'entry','pot_id'=>$id,'participant'=>$name]);
action($db,['action'=>'draw','pot_id'=>$id]);
check((int)$db->query('SELECT COUNT(DISTINCT runner_id) FROM sweepstake_entries')->fetchColumn()===3,'Draw must assign unique horses');
check($db->query("SELECT status FROM sweepstake_pots WHERE id=$other")->fetchColumn()==='open','Other pots must remain unchanged');
try { action($db,['action'=>'entry','pot_id'=>$id,'participant'=>'Late']); throw new RuntimeException('Late entry accepted'); } catch (InvalidArgumentException $e) {}
try { action($db,['action'=>'draw','pot_id'=>$id]); throw new RuntimeException('Redraw accepted'); } catch (InvalidArgumentException $e) {}
$horses=$db->query('SELECT id FROM sweepstake_runners ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
action($db,['action'=>'results','pot_id'=>$id,'winner'=>$horses[0],'runner_up'=>$horses[1],'third_place'=>$horses[2]]);
action($db,['action'=>'results','pot_id'=>$id,'winner'=>$horses[2],'runner_up'=>$horses[1],'third_place'=>$horses[0]]);
check((int)$db->query('SELECT COUNT(*) FROM sweepstake_runners WHERE placing IS NOT NULL')->fetchColumn()===3,'Result correction must retain three placings');
check(array_sum(prizes(1575,['first_pct'=>60,'second_pct'=>25,'third_pct'=>15]))===1575,'Prizes must conserve every cent');
action($db,['action'=>'paid','pot_id'=>$other,'entry_id'=>1]);
check((int)$db->query('SELECT paid FROM sweepstake_entries WHERE id=1')->fetchColumn()===1,'Cannot change another pot payment');
echo "PASS: full-field validation, unique draw, locked entries, redraw prevention, pot isolation, result corrections, payment isolation, exact-cent payouts.\n";
