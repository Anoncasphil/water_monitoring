<?php
require_once 'config/database.php';

class SerialHandler {
    private $port;
    private $db;

    public function __construct($port = 'COM3', $baudRate = 9600) {
        $this->port = $port;
        $this->db = Database::getInstance();
        
        // Open serial port
        $this->openPort($baudRate);
    }

    private function openPort($baudRate) {
        // Windows specific command to open COM port
        exec("mode {$this->port}: BAUD={$baudRate} PARITY=N DATA=8 STOP=1 to=off dtr=on rts=on");
    }

    public function sendRelayCommand($relay, $state) {
        $command = "RELAY:{$relay},{$state}\n";
        $fp = fopen($this->port, 'w');
        fwrite($fp, $command);
        fclose($fp);
    }

    public function readData() {
        $fp = fopen($this->port, 'r');
        $data = '';
        
        // Read until we get a complete line
        while (!feof($fp)) {
            $line = fgets($fp);
            if (strpos($line, 'DATA:') !== false) {
                $data = $line;
                break;
            }
        }
        
        fclose($fp);
        return $data;
    }

    public function parseAndSaveData($data) {
        if (strpos($data, 'DATA:') !== false) {
            $values = explode(':', $data)[1];
            list($turbidity, $tds) = explode(',', $values);
            
            try {
                $conn = $this->db->getConnection();
                $stmt = $conn->prepare("INSERT INTO water_readings (turbidity, tds) VALUES (?, ?)");
                $stmt->bind_param("dd", $turbidity, $tds);
                $stmt->execute();
                return true;
            } catch (Exception $e) {
                error_log("Database error: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }
}

// Handle relay control requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $relay = isset($_POST['relay']) ? intval($_POST['relay']) : null;
    $state = isset($_POST['state']) ? intval($_POST['state']) : null;

    if ($relay !== null && $state !== null && $relay >= 1 && $relay <= 4) {
        $serial = new SerialHandler();
        $serial->sendRelayCommand($relay, $state);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Invalid parameters']);
    }
    exit;
}

// Handle sensor data reading
$serial = new SerialHandler();
$data = $serial->readData();
if ($data) {
    $serial->parseAndSaveData($data);
}
?> 