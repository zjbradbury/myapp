<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
*/
$m3uSource = 'http://cf.business-cdn-8k.su/get.php?username=tpowell&password=ed491c02cb&type=m3u_plus';

/*
|--------------------------------------------------------------------------
| LOAD FILE SAFE
|--------------------------------------------------------------------------
*/
function loadTextFile($source) {
    if (!is_string($source) || trim($source) === '') return '';

    if (filter_var($source, FILTER_VALIDATE_URL)) {
        return @file_get_contents($source) ?: '';
    }

    if (!file_exists($source)) return '';

    return @file_get_contents($source) ?: '';
}

/*
|--------------------------------------------------------------------------
| PARSE M3U
|--------------------------------------------------------------------------
*/
function parseAttributes($text) {
    $attrs = [];
    preg_match_all('/([a-zA-Z0-9\-_]+)="([^"]*)"/', $text, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $attrs[$match[1]] = $match[2];
    }
    return $attrs;
}

function parseM3U($content) {
    $channels = [];
    $lines = preg_split('/\r\n|\r|\n/', $content);

    for ($i = 0; $i < count($lines); $i++) {
        $line = trim($lines[$i]);

        if (stripos($line, '#EXTINF:') === 0) {
            $attrs = parseAttributes($line);

            $name = '';
            $commaPos = strrpos($line, ',');
            if ($commaPos !== false) {
                $name = trim(substr($line, $commaPos + 1));
            }

            $url = '';
            for ($j = $i + 1; $j < count($lines); $j++) {
                $nextLine = trim($lines[$j]);
                if ($nextLine === '' || strpos($nextLine, '#') === 0) continue;

                $url = $nextLine;
                $i = $j;
                break;
            }

            if ($url !== '') {
                $channels[] = [
                    'id' => md5($name . $url),
                    'name' => $name ?: 'Unnamed',
                    'url' => $url,