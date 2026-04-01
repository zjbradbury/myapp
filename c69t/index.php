<?php
require_once __DIR__ . "/functions.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Data Entry</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #0f1115;
            color: #ffffff;
        }

        .wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px 16px 30px;
        }

        .header {
            background: #171a21;
            border: 1px solid #2b313d;
            border-radius: 18px;
            padding: 22px;
            margin-bottom: 20px;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 30px;
        }

        p {
            margin: 0;
            color: #b8c0cc;
            line-height: 1.5;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
        }

        .card {
            background: #171a21;
            border: 1px solid #2b313d;
            border-radius: 18px;
            padding: 20px;
        }

        .card h2 {
            margin-top: 0;
        }

        .card p {
            margin-bottom: 18px;
        }

        .card a {
            display: inline-block;
            text-decoration: none;
            background: #2a3749;
            color: #ffffff;
            border: 1px solid #41526d;
            padding: 12px 16px;
            border-radius: 12px;
            font-weight: bold;
        }

        .card a:hover {
            background: #33445b;
        }

        @media (max-width: 640px) {
            .wrap {
                padding: 14px 12px 24px;
            }

            h1 {
                font-size: 24px;
            }

            .card a {
                display: block;
                text-align: center;
            }
        }
    </style>
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
                <p>Enter one amount. Start Level = amount entered, Stop Level = 0.</p>
                <a href="solid_waste.php">Open Solid Waste Entry</a>
            </div>
        </div>
    </div>
</body>
</html>