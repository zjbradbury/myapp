<?php
require_once __DIR__ . "/functions.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Data Entry</title>

    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="wrap">
        <div class="header">
            <h1>Manual Process Data Entry</h1>
            <p>Select a data entry page below.</p>
        </div>

        <div class="grid">
            <div class="card">
                <h2>Nozzle</h2>
                <p>Enter nozzle, flow, pressure, min/max degree, RPM, and comments.</p>
                <a href="nozzle.php">Open Nozzle Entry</a>
            </div>

            <div class="card">
                <h2>Tricanter</h2>
                <p>Enter bowl speed, screw speed, RPM, feed rate, torque, temp, pressure, and comments.</p>
                <a href="tricanter.php">Open Tricanter Entry</a>
            </div>

            <div class="card">
                <h2>Solid Waste</h2>
                <p>Enter one amount of solid waste dumped.</p>
                <a href="solid_waste.php">Open Solid Waste Entry</a>
            </div>

            <div class="card">
                <h2>Gas Test</h2>
                <p>Enter gas test results including device, operator, location, readings, product details, and actions.
                </p>
                <a href="gas_test.php">Open Gas Test Entry</a>
            </div>

            <div class="card">
                <h2>Sample</h2>
                <p>Enter sample results including operator, location, readings.
                </p>
                <a href="sample.php">Open Sample Entry</a>
            </div>

        </div>
    </div>
</body>

</html>