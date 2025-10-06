<?php
// Simple Ratchet WebSocket broadcast server for acknowledgments
require __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class AckBroadcastServer implements MessageComponentInterface {
    /** @var \SplObjectStorage */
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // Expect JSON: { type: 'ack', payload: { alert_type: 'tds', at: 123456789 } }
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }
}

$port = getenv('WS_PORT') ?: 8081; // set WS_PORT env or default 8081

$server = Ratchet\App::factory('0.0.0.0', (int)$port);
$server->route('/ack', new AckBroadcastServer(), ['*']);
$server->run();


