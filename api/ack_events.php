<?php
// Server-Sent Events endpoint for real-time acknowledgment updates
// Works on shared hosting (Hostinger) without long-running daemons

// Critical headers for SSE and to avoid buffering on shared hosting
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // nginx proxies
header('Content-Encoding: none');
header('Access-Control-Allow-Origin: *');

// Try to disable PHP output buffering & compression
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_flush(); }
@ob_implicit_flush(true);
@set_time_limit(0);

$markerDir = __DIR__ . '/logs';
$markerFile = $markerDir . '/last_ack.json';
if (!is_dir($markerDir)) { @mkdir($markerDir, 0775, true); @chmod($markerDir, 0775); }
if (!file_exists($markerFile)) {
    file_put_contents($markerFile, json_encode(['t' => time()]));
    @chmod($markerFile, 0664);
}

// Try to keep the connection open for up to ~30 seconds and then let client reconnect
$start = time();
$lastSent = 0;
echo "retry: 5000\n\n"; // advise client to retry after 5s on disconnect
@flush();
while (time() - $start < 30) {
    clearstatcache();
    $mtime = filemtime($markerFile);
    if ($mtime && $mtime > $lastSent) {
        $payload = @file_get_contents($markerFile);
        if (!$payload) { $payload = json_encode(['t' => time()]); }
        echo "event: ack\n";
        echo "data: $payload\n\n";
        @flush();
        $lastSent = $mtime;
    }
    // heartbeat comment keeps some proxies from buffering
    echo ": hb\n\n";
    @flush();
    usleep(1000000); // 1s
}

// Send a keep-alive comment before ending (optional)
echo ": keep-alive\n\n";
@flush();


