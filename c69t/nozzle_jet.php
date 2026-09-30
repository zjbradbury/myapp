<?php
declare(strict_types=1);

// Supplied performance table takes precedence over the approximate plotted curves.
// Each row contains flow (m³/h) and effective jet radius (m) at 6, 8, 10, 12 bar.
function nozzleJetReference(): array
{
    return [
        16 => [[25, 29, 32, 35], [23, 26, 28, 29]],
        18 => [[31, 35, 39, 43], [24, 27, 29, 30]],
        20 => [[38, 44, 49, 54], [24, 26, 28, 30]],
        22 => [[42.5, 49, 55, 60], [25, 27, 29, 31]],
        24 => [[50, 58, 65, 71], [26, 28, 30, 32]],
        26 => [[56, 64.5, 72, 79], [27, 29, 31, 33]],
        28 => [[63, 72, 80.5, 88], [28, 30, 32, 34]],
        30 => [[68, 78, 86.5, 95], [29, 31, 33, 35]],
    ];
}

function nozzleJetInterpolate(float $value, array $axis, array $radii): ?float
{
    if (!is_finite($value) || $value < $axis[0] || $value > $axis[3]) return null;
    for ($i = 1; $i < 4; $i++) {
        if ($value <= $axis[$i]) {
            return $radii[$i - 1] + ($radii[$i] - $radii[$i - 1])
                * ($value - $axis[$i - 1]) / ($axis[$i] - $axis[$i - 1]);
        }
    }
    return null;
}

function nozzleJetEstimate(int $size, $flow, $pressure): array
{
    $row = nozzleJetReference()[$size] ?? null;
    $result = ['maximum_m' => $row ? $row[1][3] : null, 'actual_m' => null,
        'status' => 'Select a nozzle size to show jet reach.'];
    if (!$row) return $result;
    if (!is_numeric($flow) || !is_numeric($pressure) || !is_finite((float)$flow)
        || !is_finite((float)$pressure) || $flow < 0 || $pressure < 0) {
        $result['status'] = 'Latest log has missing or invalid flow / pressure.';
    } elseif ((float)$flow === 0.0 || (float)$pressure === 0.0) {
        $result['actual_m'] = 0;
        $result['status'] = 'Zero flow or pressure — no jet reach.';
    } else {
        $byFlow = nozzleJetInterpolate((float)$flow, $row[0], $row[1]);
        $byPressure = nozzleJetInterpolate((float)$pressure, [6, 8, 10, 12], $row[1]);
        if ($byFlow === null || $byPressure === null) {
            $result['status'] = 'Estimate unavailable: readings outside reference range (6–12 bar; '
                . $row[0][0] . '–' . $row[0][3] . ' m³/h).';
        } else {
            $result['actual_m'] = min($byFlow, $byPressure);
            $result['status'] = 'Estimated from latest flow and pressure.';
        }
    }
    return $result;
}
