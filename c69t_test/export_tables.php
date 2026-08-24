<?php

function exportTableDefinitions(bool $displayLabels = true): array
{
    $tables = [
        'solid_waste_logs' => ['Solid Waste', ['log_date', 'log_time', 'amount', 'comments']],
        'recovered_water_pump_logs' => ['Recovered Water Pump', ['log_date', 'log_time', 'start_level', 'stop_level', 'comments']],
        'nozzle_logs' => ['Nozzle', ['log_date', 'log_time', 'nozzle', 'flow', 'pressure', 'min_deg', 'max_deg', 'rpm', 'comments']],
        'tricanter_logs' => ['Tricanter', ['log_date', 'log_time', 'bowl_speed', 'screw_speed', 'bowl_rpm', 'screw_rpm', 'impeller', 'feed_rate', 'torque', 'temp', 'pressure', 'comments']],
        'sample_logs' => ['Sample', ['sample_location', 'log_date', 'log_time', 'nozzle', 'flow', 'mercury', 'solids', 'water', 'wax', 'operator', 'comments']],
        'gas_test_logs' => ['Gas Test', ['log_date', 'log_time', 'device', 'operator', 'location', 'area_details', 'mercury', 'benzene', 'lel', 'h2s', 'o2', 'product_details', 'action_taken']],
        'project_flow_logs' => ['Project Flow', ['log_date', 'log_time', 'total_recovered_oil', 'total_recovered_water', 'total_solid_waste', 'total_tricanter', 'total_nozzle', 'comments']],
        'pump_values_logs' => ['Pump Values', ['log_date', 'log_time', 'suction_pump_1_status', 'suction_pump_2_status', 'suction_pump_3_status', 'suction_pump_2_speed_out', 'suction_pump_2_feedback', 'suction_pump_2_inlet_pressure', 'suction_pump_2_outlet_pressure', 'feed_pump_status', 'feed_pump_speed_out', 'feed_pump_feedback', 'feed_pump_inlet_pressure', 'feed_pump_outlet_pressure', 'booster_pump_status', 'booster_pump_speed_out', 'booster_pump_feedback', 'booster_pump_inlet_pressure', 'booster_pump_outlet_pressure', 'comments']],
        'nitrogen_logs' => ['Nitrogen', ['log_date', 'log_time', 'nitrogen_active', 'trip_status', 'outlet_flow', 'outlet_purity', 'inlet_pressure', 'outlet_pressure', 'pre_heat_temp', 'post_heat_temp', 'interior_o2', 'tank_internal_o2', 'comments']],
    ];

    $definitions = [];
    foreach ($tables as $table => [$label, $columns]) {
        $definitions[$table] = [
            'label' => $displayLabels ? $label : preg_replace('/_logs$/', '', $table),
            'columns' => $columns,
        ];
    }
    return $definitions;
}
