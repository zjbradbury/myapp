<?php
require_once __DIR__ . "/functions.php";

$message = $_GET["msg"] ?? "";

$dateValue = default_form_date();
$timeValue = default_form_time();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $date = format_date_ddmmyyyy($_POST["date"] ?? "");
    $time = trim($_POST["time"] ?? "");
    $device = trim($_POST["device"] ?? "");
    $operator = trim($_POST["operator"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $areaDetails = trim($_POST["area_details"] ?? "");
    $mercury = trim($_POST["mercury"] ?? "");
    $benzene = trim($_POST["benzene"] ?? "");
    $lel = trim($_POST["lel"] ?? "");
    $h2s = trim($_POST["h2s"] ?? "");
    $o2 = trim($_POST["o2"] ?? "");
    $productDetails = trim($_POST["product_details"] ?? "");
    $actions = trim($_POST["actions"] ?? "");

    if (
        $date === "" ||
        $time === "" ||
        $device === "" ||
        $operator === "" ||
        $location === ""
    ) {
        $resultMessage = "Please complete Date, Time, Device, Operator, and Location.";
    } else {
        $fields = [
            "Date" => $date,
            "Time" => $time,
            "Device" => $device,
            "Operator" => $operator,
            "Location" => $location,
            "Area_details" => $areaDetails,
            "Mercury" => $mercury,
            "Benzene" => $benzene,
            "LEL" => $lel,
            "H2S" => $h2s,
            "O2" => $o2,
            "Product_details" => $productDetails,
            "Actions" => $actions
        ];

        [$ok, $result] = write_parser_file("gas_test", $fields, get_data_dir());
        $resultMessage = $ok ? "Gas test file created: " . $result : $result;
    }

    header("Location: gas_test.php?msg=" . urlencode($resultMessage));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gas Test Entry</title>

    <link rel="shortcut icon" href="favicon.ico">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="wrap-narrow">
        <div class="topbar">
            <a href="index.php">← Back to Home</a>
        </div>

        <?php if ($message !== ""): ?>
        <div class="message"><?php echo h($message); ?></div>
        <?php endif; ?>

        <div class="card">
            <h1>Gas Test Entry</h1>
            <!-- <p class="form-sub">Creates a file in this format: [gas_test]</p> -->

            <form method="post" action="">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?php echo h($dateValue); ?>" required>

                <label for="time">Time</label>
                <input type="time" id="time" name="time" value="<?php echo h($timeValue); ?>" required>

                <label for="device">Device Used To Conduct Test</label>
                <input list="device_list" id="device" name="device" required>

                <datalist id="device_list">
                    <option value="Benzene Meter">
                    <option value="Jerome Meter (Mercury)">
                    <option value="PGM 5 Gas Detector">
                </datalist>

                <label for="operator">Person Conducting Test</label>
                <input list="operator_list" id="operator" name="operator" required>

                <datalist id="operator_list">
                    <option value="Aaron">
                    <option value="Benney">
                    <option value="Brent">
                    <option value="Bricky">
                    <option value="Cain">
                    <option value="Cody">
                    <option value="Curtis">
                    <option value="Dan">
                    <option value="Jared">
                    <option value="Jeff">
                    <option value="Mitch">
                    <option value="Pieter">
                    <option value="Roshel">
                    <option value="Sam">
                    <option value="Sheldon">
                    <option value="Silver">
                    <option value="Stewy">
                    <option value="Tate">
                    <option value="Zack">

                </datalist>

                <label for="location">Test Location</label>
                <input list="location_list" id="location" name="location" required>

                <datalist id="location_list">
                    <option value="Filter Skid">
                    <option value="Frac Tanks">
                    <option value="Solids Bin">
                    <option value="Solids Waste Chute">
                    <option value="Strainers">
                    <option value="Tricanter">
                </datalist>

                <label for="area_details">Details of Area Tested</label>
                <textarea id="area_details" name="area_details"></textarea>

                <label for="mercury">Mercury</label>
                <input type="text" id="mercury" name="mercury">

                <label for="benzene">Benzene</label>
                <input type="text" id="benzene" name="benzene">

                <label for="lel">LEL</label>
                <input type="text" id="lel" name="lel">

                <label for="h2s">H2S</label>
                <input type="text" id="h2s" name="h2s">

                <label for="o2">O2</label>
                <input type="text" id="o2" name="o2">

                <label for="product_details">Details of Product Found</label>
                <textarea id="product_details" name="product_details"></textarea>

                <label for="actions">Actions</label>
                <textarea id="actions" name="actions"></textarea>

                <button type="submit">Save Gas Test Entry</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll("form").forEach(function(form) {
            form.addEventListener("submit", function() {
                const btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = "Saving...";
                }
            });
        });
    });
    </script>
</body>

</html>