<?php
require_once 'config.php';
require_once __DIR__ . '/export_tables.php';
requireRole(['admin', 'operator', 'viewer']);

$exportTables = exportTableDefinitions(true);
$intervals = [0 => 'All records', 1 => 'Every minute', 5 => 'Every 5 minutes', 10 => 'Every 10 minutes', 15 => 'Every 15 minutes', 30 => 'Every 30 minutes', 60 => 'Every hour', 120 => 'Every 2 hours', 360 => 'Every 6 hours', 720 => 'Every 12 hours'];

function xml_value($value): string
{
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function excel_rows(PDO $pdo, string $table, array $columns, string $start, string $end, int $interval): array
{
    $select = implode(', ', array_map(static fn($column) => "`$column`", $columns));
    $where = [];
    $params = [];
    if ($start !== '') {
        $where[] = "TIMESTAMP(log_date, log_time) >= ?";
        $params[] = $start;
    }
    if ($end !== '') {
        $where[] = "TIMESTAMP(log_date, log_time) <= ?";
        $params[] = $end;
    }
    $sql = "SELECT $select FROM `$table`" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY log_date ASC, log_time ASC, id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($interval <= 0) return $rows;

    $filtered = [];
    $last = null;
    foreach ($rows as $row) {
        $stamp = strtotime(($row['log_date'] ?? '') . ' ' . ($row['log_time'] ?? ''));
        if ($stamp !== false && ($last === null || $stamp >= $last + ($interval * 60))) {
            $filtered[] = $row;
            $last = $stamp;
        }
    }
    return $filtered;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requests = $_POST['tables'] ?? [];
    $chosen = [];
    foreach ($exportTables as $table => $definition) {
        $request = is_array($requests[$table] ?? null) ? $requests[$table] : [];
        if (empty($request['selected'])) continue;
        $startInput = trim((string)($request['start'] ?? ''));
        $endInput = trim((string)($request['end'] ?? ''));
        $startTs = $startInput === '' ? false : strtotime($startInput);
        $endTs = $endInput === '' ? false : strtotime($endInput);
        if (($startInput !== '' && $startTs === false) || ($endInput !== '' && $endTs === false)) {
            $error = 'One of the selected date/time ranges is invalid.';
            break;
        }
        if ($startTs !== false && $endTs !== false && $startTs > $endTs) {
            $error = $definition['label'] . ': the start must be before the end.';
            break;
        }
        $interval = (int)($request['interval'] ?? 0);
        if (!array_key_exists($interval, $intervals)) $interval = 0;
        $chosen[] = [$table, $definition, $startTs === false ? '' : date('Y-m-d H:i:s', $startTs), $endTs === false ? '' : date('Y-m-d H:i:s', $endTs), $interval];
    }

    if (!$error && !$chosen) $error = 'Select at least one table.';
    if (!$error) {
        $filename = 'log_export_' . date('Ymd_His') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<?mso-application progid="Excel.Sheet"?>';
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel">';
        echo '<Styles><Style ss:ID="Default"><Alignment ss:Vertical="Top"/><Font ss:FontName="Calibri" ss:Size="11"/></Style><Style ss:ID="Title"><Font ss:Bold="1" ss:Size="16" ss:Color="#FFFFFF"/><Interior ss:Color="#10273C" ss:Pattern="Solid"/><Alignment ss:Vertical="Center"/></Style><Style ss:ID="Meta"><Font ss:Bold="1" ss:Color="#163A59"/><Interior ss:Color="#DCEAF5" ss:Pattern="Solid"/></Style><Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#163A59" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style><Style ss:ID="Alt"><Interior ss:Color="#EAF2F8" ss:Pattern="Solid"/></Style></Styles>';
        foreach ($chosen as [$table, $definition, $start, $end, $interval]) {
            $rows = excel_rows($pdo, $table, $definition['columns'], $start, $end, $interval);
            $columnCount = count($definition['columns']);
            $mergeAcross = max(0, $columnCount - 1);
            $rangeStart = $start !== '' ? date('d M Y H:i', strtotime($start)) : 'Beginning of records';
            $rangeEnd = $end !== '' ? date('d M Y H:i', strtotime($end)) : 'Latest record';
            $frequency = $intervals[$interval] ?? $intervals[0];
            echo '<Worksheet ss:Name="' . xml_value(substr($definition['label'], 0, 31)) . '"><Table>';
            foreach ($definition['columns'] as $_) echo '<Column ss:AutoFitWidth="1" ss:Width="100"/>';
            echo '<Row ss:Height="28"><Cell ss:StyleID="Title" ss:MergeAcross="' . $mergeAcross . '"><Data ss:Type="String">' . xml_value($definition['label'] . ' Logs') . '</Data></Cell></Row>';
            echo '<Row><Cell ss:StyleID="Meta" ss:MergeAcross="' . $mergeAcross . '"><Data ss:Type="String">Start: ' . xml_value($rangeStart) . '</Data></Cell></Row>';
            echo '<Row><Cell ss:StyleID="Meta" ss:MergeAcross="' . $mergeAcross . '"><Data ss:Type="String">End: ' . xml_value($rangeEnd) . '</Data></Cell></Row>';
            echo '<Row><Cell ss:StyleID="Meta" ss:MergeAcross="' . $mergeAcross . '"><Data ss:Type="String">Frequency: ' . xml_value($frequency) . '</Data></Cell></Row>';
            echo '<Row ss:Height="8"/>';
            echo '<Row ss:StyleID="Header">';
            foreach ($definition['columns'] as $column) echo '<Cell><Data ss:Type="String">' . xml_value(ucwords(str_replace('_', ' ', $column))) . '</Data></Cell>';
            echo '</Row>';
            foreach ($rows as $index => $row) {
                echo '<Row' . ($index % 2 ? ' ss:StyleID="Alt"' : '') . '>';
                foreach ($definition['columns'] as $column) {
                    $value = $row[$column] ?? '';
                    $type = ($value !== '' && is_numeric($value)) ? 'Number' : 'String';
                    echo '<Cell><Data ss:Type="' . $type . '">' . xml_value($value) . '</Data></Cell>';
                }
                echo '</Row>';
            }
            echo '</Table><x:AutoFilter x:Range="R6C1:R' . max(6, count($rows) + 6) . 'C' . $columnCount . '"/><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>6</SplitHorizontal><TopRowBottomPane>6</TopRowBottomPane></WorksheetOptions></Worksheet>';
        }
        echo '</Workbook>';
        exit;
    }
}

[$currentShiftStart, $currentShiftEnd] = get_current_shift_range();
$defaultStart = date('Y-m-d\TH:i', strtotime($currentShiftStart));
$defaultEnd = date('Y-m-d\TH:i', strtotime($currentShiftEnd));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" href="c69t.ico" type="image/x-icon"><title>Export to Excel</title><link rel="stylesheet" href="indexStyle.css"><link rel="stylesheet" href="excel_download.css"></head><body><?php require_once 'nav.php'; ?><main class="export-shell"><section class="export-card"><div class="section-kicker">data export</div><h1>Export to Excel</h1><p class="hint">Select one or more tables. Each table becomes a formatted worksheet and can use its own frequency and date/time range.</p><?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?><form method="post"><div class="export-grid"><?php foreach ($exportTables as $table => $definition): ?><div class="export-row"><label class="table-choice"><input type="checkbox" name="tables[<?= h($table) ?>][selected]" value="1"> <?= h($definition['label']) ?></label><label>Start<input type="datetime-local" name="tables[<?= h($table) ?>][start]" value="<?= h($defaultStart) ?>"></label><label>End<input type="datetime-local" name="tables[<?= h($table) ?>][end]" value="<?= h($defaultEnd) ?>"></label><label>Frequency<select name="tables[<?= h($table) ?>][interval]"><?php foreach ($intervals as $minutes => $label): ?><option value="<?= $minutes ?>"><?= h($label) ?></option><?php endforeach; ?></select></label></div><?php endforeach; ?></div><p><button class="btn" type="submit">Export to Excel</button></p></form></section></main></body></html>
