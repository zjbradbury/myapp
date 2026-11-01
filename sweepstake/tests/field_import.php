<?php
require __DIR__ . '/workflow.php';
function fixture(int $year=2026): string {
    $date=cupDate($year)->format('l, d F Y');
    $html='<div class="race-venue"><h2>Flemington: Victoria Racing Club <span>'.$date.'</span></h2></div>';
    // Another race with a similar name must never be imported.
    $html.='<table class="race-title"><tr><th><span>Race 2 - Melbourne Cup Day Trophy (1800 METRES)</span></th></tr></table><table class="race-strip-fields"><tr><td class="no">99</td><td class="horse"><a>Wrong Horse</a></td></tr></table>';
    $html.='<table class="race-title"><tr><th><span>Race 7 - 3:00PM Lexus Melbourne Cup (3200 METRES)</span></th></tr></table><table class="race-strip-fields"><tr><th>No</th><th>Horse</th></tr>';
    for ($n=1;$n<=5;$n++) {
        $html.='<tr class="OddRow'.($n===5?' Scratched':'').'"><td class="no">'.$n.'</td><td class="horse"><a>HORSE '.$n.' &amp; CO (IRE)</a></td><td class="jockey"><a><span>Rider '.$n.'</span></a></td><td class="trainer">Trainer '.$n.'</td><td class="barrier">'.$n.'</td><td class="weight">55.5kg</td><td class="last">12x31</td></tr>';
    }
    return $html.'</table><table class="race-title"><tr><th><span>Race 8 - Other race</span></th></tr></table><table class="race-strip-fields"><tr><td class="no">22</td><td class="horse"><a>Also Wrong</a></td></tr></table>';
}
function importAction(PDO $db, int $id, array $field): void {
    $db->beginTransaction();
    try { importCupField($db,$id,$field); $db->commit(); }
    catch (Throwable $e) { $db->rollBack(); throw $e; }
}
$field=parseCupField(fixture(),2026);
check(count($field)===5 && $field[0]['name']==='HORSE 1 & CO (IRE)','Must import only the Cup and decode names');
check($field[0]['jockey']==='Rider 1' && $field[0]['barrier']==='1' && $field[0]['weight']==='55.5kg' && $field[0]['recent_form']==='12x31','Runner details must be parsed');
check($field[4]['scratched']===1,'Scratch status must be recognised');
foreach ([fixture(2025),str_replace('Melbourne Cup (3200','Other Cup (3200',fixture()),str_replace('<td class="no">2</td>','<td class="no">1</td>',fixture()),str_replace('Flemington','Elsewhere',fixture())] as $badHtml) {
    try { parseCupField($badHtml,2026); throw new LogicException('Invalid source accepted'); } catch (RuntimeException $e) {}
}
check(cupDate(2025)->format('Y-m-d')==='2025-11-04' && cupDate(2026)->format('Y-m-d')==='2026-11-03','Cup date must follow the first Tuesday, not a hard-coded date');
$importPot=action($db,$create);
importAction($db,$importPot,$field);
check((bool)fieldSync($db,$importPot)['synced_at'],'Successful imports must record freshness');
for ($i=0;$i<4;$i++) action($db,['action'=>'entry','pot_id'=>$importPot,'participant'=>'Guest '.$i]);
try { action($db,['action'=>'entry','pot_id'=>$importPot,'participant'=>'Over capacity']); throw new LogicException('Scratched horse sold as slot'); } catch (InvalidArgumentException $e) {}
$changed=$field; $changed[0]['scratched']=1;
try { importAction($db,$importPot,$changed); throw new LogicException('Over-capacity refresh accepted'); } catch (InvalidArgumentException $e) {}
check((int)$db->query("SELECT COUNT(*) FROM sweepstake_runners WHERE pot_id=$importPot")->fetchColumn()===5,'Failed import must preserve the saved field');
check((int)$db->query("SELECT COUNT(*) FROM sweepstake_entries WHERE pot_id=$importPot")->fetchColumn()===4,'Refreshing must preserve participants');
check((int)$db->query("SELECT COUNT(*) FROM sweepstake_runner_details d JOIN sweepstake_runners r ON r.id=d.runner_id WHERE r.pot_id=$importPot AND d.scratched=1")->fetchColumn()===1,'Failed refresh must retain existing details');
saveSyncError($db,$importPot,'Updated field has fewer active horses than entries.');
try { action($db,['action'=>'draw','pot_id'=>$importPot]); throw new LogicException('Draw allowed despite unresolved import error'); } catch (InvalidArgumentException $e) {}
importAction($db,$importPot,$field);
action($db,['action'=>'draw','pot_id'=>$importPot]);
check((int)$db->query("SELECT COUNT(*) FROM sweepstake_entries e JOIN sweepstake_runner_details d ON d.runner_id=e.runner_id WHERE e.pot_id=$importPot AND d.scratched=1")->fetchColumn()===0,'Draw must exclude scratched horses');
try { importAction($db,$importPot,$field); throw new LogicException('Drawn pot was refreshed'); } catch (InvalidArgumentException $e) {}
check((int)$db->query("SELECT COUNT(DISTINCT runner_id) FROM sweepstake_entries WHERE pot_id=$importPot")->fetchColumn()===4,'Failed refresh of drawn pot must preserve assignments');
$manualPot=action($db,$create);
importAction($db,$manualPot,$field);
action($db,['action'=>'runners','pot_id'=>$manualPot,'runners'=>"1 Manual A\n2 Manual B\n3 Manual C"]);
check((int)fieldSync($db,$manualPot)['auto_refresh']===0,'Manual field must pause auto refresh');
$db->beginTransaction(); importCupField($db,$manualPot,$field,true); $db->commit();
check((int)fieldSync($db,$manualPot)['auto_refresh']===0 && (int)$db->query("SELECT COUNT(*) FROM sweepstake_runners WHERE pot_id=$manualPot")->fetchColumn()===3,'In-flight automatic refresh must not overwrite a manual field');
check((int)$db->query("SELECT COUNT(*) FROM sweepstake_runner_details d JOIN sweepstake_runners r ON r.id=d.runner_id WHERE r.pot_id=$manualPot")->fetchColumn()===0,'Manual replacement must clear imported details');
importAction($db,$manualPot,$field);
check((int)fieldSync($db,$manualPot)['auto_refresh']===1,'Explicit refresh must resume automatic updates');
saveSyncError($db,$manualPot,'Source unavailable');
check(fieldSync($db,$manualPot)['last_error']==='Source unavailable' && fieldSync($db,$manualPot)['synced_at']!==null,'Source failure must record error without losing last successful sync');
echo "PASS: Cup-only parser, source date validation, runner details, scratchings, duplicate rejection, transactional refresh, capacity protection, frozen draws, manual override, and sync status.\n";
