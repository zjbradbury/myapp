<?php
require_once __DIR__ . '/../nozzle_jet.php';
function check($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
foreach (nozzleJetReference() as $size => [$flows, $radii]) {
    foreach ([6, 8, 10, 12] as $i => $pressure) {
        $result = nozzleJetEstimate($size, $flows[$i], $pressure);
        check(abs($result['actual_m'] - $radii[$i]) < 0.00001, "Table point $size / $pressure");
        check($result['maximum_m'] === $radii[3], 'Maximum radius');
    }
}
check(nozzleJetEstimate(22, 45.75, 7)['actual_m'] === 26.0, 'Midpoint interpolation');
check(nozzleJetEstimate(22, 42.5, 12)['actual_m'] === 25.0, 'Flow limits estimate');
check(nozzleJetEstimate(22, 60, 6)['actual_m'] === 25.0, 'Pressure limits estimate');
foreach ([[null, 8], ['', 8], [-1, 8], [50, -1], [50, 5], [50, 13], [41, 8], [61, 8], [INF, 8]] as [$flow, $pressure]) {
    check(nozzleJetEstimate(22, $flow, $pressure)['actual_m'] === null, 'Invalid / out of range reading');
}
check(nozzleJetEstimate(22, 0, 8)['actual_m'] === 0, 'Zero flow');
check(nozzleJetEstimate(22, 50, 0)['actual_m'] === 0, 'Zero pressure');
check(nozzleJetEstimate(17, 50, 8)['maximum_m'] === null, 'Unsupported size');
echo "Nozzle jet tests passed (32 reference points, interpolation and edge cases).\n";
