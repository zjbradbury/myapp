<?php
require_once __DIR__ . "/functions.php";

$message = $_GET["msg"] ?? "";

$dateValue = default_form_date();
$timeValue = default_form_time();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $date = format_date_ddmmyyyy($_POST["date"] ?? "");
    $time = trim($_POST["time"] ?? "");
    $amount = trim($_POST["amount"] ?? "");
    $comments = trim($_POST["comments"] ?? "manual_web_entry");

    if ($date === "" || $time === "" || $amount === "") {
        $resultMessage = "Please complete all Solid Waste fields.";
    } else {
        $fields = [
            "Date" => $date,
            "Time" => $time,
            "Start Level" => $amount,
            "Stop Level" => "0",
            "Comments" => $comments
        ];

        [$ok, $result] = write_parser_file("SOLID_WASTE", $fields, get_data_dir());
        $resultMessage = $ok ? "Solid Waste file created: " . $result : $result;
    }

    header("Location: solid_waste.php?msg=" . urlencode($resultMessage));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solid Waste Entry</title>
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
            <h1>Solid Waste Entry</h1>
            <!-- <p class="form-sub">Creates a file in your exact SOLID_WASTE parser format.</p> -->

            <form method="post" action="">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?php echo h($dateValue); ?>" required>

                <label for="time">Time</label>
                <input type="time" id="time" name="time" value="<?php echo h($timeValue); ?>" required>

                <label for="amount">Amount</label>
                <input type="number" step="any" id="amount" name="amount" required>

                <!-- <div class="hint">
                    This writes Start Level = amount entered and Stop Level = 0.
                </div> -->

                <label for="comments">Comments</label>
                <textarea id="comments" name="comments">manual_web_entry</textarea>

                <button type="submit">Save Solid Waste Entry</button>
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