<?php
require_once __DIR__ . "/functions.php";

$message = $_GET["msg"] ?? "";

$dateValue = default_form_date();
$timeValue = default_form_time();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $sampleLocation = trim($_POST["sampleLocation"] ?? "");
    $date = format_date_ddmmyyyy($_POST["date"] ?? "");
    $time = trim($_POST["time"] ?? "");
    $nzl = trim($_POST["nzl"] ?? "");
    $flow = trim($_POST["flow"] ?? "");
    $mercury = trim($_POST["mercury"] ?? "");
    $solids = trim($_POST["solids"] ?? "");
    $water = trim($_POST["water"] ?? "");
    $wax = trim($_POST["wax"] ?? "");
    $operator = trim($_POST["operator"] ?? "");
    $comments = trim($_POST["comments"] ?? "manual_web_entry");

    if (
        $date === "" ||
        $time === "" ||
        $operator === "" ||
        $sampleLocation === ""
    ) {
        $resultMessage = "Please complete Date, Time, Operator, and Location.";
    } else {
        $fields = [
            "Sample Location" => $sampleLocation,
            "Date" => $date,
            "Time" => $time,
            "Nozzle" => $nzl,
            "Flow" => $flow,
            "Mercury" => $mercury,
            "Solids" => $solids,
            "Water" => $water,
            "Wax" => $wax,
            "Operator" => $operator,
            "Comments" => $comments,
        ];

        [$ok, $result] = write_parser_file("sample", $fields, get_data_dir());
        $resultMessage = $ok ? "Sample data file created: " . $result : $result;
    }

    header("Location: sample.php?msg=" . urlencode($resultMessage));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sample Entry</title>

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
            <h1>Sample Entry</h1>
            <!-- <p class="form-sub">Creates a file in this format: [gas_test]</p> -->

            <form method="post" action="">

                <label for="sampleLocation">Sample Location</label>
                <input list="sampleLocation_list" id="sampleLocation" name="sampleLocation" required>

                <datalist id="sampleLocation_list">
                    <option value="Suction Pump 1">
                    <option value="Filter Skid 1">
                    <option value="Filter Skid 2">
                    <option value="Suction Pump 2">
                    <option value="Hairpin">
                    <option value="Tricanter Feed">
                    <option value="Recovered Oil">
                    <option value="Recovered Water">
                    <option value="LILO Oil">
                    <option value="Frac Tank 1">
                    <option value="Frac Tank 2">
                    <option value="Frac Tank 3">

                </datalist>


                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?php echo h($dateValue); ?>" required>

                <label for="time">Time</label>
                <input type="time" id="time" name="time" value="<?php echo h($timeValue); ?>" required>

                <label for="nzl">Nozzle</label>
                <input type="text" id="nzl" name="nzl">

                <label for="flow">Flow</label>
                <input type="text" id="flow" name="flow">

                <label for="mercury">Mercury</label>
                <input type="text" id="mercury" name="mercury">

                <label for="solids">Solids</label>
                <input type="text" id="solids" name="solids">

                <label for="water">Water</label>
                <input type="text" id="water" name="water">

                <label for="wax">Wax</label>
                <input type="text" id="wax" name="wax">

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

                <label for="comments">Comments</label>
                <textarea id="comments" name="comments"></textarea>

                <button type="submit">Save Sample Entry</button>
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