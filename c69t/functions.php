<?php

date_default_timezone_set('Australia/Adelaide');

function clean_value($value) {
    return trim(str_replace(["\r", "\n"], " ", (string)$value));
}

function format_date_ddmmyyyy($value) {
    $value = trim((string)$value);

    if ($value === "") {
        return "";
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return "";
    }

    return date("d/m/Y", $ts);
}

function get_data_dir() {
    return __DIR__ . "/hmi_logs";
}

function write_parser_file($sectionName, $fields, $targetDir) {
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return [false, "Failed to create target folder."];
        }
    }

    $stamp = date("Ymd_His");
    $unique = substr(str_replace(".", "", (string)microtime(true)), -6);
    $fileName = $sectionName . "_" . $stamp . "_" . $unique . ".txt";
    $filePath = rtrim($targetDir, "/") . "/" . $fileName;

    $lines = [];
    $lines[] = "[" . $sectionName . "]";

    foreach ($fields as $key => $value) {
        $lines[] = $key . "=" . clean_value($value);
    }

    $content = implode(PHP_EOL, $lines) . PHP_EOL;

    if (file_put_contents($filePath, $content) === false) {
        return [false, "Failed to write file."];
    }

    return [true, $fileName];
}

function default_form_date() {
    $dt = new DateTime('now', new DateTimeZone('Australia/Adelaide'));
    return $dt->format('Y-m-d');
}

function default_form_time() {
    $dt = new DateTime('now', new DateTimeZone('Australia/Adelaide'));
    return $dt->format('H:i');
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}