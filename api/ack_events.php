<?php
// Server-Sent Events endpoint for real-time acknowledgment updates
// Works on shared hosting (Hostinger) without long-running daemons

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

$markerDir = __DIR__ . '/logs';
$markerFile = $markerDir . '/last_ack.json';
if (!is_dir($markerDir)) {
    @mkdir($markerDir, 0775, true);
}
if (!file_exists($markerFile)) {
    file_put_contents($markerFile, json_encode(['t' => time()]));
}

// Try to keep the connection open for up to ~30 seconds and then let client reconnect
$start = time();
$lastSent = 0;
while (time() - $start < 30) {
    clearstatcache();
    $mtime = filemtime($markerFile);
    if ($mtime && $mtime > $lastSent) {
        $payload = @file_get_contents($markerFile);
        if (!$payload) { $payload = json_encode(['t' => time()]); }
        echo "event: ack\n";
        echo "data: $payload\n\n";
        @ob_flush(); @flush();
        $lastSent = $mtime;
    }
    usleep(500000); // 0.5s
}

// Send a keep-alive comment before ending (optional)
echo ": keep-alive\n\n";
@ob_flush(); @flush();


