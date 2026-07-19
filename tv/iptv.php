<?php

$playlistUrl = 'http://bradbury.services/get.php'
    . '?username=' . urlencode('tpowell')
    . '&password=' . urlencode('hcmrc61pcx')
    . '&type=m3u_plus'
    . '&output=m3u8';

$context = stream_context_create([
    'http' => [
        'timeout' => 20,
        'user_agent' => 'Mozilla/5.0 IPTV Web Player',
    ],
]);

$playlist = @file_get_contents($playlistUrl, false, $context);

if ($playlist === false) {
    http_response_code(502);
    exit('Unable to download IPTV playlist.');
}

header('Content-Type: text/plain; charset=UTF-8');
echo $playlist;