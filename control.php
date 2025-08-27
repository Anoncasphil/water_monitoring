<?php
// Simple control panel for toggling uploads and inserting water_readings
// Requires: config/database.php

require_once __DIR__ . '/config/database.php';

date_default_timezone_set('Asia/Manila');

function ensureSettingsTable(mysqli $conn): void {
	$conn->query(
		"CREATE TABLE IF NOT EXISTS `system_settings` (
			`id` INT(11) NOT NULL AUTO_INCREMENT,
			`name` VARCHAR(100) NOT NULL,
			`value` VARCHAR(255) NOT NULL,
			`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uniq_name` (`name`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
	);
}

function getSetting(mysqli $conn, string $name, string $default = '0'): string {
	$stmt = $conn->prepare("SELECT `value` FROM `system_settings` WHERE `name` = ? LIMIT 1");
	$stmt->bind_param('s', $name);
	$stmt->execute();
	$result = $stmt->get_result();
	$row = $result ? $result->fetch_assoc() : null;
	$stmt->close();
	return $row ? (string)$row['value'] : $default;
}

function setSetting(mysqli $conn, string $name, string $value): void {
	$stmt = $conn->prepare(
		"INSERT INTO `system_settings` (`name`, `value`) VALUES (?, ?) 
		ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
	);
	$stmt->bind_param('ss', $name, $value);
	$stmt->execute();
	$stmt->close();
}

function getDataManipulationSettings(mysqli $conn): array {
	return [
		'ph_offset' => (float)getSetting($conn, 'ph_offset', '0.0'),
		'ph_multiplier' => (float)getSetting($conn, 'ph_multiplier', '1.0'),
		'turbidity_offset' => (float)getSetting($conn, 'turbidity_offset', '0.0'),
		'turbidity_multiplier' => (float)getSetting($conn, 'turbidity_multiplier', '1.0'),
		'tds_offset' => (float)getSetting($conn, 'tds_offset', '0.0'),
		'tds_multiplier' => (float)getSetting($conn, 'tds_multiplier', '1.0'),
		'temperature_offset' => (float)getSetting($conn, 'temperature_offset', '0.0'),
		'temperature_multiplier' => (float)getSetting($conn, 'temperature_multiplier', '1.0'),
		'manipulation_enabled' => getSetting($conn, 'manipulation_enabled', '0') === '1'
	];
}

function applyDataManipulation(float $value, float $offset, float $multiplier): float {
	return ($value + $offset) * $multiplier;
}

function parseRange(string $range): array {
	// Expecting formats like "3-4", "10-20"
	$parts = array_map('trim', explode('-', $range));
	$min = isset($parts[0]) ? (float)$parts[0] : 0.0;
	$max = isset($parts[1]) ? (float)$parts[1] : $min;
	if ($max < $min) {
		[$min, $max] = [$max, $min];
	}
	return [$min, $max];
}

function randomInRange(float $min, float $max, int $decimals = 2): float {
	if ($max <= $min) {
		return round($min, $decimals);
	}
	$rand = $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
	return round($rand, $decimals);
}

function getRangesFromCategories(string $phCategory, string $turbidityCategory, string $tdsCategory): array {
	// Map categories to numeric ranges
	switch ($phCategory) {
		case 'low':
			$phRange = '3-6';
			break;
		case 'neutral':
			$phRange = '6-7';
			break;
		case 'high':
			$phRange = '7-9';
			break;
		default:
			$phRange = '6-7';
	}

	switch ($turbidityCategory) {
		case 'clean':
			$turbidityRange = '1-2';
			break;
		case 'turbid':
			$turbidityRange = '30-100';
			break;
		default:
			$turbidityRange = '2-10';
	}

	switch ($tdsCategory) {
		case 'low':
			$tdsRange = '0-150';
			break;
		case 'high':
			$tdsRange = '150-1000';
			break;
		default:
			$tdsRange = '0-150';
	}

	return [$phRange, $turbidityRange, $tdsRange];
}

$messages = [];
$errors = [];

try {
	$db = Database::getInstance();
	$conn = $db->getConnection();

	// Ensure settings table exists for the uploads toggle
	ensureSettingsTable($conn);

	// Handle actions
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$action = isset($_POST['action']) ? (string)$_POST['action'] : '';

		if ($action === 'toggle_uploads') {
			$desired = isset($_POST['uploads_disabled']) && $_POST['uploads_disabled'] === '1' ? '1' : '0';
			setSetting($conn, 'uploads_disabled', $desired);
			$messages[] = $desired === '1' ? 'Uploads have been disabled.' : 'Uploads have been enabled.';
		}

		if ($action === 'save_manipulation_settings') {
			$manipulationEnabled = isset($_POST['manipulation_enabled']) && $_POST['manipulation_enabled'] === '1' ? '1' : '0';
			setSetting($conn, 'manipulation_enabled', $manipulationEnabled);
			
			// Debug: Log what we received
			error_log("SAVE MANIPULATION SETTINGS - Received POST data:");
			error_log("  manipulation_enabled: " . (isset($_POST['manipulation_enabled']) ? $_POST['manipulation_enabled'] : 'NOT SET'));
			error_log("  manipulate_ph: " . (isset($_POST['manipulate_ph']) ? $_POST['manipulate_ph'] : 'NOT SET'));
			error_log("  manipulate_turbidity: " . (isset($_POST['manipulate_turbidity']) ? $_POST['manipulate_turbidity'] : 'NOT SET'));
			error_log("  manipulate_tds: " . (isset($_POST['manipulate_tds']) ? $_POST['manipulate_tds'] : 'NOT SET'));
			error_log("  manipulate_temperature: " . (isset($_POST['manipulate_temperature']) ? $_POST['manipulate_temperature'] : 'NOT SET'));
			
			// Save the selected ranges
			$ranges = ['ph_range', 'turbidity_range', 'tds_range', 'temperature_range'];
			foreach ($ranges as $range) {
				$value = isset($_POST[$range]) ? (string)$_POST[$range] : '';
				if (!empty($value)) {
					setSetting($conn, $range, $value);
					error_log("  Saved $range: $value");
				}
			}
			
			// Save individual manipulation settings for each sensor
			$sensors = ['manipulate_ph', 'manipulate_turbidity', 'manipulate_tds', 'manipulate_temperature'];
			foreach ($sensors as $sensor) {
				$value = isset($_POST[$sensor]) && $_POST[$sensor] === '1' ? '1' : '0';
				setSetting($conn, $sensor, $value);
				error_log("  Saved $sensor: $value");
			}
			
			error_log("SAVE MANIPULATION SETTINGS - Completed");
			$messages[] = 'Data manipulation settings saved successfully.';
		}

		if ($action === 'insert_reading') {
			// Expected inputs: ph_range, turbidity_range, tds_range, temperature, in_value
			$phRangeStr = isset($_POST['ph_range']) ? (string)$_POST['ph_range'] : '';
			$turbidityRangeStr = isset($_POST['turbidity_range']) ? (string)$_POST['turbidity_range'] : '';
			$tdsRangeStr = isset($_POST['tds_range']) ? (string)$_POST['tds_range'] : '';
			$temperature = isset($_POST['temperature']) && $_POST['temperature'] !== '' ? (float)$_POST['temperature'] : 25.0;
			$inValue = isset($_POST['in_value']) && $_POST['in_value'] !== '' ? (float)$_POST['in_value'] : 0.0;

			[$phMin, $phMax] = parseRange($phRangeStr);
			[$turMin, $turMax] = parseRange($turbidityRangeStr);
			[$tdsMin, $tdsMax] = parseRange($tdsRangeStr);

			$ph = randomInRange($phMin, $phMax, 2);
			$turbidity = randomInRange($turMin, $turMax, 2);
			$tds = randomInRange($tdsMin, $tdsMax, 2);

			// Apply data manipulation if enabled
			$manipulationSettings = getDataManipulationSettings($conn);
			if ($manipulationSettings['manipulation_enabled']) {
				$ph = applyDataManipulation($ph, $manipulationSettings['ph_offset'], $manipulationSettings['ph_multiplier']);
				$turbidity = applyDataManipulation($turbidity, $manipulationSettings['turbidity_offset'], $manipulationSettings['turbidity_multiplier']);
				$tds = applyDataManipulation($tds, $manipulationSettings['tds_offset'], $manipulationSettings['tds_multiplier']);
				$temperature = applyDataManipulation($temperature, $manipulationSettings['temperature_offset'], $manipulationSettings['temperature_multiplier']);
			}

			$stmt = $conn->prepare("INSERT INTO water_readings (turbidity, tds, ph, temperature, `in`) VALUES (?, ?, ?, ?, ?)");
			$stmt->bind_param('ddddd', $turbidity, $tds, $ph, $temperature, $inValue);
			$ok = $stmt->execute();
			$stmt->close();

			$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
			if ($isAjax) {
				header('Content-Type: application/json');
				echo json_encode([
					'success' => $ok,
					'data' => [
						'ph' => $ph,
						'turbidity' => $turbidity,
						'tds' => $tds,
						'temperature' => $temperature,
						'in' => $inValue,
					]
				]);
				exit;
			}

			if ($ok) {
				$messages[] = 'Reading inserted successfully.';
			} else {
				$errors[] = 'Failed to insert reading.';
			}
		}

		if ($action === 'test_manipulation') {
			// Simple test to verify manipulation settings are working
			$manipulationEnabled = getSetting($conn, 'manipulation_enabled', '0');
			$manipulationRunning = getSetting($conn, 'manipulation_running', '0');
			$manipulatePh = getSetting($conn, 'manipulate_ph', '0');
			$phRange = getSetting($conn, 'ph_range', '6-7');
			
			header('Content-Type: application/json');
			echo json_encode([
				'success' => true,
				'test_results' => [
					'manipulation_enabled' => $manipulationEnabled,
					'manipulation_running' => $manipulationRunning,
					'manipulate_ph' => $manipulatePh,
					'ph_range' => $phRange,
					'timestamp' => date('Y-m-d H:i:s')
				]
			]);
			exit;
		}

		if ($action === 'verify_database') {
			// Verify what's actually in the database
			$stmt = $conn->prepare("SELECT id, turbidity, tds, ph, temperature, `in`, reading_time FROM water_readings ORDER BY id DESC LIMIT 5");
			$stmt->execute();
			$result = $stmt->get_result();
			
			$records = [];
			while ($row = $result->fetch_assoc()) {
				$records[] = $row;
			}
			$stmt->close();
			
			header('Content-Type: application/json');
			echo json_encode([
				'success' => true,
				'latest_records' => $records,
				'manipulation_status' => [
					'enabled' => getSetting($conn, 'manipulation_enabled', '0'),
					'running' => getSetting($conn, 'manipulation_running', '0'),
					'ph_range' => getSetting($conn, 'ph_range', '6-7'),
					'turbidity_range' => getSetting($conn, 'turbidity_range', '1-2'),
					'tds_range' => getSetting($conn, 'tds_range', '0-50')
				]
			]);
			exit;
		}

		if ($action === 'insert_reading_categories') {
			$phCategory = isset($_POST['ph_category']) ? (string)$_POST['ph_category'] : '';
			$turbidityCategory = isset($_POST['turbidity_category']) ? (string)$_POST['turbidity_category'] : '';
			$tdsCategory = isset($_POST['tds_category']) ? (string)$_POST['tds_category'] : '';
			$temperature = isset($_POST['temperature']) && $_POST['temperature'] !== '' ? (float)$_POST['temperature'] : 25.0;
			$inValue = isset($_POST['in_value']) && $_POST['in_value'] !== '' ? (float)$_POST['in_value'] : 0.0;

			[$phRangeStr, $turbidityRangeStr, $tdsRangeStr] = getRangesFromCategories($phCategory, $turbidityCategory, $tdsCategory);

			[$phMin, $phMax] = parseRange($phRangeStr);
			[$turMin, $turMax] = parseRange($turbidityRangeStr);
			[$tdsMin, $tdsMax] = parseRange($tdsRangeStr);

			$ph = randomInRange($phMin, $phMax, 2);
			$turbidity = randomInRange($turMin, $turMax, 2);
			$tds = randomInRange($tdsMin, $tdsMax, 2);

			// Check if manipulation is enabled and running
			$manipulationEnabled = getSetting($conn, 'manipulation_enabled', '0') === '1';
			$manipulationRunning = getSetting($conn, 'manipulation_running', '0') === '1';
			
			// Debug: Log the manipulation status
			error_log("Manipulation enabled: " . ($manipulationEnabled ? 'YES' : 'NO'));
			error_log("Manipulation running: " . ($manipulationRunning ? 'YES' : 'NO'));
			
			if ($manipulationEnabled && $manipulationRunning) {
				// Get individual manipulation settings for each sensor
				$manipulatePh = getSetting($conn, 'manipulate_ph', '1') === '1';
				$manipulateTurbidity = getSetting($conn, 'manipulate_turbidity', '1') === '1';
				$manipulateTds = getSetting($conn, 'manipulate_tds', '1') === '1';
				$manipulateTemperature = getSetting($conn, 'manipulate_temperature', '1') === '1';
				
				// Debug: Log individual sensor settings
				error_log("Manipulate pH: " . ($manipulatePh ? 'YES' : 'NO'));
				error_log("Manipulate Turbidity: " . ($manipulateTurbidity ? 'YES' : 'NO'));
				error_log("Manipulate TDS: " . ($manipulateTds ? 'YES' : 'NO'));
				error_log("Manipulate Temperature: " . ($manipulateTemperature ? 'YES' : 'NO'));
				
				// Override with manipulated random values for enabled sensors
				if ($manipulatePh) {
					$phRange = getSetting($conn, 'ph_range', '6-7');
					[$phMin, $phMax] = parseRange($phRange);
					$ph = randomInRange($phMin, $phMax, 2);
					error_log("pH manipulated from range $phRange to: $ph");
				}
				
				if ($manipulateTurbidity) {
					$turbidityRange = getSetting($conn, 'turbidity_range', '1-2');
					[$turMin, $turMax] = parseRange($turbidityRange);
					$turbidity = randomInRange($turMin, $turMax, 2);
					error_log("Turbidity manipulated from range $turbidityRange to: $turbidity");
				}
				
				if ($manipulateTds) {
					$tdsRange = getSetting($conn, 'tds_range', '0-50');
					[$tdsMin, $tdsMax] = parseRange($tdsRange);
					$tds = randomInRange($tdsMin, $tdsMax, 2);
					error_log("TDS manipulated from range $tdsRange to: $tds");
				}
				
				if ($manipulateTemperature) {
					$temperatureRange = getSetting($conn, 'temperature_range', '20-25');
					[$tempMin, $tempMax] = parseRange($temperatureRange);
					$temperature = randomInRange($tempMin, $tempMax, 2);
					error_log("Temperature manipulated from range $temperatureRange to: $temperature");
				}
			}

			// Debug: Log final values being inserted
			error_log("Final values to insert - pH: $ph, Turbidity: $turbidity, TDS: $tds, Temperature: $temperature");

			$stmt = $conn->prepare("INSERT INTO water_readings (turbidity, tds, ph, temperature, `in`) VALUES (?, ?, ?, ?, ?)");
			$stmt->bind_param('ddddd', $turbidity, $tds, $ph, $temperature, $inValue);
			$ok = $stmt->execute();
			
			// Log the actual INSERT query and values
			error_log("INSERT Query executed: INSERT INTO water_readings (turbidity, tds, ph, temperature, `in`) VALUES ($turbidity, $tds, $ph, $temperature, $inValue)");
			error_log("INSERT Result: " . ($ok ? 'SUCCESS' : 'FAILED'));
			
			// If successful, verify what was actually stored in the database
			if ($ok) {
				$insertId = $conn->insert_id;
				error_log("New record ID: $insertId");
				
				// Fetch the record we just inserted to verify the values
				$verifyStmt = $conn->prepare("SELECT turbidity, tds, ph, temperature, `in` FROM water_readings WHERE id = ?");
				$verifyStmt->bind_param('i', $insertId);
				$verifyStmt->execute();
				$verifyResult = $verifyStmt->get_result();
				
				if ($verifyResult && $verifyResult->num_rows > 0) {
					$row = $verifyResult->fetch_assoc();
					error_log("VERIFICATION - What was actually stored in database:");
					error_log("  ID: $insertId");
					error_log("  Turbidity: " . $row['turbidity'] . " (expected: $turbidity)");
					error_log("  TDS: " . $row['tds'] . " (expected: $tds)");
					error_log("  pH: " . $row['ph'] . " (expected: $ph)");
					error_log("  Temperature: " . $row['temperature'] . " (expected: $temperature)");
					error_log("  In: " . $row['in'] . " (expected: $inValue)");
					
					// Check if values match what we intended to insert
					if ($row['turbidity'] == $turbidity && $row['tds'] == $tds && $row['ph'] == $ph && $row['temperature'] == $temperature) {
						error_log("SUCCESS: Database values match intended values");
					} else {
						error_log("ERROR: Database values DO NOT match intended values!");
						error_log("  This suggests the manipulation is not working or there's a database issue");
					}
				} else {
					error_log("ERROR: Could not verify inserted record - verification query failed");
				}
				$verifyStmt->close();
			}
			
			$stmt->close();

			$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
			if ($isAjax) {
				header('Content-Type: application/json');
				echo json_encode([
					'success' => $ok,
					'data' => [
						'ph' => $ph,
						'turbidity' => $turbidity,
						'tds' => $tds,
						'temperature' => $temperature,
						'in' => $inValue,
						'categories' => [
							'ph' => $phCategory,
							'turbidity' => $turbidityCategory,
							'tds' => $tdsCategory,
						],
						'manipulated' => ($manipulationEnabled && $manipulationRunning)
					]
				]);
				exit;
			}

			if ($ok) {
				$messages[] = 'Reading inserted successfully.';
			} else {
				$errors[] = 'Failed to insert reading.';
			}
		}

		if ($action === 'get_latest_reading') {
			$stmt = $conn->prepare("SELECT turbidity, tds, ph, temperature, `in`, reading_time FROM water_readings ORDER BY reading_time DESC LIMIT 1");
			$stmt->execute();
			$result = $stmt->get_result();
			$row = $result ? $result->fetch_assoc() : null;
			$stmt->close();

			if ($row) {
				header('Content-Type: application/json');
				echo json_encode([
					'success' => true,
					'data' => [
						'ph' => (float)$row['ph'],
						'turbidity' => (float)$row['turbidity'],
						'tds' => (float)$row['tds'],
						'temperature' => (float)$row['temperature'],
						'in' => (float)$row['in'],
						'reading_time' => $row['reading_time']
					]
				]);
			} else {
				header('Content-Type: application/json');
				echo json_encode([
					'success' => false,
					'error' => 'No readings found'
				]);
			}
			exit;
		}

		if ($action === 'set_manipulation_running') {
			$manipulationRunning = isset($_POST['manipulation_running']) && $_POST['manipulation_running'] === '1' ? '1' : '0';
			setSetting($conn, 'manipulation_running', $manipulationRunning);
			
			// Return JSON response for AJAX calls
			if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
				header('Content-Type: application/json');
				echo json_encode([
					'success' => true,
					'manipulation_running' => $manipulationRunning
				]);
				exit;
			}
			
			$messages[] = $manipulationRunning === '1' ? 'Manipulation system started.' : 'Manipulation system stopped.';
		}
	}

	$currentUploadsDisabled = getSetting($conn, 'uploads_disabled', '0') === '1';
	$manipulationSettings = getDataManipulationSettings($conn);
	
	// Load current manipulation settings
	$currentManipulationSettings = [
		'manipulate_ph' => getSetting($conn, 'manipulate_ph', '1'),
		'manipulate_turbidity' => getSetting($conn, 'manipulate_turbidity', '1'),
		'manipulate_tds' => getSetting($conn, 'manipulate_tds', '1'),
		'manipulate_temperature' => getSetting($conn, 'manipulate_temperature', '1'),
		'ph_range' => getSetting($conn, 'ph_range', '6-7'),
		'turbidity_range' => getSetting($conn, 'turbidity_range', '1-2'),
		'tds_range' => getSetting($conn, 'tds_range', '0-50'),
		'temperature_range' => getSetting($conn, 'temperature_range', '20-25')
	];
} catch (Exception $e) {
	$errors[] = 'Error: ' . $e->getMessage();
}

// Predefined ranges
$phRanges = [
	'3-4' => '3 - 4',
	'4-5' => '4 - 5',
	'5-6' => '5 - 6',
	'6-7' => '6 - 7',
	'7-9' => '7 - 9',
];

$turbidityRanges = [
	'1-2' => '1 - 2',
	'2-10' => '2 - 10',
	'10-20' => '10 - 20',
	'30-50' => '30 - 50',
	'50-100' => '50 - 100',
];

$tdsRanges = [
	'0-100' => '0 - 100',
	'150-300' => '150 - 300',
	'300-600' => '300 - 600',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Control Panel</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 24px; }
		section { border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin-bottom: 24px; }
		h2 { margin-top: 0; }
		.status { padding: 6px 10px; border-radius: 4px; display: inline-block; }
		.status.ok { background: #e6ffed; color: #036b26; border: 1px solid #a6f3bf; }
		.status.warn { background: #fff4e5; color: #8a4b00; border: 1px solid #ffd8a8; }
		.msg { background: #f0f7ff; border: 1px solid #cfe5ff; padding: 8px 10px; border-radius: 4px; margin-bottom: 8px; }
		.err { background: #fff5f5; border: 1px solid #ffc9c9; padding: 8px 10px; border-radius: 4px; margin-bottom: 8px; }
		form .row { margin-bottom: 12px; }
		label { display: inline-block; width: 160px; }
		select, input[type="number"] { padding: 6px 8px; }
		button { padding: 8px 14px; cursor: pointer; }
		.small { font-size: 12px; color: #666; }
		.manipulation-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px; }
		.manipulation-grid h3 { margin: 0 0 12px 0; font-size: 14px; color: #333; }
		.manipulation-grid .row { margin-bottom: 8px; }
		.manipulation-grid label { width: 80px; font-size: 12px; }
		.manipulation-grid input { width: 80px; padding: 4px 6px; font-size: 12px; }
		.formula-box { background: #f8f9fa; border: 1px solid #e9ecef; padding: 12px; border-radius: 4px; margin-top: 12px; }
	</style>
</head>
<body>
	<h1>Control Panel</h1>

	<?php foreach ($messages as $m): ?>
		<div class="msg"><?php echo htmlspecialchars($m, ENT_QUOTES, 'UTF-8'); ?></div>
	<?php endforeach; ?>
	<?php foreach ($errors as $e): ?>
		<div class="err"><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div>
	<?php endforeach; ?>

	<section>
		<h2>Uploads Control</h2>
		<p>
			Current status:
			<span class="status <?php echo $currentUploadsDisabled ? 'warn' : 'ok'; ?>">
				<?php echo $currentUploadsDisabled ? 'Uploads are DISABLED' : 'Uploads are ENABLED'; ?>
			</span>
		</p>
		<form method="post">
			<input type="hidden" name="action" value="toggle_uploads" />
			<input type="hidden" name="uploads_disabled" value="<?php echo $currentUploadsDisabled ? '0' : '1'; ?>" />
			<button type="submit"><?php echo $currentUploadsDisabled ? 'Enable uploads' : 'Disable uploads'; ?></button>
		</form>
	</section>

	<section>
		<h2>Insert Water Reading</h2>
		<form method="post">
			<input type="hidden" name="action" value="insert_reading" />
			<div class="row">
				<label for="ph_range">pH range</label>
				<select id="ph_range" name="ph_range" required>
					<?php foreach ($phRanges as $value => $label): ?>
						<option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="row">
				<label for="turbidity_range">Turbidity range (NTU)</label>
				<select id="turbidity_range" name="turbidity_range" required>
					<?php foreach ($turbidityRanges as $value => $label): ?>
						<option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="row">
				<label for="tds_range">TDS range (ppm)</label>
				<select id="tds_range" name="tds_range" required>
					<?php foreach ($tdsRanges as $value => $label): ?>
						<option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="row">
				<label for="temperature">Temperature (°C)</label>
				<input id="temperature" name="temperature" type="number" step="0.1" placeholder="e.g. 25" value="25" />
			</div>
			<div class="row">
				<label for="in_value">In (flow/level)</label>
				<input id="in_value" name="in_value" type="number" step="0.01" placeholder="e.g. 0" value="0" />
			</div>
			<div class="row">
				<button type="submit">Insert reading</button>
			</div>
		</form>
	</section>

	<section>
		<h2>Data Manipulation Settings</h2>
		<p class="small">Control data manipulation with start/stop. When running, generates random values within specified ranges for every new reading every second.</p>
		<form method="post">
			<input type="hidden" name="action" value="save_manipulation_settings" />
			<div class="row">
				<label>
					<input type="checkbox" name="manipulation_enabled" value="1" <?php echo $manipulationSettings['manipulation_enabled'] ? 'checked' : ''; ?> />
					Enable data manipulation system
				</label>
			</div>
			
			<div class="manipulation-grid">
				<div>
					<h3>pH Sensor</h3>
					<div class="row">
						<label>
							<input type="checkbox" name="manipulate_ph" value="1" <?php echo $currentManipulationSettings['manipulate_ph'] === '1' ? 'checked' : ''; ?> />
							Manipulate pH
						</label>
					</div>
					<div class="row">
						<label for="ph_range">pH Range:</label>
						<select id="ph_range" name="ph_range">
							<option value="3-4" <?php echo $currentManipulationSettings['ph_range'] === '3-4' ? 'selected' : ''; ?>>3 - 4 (Very Acidic)</option>
							<option value="4-5" <?php echo $currentManipulationSettings['ph_range'] === '4-5' ? 'selected' : ''; ?>>4 - 5 (Acidic)</option>
							<option value="5-6" <?php echo $currentManipulationSettings['ph_range'] === '5-6' ? 'selected' : ''; ?>>5 - 6 (Slightly Acidic)</option>
							<option value="6-7" <?php echo $currentManipulationSettings['ph_range'] === '6-7' ? 'selected' : ''; ?>>6 - 7 (Neutral)</option>
							<option value="7-8" <?php echo $currentManipulationSettings['ph_range'] === '7-8' ? 'selected' : ''; ?>>7 - 8 (Slightly Alkaline)</option>
							<option value="8-9" <?php echo $currentManipulationSettings['ph_range'] === '8-9' ? 'selected' : ''; ?>>8 - 9 (Alkaline)</option>
							<option value="9-10" <?php echo $currentManipulationSettings['ph_range'] === '9-10' ? 'selected' : ''; ?>>9 - 10 (Very Alkaline)</option>
						</select>
					</div>
				</div>
				
				<div>
					<h3>Turbidity Sensor</h3>
					<div class="row">
						<label>
							<input type="checkbox" name="manipulate_turbidity" value="1" <?php echo $currentManipulationSettings['manipulate_turbidity'] === '1' ? 'checked' : ''; ?> />
							Manipulate Turbidity
						</label>
					</div>
					<div class="row">
						<label for="turbidity_range">Turbidity Range (NTU):</label>
						<select id="turbidity_range" name="turbidity_range">
							<option value="1-2" <?php echo $currentManipulationSettings['turbidity_range'] === '1-2' ? 'selected' : ''; ?>>1 - 2 (Very Clear)</option>
							<option value="2-5" <?php echo $currentManipulationSettings['turbidity_range'] === '2-5' ? 'selected' : ''; ?>>2 - 5 (Clear)</option>
							<option value="5-10" <?php echo $currentManipulationSettings['turbidity_range'] === '5-10' ? 'selected' : ''; ?>>5 - 10 (Slightly Turbid)</option>
							<option value="10-20" <?php echo $currentManipulationSettings['turbidity_range'] === '10-20' ? 'selected' : ''; ?>>10 - 20 (Turbid)</option>
							<option value="20-50" <?php echo $currentManipulationSettings['turbidity_range'] === '20-50' ? 'selected' : ''; ?>>20 - 50 (Very Turbid)</option>
							<option value="50-100" <?php echo $currentManipulationSettings['turbidity_range'] === '50-100' ? 'selected' : ''; ?>>50 - 100 (Extremely Turbid)</option>
						</select>
					</div>
				</div>
				
				<div>
					<h3>TDS Sensor</h3>
					<div class="row">
						<label>
							<input type="checkbox" name="manipulate_tds" value="1" <?php echo $currentManipulationSettings['manipulate_tds'] === '1' ? 'checked' : ''; ?> />
							Manipulate TDS
						</label>
					</div>
					<div class="row">
						<label for="tds_range">TDS Range (ppm):</label>
						<select id="tds_range" name="tds_range">
							<option value="0-50" <?php echo $currentManipulationSettings['tds_range'] === '0-50' ? 'selected' : ''; ?>>0 - 50 (Very Low)</option>
							<option value="50-150" <?php echo $currentManipulationSettings['tds_range'] === '50-150' ? 'selected' : ''; ?>>50 - 150 (Low)</option>
							<option value="150-300" <?php echo $currentManipulationSettings['tds_range'] === '150-300' ? 'selected' : ''; ?>>150 - 300 (Medium)</option>
							<option value="300-500" <?php echo $currentManipulationSettings['tds_range'] === '300-500' ? 'selected' : ''; ?>>300 - 500 (High)</option>
							<option value="500-1000" <?php echo $currentManipulationSettings['tds_range'] === '500-1000' ? 'selected' : ''; ?>>500 - 1000 (Very High)</option>
						</select>
					</div>
				</div>
				
				<div>
					<h3>Temperature Sensor</h3>
					<div class="row">
						<label>
							<input type="checkbox" name="manipulate_temperature" value="1" <?php echo $currentManipulationSettings['manipulate_temperature'] === '1' ? 'checked' : ''; ?> />
							Manipulate Temperature
						</label>
					</div>
					<div class="row">
						<label for="temperature_range">Temperature Range (°C):</label>
						<select id="temperature_range" name="temperature_range">
							<option value="15-20" <?php echo $currentManipulationSettings['temperature_range'] === '15-20' ? 'selected' : ''; ?>>15 - 20 (Cool)</option>
							<option value="20-25" <?php echo $currentManipulationSettings['temperature_range'] === '20-25' ? 'selected' : ''; ?>>20 - 25 (Room Temp)</option>
							<option value="25-30" <?php echo $currentManipulationSettings['temperature_range'] === '25-30' ? 'selected' : ''; ?>>25 - 30 (Warm)</option>
							<option value="30-35" <?php echo $currentManipulationSettings['temperature_range'] === '30-35' ? 'selected' : ''; ?>>30 - 35 (Hot)</option>
							<option value="35-40" <?php echo $currentManipulationSettings['temperature_range'] === '35-40' ? 'selected' : ''; ?>>35 - 40 (Very Hot)</option>
						</select>
					</div>
				</div>
			</div>
			
			<div class="row" style="margin-top: 16px;">
				<button type="submit">Save Manipulation Settings</button>
			</div>
			
			<div class="formula-box">
				<div class="small">
					<strong>How it works:</strong> When manipulation is running, random values are generated within your selected ranges every second.<br/>
					<strong>Example:</strong> pH range 6-7 will generate random values like 6.23, 6.87, 6.45, etc.<br/>
					<strong>Note:</strong> These random values replace the original sensor readings in the display only.
				</div>
			</div>
		</form>
		
		<div style="margin-top: 20px; padding: 16px; background: #f8f9fa; border-radius: 8px;">
			<h3>Manipulation Control</h3>
			<p class="small">Start/stop the continuous manipulation process. When running, generates random values within your ranges every second.</p>
			<div class="row">
				<button id="startManipulationBtn" type="button">Start Manipulation</button>
				<button id="stopManipulationBtn" type="button" disabled>Stop Manipulation</button>
			</div>
			<div id="manipulation_status" class="small" style="margin-top: 8px;"></div>
			
			<!-- Debug section to show current settings -->
			<div style="margin-top: 16px; padding: 12px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
				<h4 style="margin: 0 0 8px 0; color: #856404;">Debug: Current Settings</h4>
				<div class="small">
					<strong>Manipulation Enabled:</strong> <?php echo getSetting($conn, 'manipulation_enabled', '0') === '1' ? 'YES' : 'NO'; ?><br/>
					<strong>Manipulation Running:</strong> <?php echo getSetting($conn, 'manipulation_running', '0') === '1' ? 'YES' : 'NO'; ?><br/>
					<strong>Manipulate pH:</strong> <?php echo getSetting($conn, 'manipulate_ph', '0') === '1' ? 'YES' : 'NO'; ?> (Range: <?php echo getSetting($conn, 'ph_range', '6-7'); ?>)<br/>
					<strong>Manipulate Turbidity:</strong> <?php echo getSetting($conn, 'manipulate_turbidity', '0') === '1' ? 'YES' : 'NO'; ?> (Range: <?php echo getSetting($conn, 'turbidity_range', '1-2'); ?>)<br/>
					<strong>Manipulate TDS:</strong> <?php echo getSetting($conn, 'manipulate_tds', '0') === '1' ? 'YES' : 'NO'; ?> (Range: <?php echo getSetting($conn, 'tds_range', '0-50'); ?>)<br/>
					<strong>Manipulate Temperature:</strong> <?php echo getSetting($conn, 'manipulate_temperature', '0') === '1' ? 'YES' : 'NO'; ?> (Range: <?php echo getSetting($conn, 'temperature_range', '20-25'); ?>)
				</div>
				<div style="margin-top: 12px;">
					<button id="testManipulationBtn" type="button" style="background: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">Test Manipulation Settings</button>
					<button id="verifyDatabaseBtn" type="button" style="background: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; margin-left: 8px;">Verify Database Contents</button>
					<button id="checkManipulationStatusBtn" type="button" style="background: #ffc107; color: black; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; margin-left: 8px;">Check Manipulation Status</button>
					<div id="testResults" style="margin-top: 8px; font-size: 11px;"></div>
					<div id="databaseResults" style="margin-top: 8px; font-size: 11px;"></div>
					<div id="manipulationStatusResults" style="margin-top: 8px; font-size: 11px;"></div>
				</div>
			</div>
		</div>
	</section>

	<section>
		<h2>Continuous Database Updates (New Approach)</h2>
		<p>This approach updates the latest reading in the database every second instead of inserting new records.</p>
		
		<div style="margin-bottom: 16px;">
			<button id="startDbUpdatesBtn" type="button" style="background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Start Database Updates</button>
			<button id="stopDbUpdatesBtn" type="button" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-left: 8px;">Stop Database Updates</button>
		</div>
		
		<div id="dbUpdateStatus" style="padding: 8px; border-radius: 4px; display: none;">
			<strong>Status:</strong> <span id="dbUpdateStatusText">Stopped</span>
		</div>
		
		<div id="dbUpdateResults" style="margin-top: 12px; padding: 8px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; font-size: 12px; display: none;">
			<strong>Last Update Results:</strong>
			<div id="dbUpdateResultsContent"></div>
		</div>
		
		<div style="margin-top: 16px; padding: 12px; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 4px;">
			<h4 style="margin: 0 0 8px 0; color: #0056b3;">How This Works (OPTIMIZED FOR SPEED):</h4>
			<div class="small">
				<strong>1.</strong> Start Database Updates when manipulation is enabled<br/>
				<strong>2.</strong> The API will update the latest reading every 100ms (10x faster!)<br/>
				<strong>3.</strong> Aggressive manipulation runs every 50ms (20x faster!) for real-time updates<br/>
				<strong>4.</strong> Only the sensors you've enabled for manipulation will be updated<br/>
				<strong>5.</strong> The database table will show the manipulated values immediately<br/>
				<strong>6.</strong> Use "Verify Database Contents" to see the changes<br/>
				<strong>⚠️ NEW:</strong> Much faster update intervals to catch new readings before they're displayed!
			</div>
		</div>
	</section>

	<section>
		<h2>Continuous Insert (every second)</h2>
		<p class="small">This will insert a new row in `water_readings` every second until you stop it.</p>
		<div class="row">
			<label for="cont_temperature">Temperature (°C)</label>
			<input id="cont_temperature" type="number" step="0.1" value="25" />
		</div>
		<div class="row">
			<label for="cont_in">In (flow/level)</label>
			<input id="cont_in" type="number" step="0.01" value="0" />
		</div>
		<div class="row">
			<label for="ph_category">pH category</label>
			<select id="ph_category">
				<option value="low">Low (3 - 6)</option>
				<option value="neutral" selected>Neutral (6 - 7)</option>
				<option value="high">High (7 - 9)</option>
			</select>
		</div>
		<div class="row">
			<label for="turbidity_category">Turbidity</label>
			<select id="turbidity_category">
				<option value="clean" selected>Clean (1 - 2)</option>
				<option value="turbid">Turbid (30 - 100)</option>
			</select>
		</div>
		<div class="row">
			<label for="tds_category">TDS ppm</label>
			<select id="tds_category">
				<option value="low" selected>Low/Clean (0 - 150)</option>
				<option value="high">High (150 - 1000)</option>
			</select>
		</div>
		<div class="row">
			<button id="startBtn" type="button">Start</button>
			<button id="stopBtn" type="button" disabled>Stop</button>
		</div>
		<div id="cont_status" class="small"></div>
	</section>

	<section>
		<h2>Latest Data Monitor</h2>
		<p class="small">Shows the most recent water reading and updates every second. Manipulation is controlled in the Data Manipulation Settings above.</p>
		<div id="latest_data" class="small" style="background: #f8f9fa; padding: 12px; border-radius: 4px; margin-bottom: 16px;">
			Loading latest data...
		</div>
	</section>

	<script>
	// Global functions that need to be accessible everywhere
	function parseRange(range) {
		// Expecting formats like "3-4", "10-20"
		var parts = range.split('-').map(function(part) { return parseFloat(part.trim()); });
		var min = parts[0] || 0;
		var max = parts[1] || min;
		if (max < min) {
			var temp = min;
			min = max;
			max = temp;
		}
		return [min, max];
	}

	function randomInRange(min, max, decimals) {
		decimals = decimals || 2;
		if (max <= min) {
			return parseFloat(min.toFixed(decimals));
		}
		var rand = min + (Math.random() * (max - min));
		return parseFloat(rand.toFixed(decimals));
	}

	(function() {
		var timerId = null;
		var lastResponse = null;
		var latestDataTimerId = null;
		var manipulationTimerId = null; // New timer for continuous manipulation

		function setRunning(running) {
			document.getElementById('startBtn').disabled = running;
			document.getElementById('stopBtn').disabled = !running;
		}

		function insertOnce() {
			var temp = document.getElementById('cont_temperature').value || '25';
			var inv = document.getElementById('cont_in').value || '0';
			var phC = document.getElementById('ph_category').value;
			var turbC = document.getElementById('turbidity_category').value;
			var tdsC = document.getElementById('tds_category').value;

			var formData = new FormData();
			formData.append('action', 'insert_reading_categories');
			formData.append('ajax', '1');
			formData.append('temperature', temp);
			formData.append('in_value', inv);
			formData.append('ph_category', phC);
			formData.append('turbidity_category', turbC);
			formData.append('tds_category', tdsC);

			fetch(window.location.href, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			}).then(function(r) { return r.json(); })
			.then(function(json) {
				lastResponse = json;
				var el = document.getElementById('cont_status');
				if (json && json.success) {
					var statusText = 'Inserted: pH ' + json.data.ph + ', Turbidity ' + json.data.turbidity + ', TDS ' + json.data.tds + ' at ' + new Date().toLocaleTimeString();
					
					// Check if manipulation is running and show it in the status
					var manipulationEnabled = document.querySelector('input[name="manipulation_enabled"]').checked;
					var manipulationRunning = manipulationTimerId !== null;
					
					if (manipulationEnabled && manipulationRunning) {
						statusText += ' [MANIPULATED]';
					}
					
					el.textContent = statusText;
				} else {
					el.textContent = 'Insert failed at ' + new Date().toLocaleTimeString();
				}
			}).catch(function(err) {
				var el = document.getElementById('cont_status');
				el.textContent = 'Request error: ' + err;
			});
		}

		function startManipulation() {
			if (manipulationTimerId !== null) return;
			var el = document.getElementById('manipulation_status');
			el.textContent = 'Manipulation started. Generating random values...';
			
			// Check if manipulation is enabled in the form
			var manipulationEnabled = document.querySelector('input[name="manipulation_enabled"]').checked;
			if (!manipulationEnabled) {
				el.textContent = 'Error: Please enable data manipulation system first.';
				return;
			}
			
			// Save manipulation running state to database
			var formData = new FormData();
			formData.append('action', 'set_manipulation_running');
			formData.append('manipulation_running', '1');
			
			fetch(window.location.href, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			}).then(function() {
						// Start the manipulation timer - much faster for real-time updates
		manipulationTimerId = setInterval(function() {
			// Generate random values even without existing data
			var phRange = document.getElementById('ph_range').value;
			var turbidityRange = document.getElementById('turbidity_range').value;
			var tdsRange = document.getElementById('tds_range').value;
			var temperatureRange = document.getElementById('temperature_range').value;
			
			// Generate random values within ranges
			var phRandom = randomInRange(parseRange(phRange)[0], parseRange(phRange)[1], 2);
			var turbidityRandom = randomInRange(parseRange(turbidityRange)[0], parseRange(turbidityRange)[1], 2);
			var tdsRandom = randomInRange(parseRange(tdsRange)[0], parseRange(tdsRange)[1], 2);
			var temperatureRandom = randomInRange(parseRange(temperatureRange)[0], parseRange(temperatureRange)[1], 2);
			
			// Update status with generated values
			el.textContent = 'Generated: pH ' + phRandom + ', Turbidity ' + turbidityRandom + ' NTU, TDS ' + tdsRandom + ' ppm, Temp ' + temperatureRandom + '°C - ' + new Date().toLocaleTimeString();
		}, 100); // Generate every 100ms (10 times per second) for faster manipulation
				
				document.getElementById('startManipulationBtn').disabled = true;
				document.getElementById('stopManipulationBtn').disabled = false;
			});
		}

		function stopManipulation() {
			if (manipulationTimerId !== null) {
				clearInterval(manipulationTimerId);
				manipulationTimerId = null;
				
				// Save manipulation stopped state to database
				var formData = new FormData();
				formData.append('action', 'set_manipulation_running');
				formData.append('manipulation_running', '0');
				
				fetch(window.location.href, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				}).then(function() {
					var el = document.getElementById('manipulation_status');
					el.textContent = 'Manipulation stopped.';
					document.getElementById('startManipulationBtn').disabled = false;
					document.getElementById('stopManipulationBtn').disabled = true;
				});
			}
		}

		function updateLatestDataDisplay(data) {
			// Update the summary display
			var el = document.getElementById('latest_data');
			
			// Check if manipulation is enabled AND manipulation timer is running
			var manipulationEnabled = document.querySelector('input[name="manipulation_enabled"]').checked;
			var manipulationRunning = manipulationTimerId !== null;
			
			// Generate random values if both enabled and running
			var phDisplay = data.ph;
			var turbidityDisplay = data.turbidity;
			var tdsDisplay = data.tds;
			var temperatureDisplay = data.temperature;
			
			if (manipulationEnabled && manipulationRunning) {
				// Check individual manipulation settings
				var manipulatePh = document.querySelector('input[name="manipulate_ph"]').checked;
				var manipulateTurbidity = document.querySelector('input[name="manipulate_turbidity"]').checked;
				var manipulateTds = document.querySelector('input[name="manipulate_tds"]').checked;
				var manipulateTemperature = document.querySelector('input[name="manipulate_temperature"]').checked;
				
				// Only manipulate sensors that are enabled
				if (manipulatePh) {
					var phRange = document.getElementById('ph_range').value;
					phDisplay = randomInRange(parseRange(phRange)[0], parseRange(phRange)[1], 2);
				}
				
				if (manipulateTurbidity) {
					var turbidityRange = document.getElementById('turbidity_range').value;
					turbidityDisplay = randomInRange(parseRange(turbidityRange)[0], parseRange(turbidityRange)[1], 2);
				}
				
				if (manipulateTds) {
					var tdsRange = document.getElementById('tds_range').value;
					tdsDisplay = randomInRange(parseRange(tdsRange)[0], parseRange(tdsRange)[1], 2);
				}
				
				if (manipulateTemperature) {
					var temperatureRange = document.getElementById('temperature_range').value;
					temperatureDisplay = randomInRange(parseRange(temperatureRange)[0], parseRange(temperatureRange)[1], 2);
				}
			}
			
			// Show manipulated values in the summary if both enabled and running
			if (manipulationEnabled && manipulationRunning) {
				el.innerHTML = '<strong>Latest Reading (Selectively Manipulated):</strong> pH: ' + phDisplay.toFixed(2) + 
							  ', Turbidity: ' + turbidityDisplay.toFixed(2) + ' NTU' +
							  ', TDS: ' + tdsDisplay.toFixed(2) + ' ppm' +
							  ', Temperature: ' + temperatureDisplay.toFixed(2) + '°C' +
							  ', In: ' + data.in +
							  ' <br><small>Updated: ' + new Date().toLocaleTimeString() + ' | Manipulation: RUNNING</small>';
			} else if (manipulationEnabled && !manipulationRunning) {
				el.innerHTML = '<strong>Latest Reading (Original):</strong> pH: ' + data.ph + 
							  ', Turbidity: ' + data.turbidity + ' NTU' +
							  ', TDS: ' + data.tds + ' ppm' +
							  ', Temperature: ' + data.temperature + '°C' +
							  ', In: ' + data.in +
							  ' <br><small>Updated: ' + new Date().toLocaleTimeString() + ' | Manipulation: STOPPED</small>';
			} else {
				el.innerHTML = '<strong>Latest Reading (Original):</strong> pH: ' + data.ph + 
							  ', Turbidity: ' + data.turbidity + ' NTU' +
							  ', TDS: ' + data.tds + ' ppm' +
							  ', Temperature: ' + data.temperature + '°C' +
							  ', In: ' + data.in +
							  ' <br><small>Updated: ' + new Date().toLocaleTimeString() + ' | Manipulation: DISABLED</small>';
			}

			// Store original values for manipulation
			window.latestOriginalData = {
				ph: parseFloat(data.ph),
				turbidity: parseFloat(data.turbidity),
				tds: parseFloat(data.tds),
				temperature: parseFloat(data.temperature)
			};
		}

		function applyLiveManipulation() {
			if (!window.latestOriginalData) return;

			var data = window.latestOriginalData;
			
			// Get selected ranges from the form
			var phRange = document.getElementById('ph_range').value;
			var turbidityRange = document.getElementById('turbidity_range').value;
			var tdsRange = document.getElementById('tds_range').value;
			var temperatureRange = document.getElementById('temperature_range').value;
			
			// Generate random values within ranges
			var phRandom = randomInRange(parseRange(phRange)[0], parseRange(phRange)[1], 2);
			var turbidityRandom = randomInRange(parseRange(turbidityRange)[0], parseRange(turbidityRange)[1], 2);
			var tdsRandom = randomInRange(parseRange(tdsRange)[0], parseRange(tdsRange)[1], 2);
			var temperatureRandom = randomInRange(parseRange(temperatureRange)[0], parseRange(temperatureRange)[1], 2);

			// Update the latest data display with random values if manipulation is running
			if (manipulationTimerId !== null && document.querySelector('input[name="manipulation_enabled"]').checked) {
				var el = document.getElementById('latest_data');
				el.innerHTML = '<strong>Latest Reading (Random Generated):</strong> pH: ' + phRandom.toFixed(2) + 
							  ', Turbidity: ' + turbidityRandom.toFixed(2) + ' NTU' +
							  ', TDS: ' + tdsRandom.toFixed(2) + ' ppm' +
							  ', Temperature: ' + temperatureRandom.toFixed(2) + '°C' +
							  ', In: ' + (window.latestOriginalData.in || 0) +
							  ' <br><small>Updated: ' + new Date().toLocaleTimeString() + ' | Manipulation: RUNNING</small>';
			}
		}

		function startLatestDataMonitor() {
			if (latestDataTimerId !== null) return;
			
			// Try to fetch data immediately, but don't fail if none exists
			fetchLatestData();
			
					// Then set up the interval for continuous monitoring - faster for real-time manipulation
		latestDataTimerId = setInterval(fetchLatestData, 100); // Update every 100ms (10 times per second) for faster manipulation
		}

		function fetchLatestData() {
			var formData = new FormData();
			formData.append('action', 'get_latest_reading');
			formData.append('ajax', '1');

			fetch(window.location.href, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			}).then(function(r) { return r.json(); })
			.then(function(json) {
				if (json && json.success && json.data) {
					updateLatestDataDisplay(json.data);
				} else {
					// No data exists yet, show a message
					var el = document.getElementById('latest_data');
					if (el) {
						el.innerHTML = '<em>No readings found yet. Start continuous insert to generate data.</em>';
					}
				}
			}).catch(function(err) {
				console.error('Error fetching latest data:', err);
				// Show error message in the display
				var el = document.getElementById('latest_data');
				if (el) {
					el.innerHTML = '<em>Error loading data. Please check the connection.</em>';
				}
			});
		}

		function stopLatestDataMonitor() {
			if (latestDataTimerId !== null) {
				clearInterval(latestDataTimerId);
				latestDataTimerId = null;
			}
		}

		// Event listeners
		document.getElementById('startBtn').addEventListener('click', function() {
			if (timerId !== null) return;
			setRunning(true);
			insertOnce();
			timerId = setInterval(insertOnce, 1000);
			startLatestDataMonitor(); // Start monitoring when continuous insert starts
		});

		document.getElementById('stopBtn').addEventListener('click', function() {
			if (timerId !== null) {
				clearInterval(timerId);
				timerId = null;
				setRunning(false);
				stopLatestDataMonitor(); // Stop monitoring when continuous insert stops
			}
		});

		document.getElementById('startManipulationBtn').addEventListener('click', startManipulation);
		document.getElementById('stopManipulationBtn').addEventListener('click', stopManipulation);

		// Add test button event listener
		document.getElementById('testManipulationBtn').addEventListener('click', function() {
			var formData = new FormData();
			formData.append('action', 'test_manipulation');
			
			fetch(window.location.href, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			}).then(function(r) { return r.json(); })
			.then(function(json) {
				if (json && json.success) {
					var results = json.test_results;
					var el = document.getElementById('testResults');
					el.innerHTML = '<strong>Test Results:</strong><br/>' +
						'Manipulation Enabled: ' + results.manipulation_enabled + '<br/>' +
						'Manipulation Running: ' + results.manipulation_running + '<br/>' +
						'Manipulate pH: ' + results.manipulate_ph + '<br/>' +
						'pH Range: ' + results.ph_range + '<br/>' +
						'Timestamp: ' + results.timestamp;
				}
			}).catch(function(err) {
				document.getElementById('testResults').innerHTML = 'Test failed: ' + err;
			});
		});

		// Add database verification button event listener
		document.getElementById('verifyDatabaseBtn').addEventListener('click', function() {
			var formData = new FormData();
			formData.append('action', 'verify_database');
			
			fetch(window.location.href, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			}).then(function(r) { return r.json(); })
			.then(function(json) {
				if (json && json.success) {
					var records = json.latest_records;
					var status = json.manipulation_status;
					var el = document.getElementById('databaseResults');
					
					var html = '<strong>Database Verification:</strong><br/>';
					html += '<strong>Manipulation Status:</strong><br/>';
					html += '- Enabled: ' + status.enabled + '<br/>';
					html += '- Running: ' + status.running + '<br/>';
					html += '- pH Range: ' + status.ph_range + '<br/>';
					html += '- Turbidity Range: ' + status.turbidity_range + '<br/>';
					html += '- TDS Range: ' + status.tds_range + '<br/><br/>';
					
					html += '<strong>Latest 5 Records:</strong><br/>';
					records.forEach(function(record) {
						html += 'ID: ' + record.id + ' | T: ' + record.turbidity + ' | TDS: ' + record.tds + ' | pH: ' + record.ph + ' | Temp: ' + record.ph + ' | Time: ' + record.reading_time + '<br/>';
					});
					
					el.innerHTML = html;
				}
			}).catch(function(err) {
				document.getElementById('databaseResults').innerHTML = 'Database verification failed: ' + err;
			});
		});
		
		// Add manipulation status check button event listener
		document.getElementById('checkManipulationStatusBtn').addEventListener('click', function() {
			var status = checkManipulationStatus();
			var el = document.getElementById('manipulationStatusResults');
			
			var html = '<strong>Current Manipulation Status:</strong><br/>';
			html += '- Overall Enabled: ' + (status.enabled ? 'YES' : 'NO') + '<br/>';
			html += '- pH Manipulation: ' + (status.ph ? 'YES' : 'NO') + '<br/>';
			html += '- Turbidity Manipulation: ' + (status.turbidity ? 'YES' : 'NO') + '<br/>';
			html += '- TDS Manipulation: ' + (status.tds ? 'YES' : 'NO') + '<br/>';
			html += '- Temperature Manipulation: ' + (status.temperature ? 'YES' : 'NO') + '<br/>';
			html += '<br/><strong>Timestamp:</strong> ' + new Date().toLocaleTimeString();
			
			el.innerHTML = html;
		});

		// Database Update functionality - Optimized for real-time manipulation
		var dbUpdateInterval = null;
		var aggressiveManipulationInterval = null; // New: More aggressive manipulation
		
				document.getElementById('startDbUpdatesBtn').addEventListener('click', function() {
			if (dbUpdateInterval) {
				clearInterval(dbUpdateInterval);
			}
			if (aggressiveManipulationInterval) {
				clearInterval(aggressiveManipulationInterval);
			}
			
			// Start continuous updates - much faster for real-time manipulation
			dbUpdateInterval = setInterval(function() {
				updateLatestReading();
			}, 100); // Update every 100ms (10 times per second) instead of 1000ms
			
			// Start aggressive manipulation - directly manipulate readings as they come in
			aggressiveManipulationInterval = setInterval(function() {
				aggressiveManipulateReadings();
			}, 50); // Even faster: every 50ms (20 times per second)
			
			// Update UI
			document.getElementById('dbUpdateStatus').style.display = 'block';
			document.getElementById('dbUpdateStatusText').textContent = 'Running (updating every 100ms + aggressive manipulation every 50ms)';
			document.getElementById('dbUpdateStatus').style.background = '#d4edda';
			document.getElementById('dbUpdateStatus').style.border = '1px solid #c3e6cb';
			document.getElementById('dbUpdateStatus').style.color = '#155724';
			
			document.getElementById('dbUpdateResults').style.display = 'block';
		});
		
		document.getElementById('stopDbUpdatesBtn').addEventListener('click', function() {
			if (dbUpdateInterval) {
				clearInterval(dbUpdateInterval);
				dbUpdateInterval = null;
			}
			if (aggressiveManipulationInterval) {
				clearInterval(aggressiveManipulationInterval);
				aggressiveManipulationInterval = null;
			}
			
			// Update UI
			document.getElementById('dbUpdateStatusText').textContent = 'Stopped';
			document.getElementById('dbUpdateStatus').style.background = '#f8d7da';
			document.getElementById('dbUpdateStatus').style.border = '1px solid #f5c6cb';
			document.getElementById('dbUpdateStatus').style.color = '#721c24';
		});
		
		function updateLatestReading() {
			fetch('api/update_latest_reading.php?action=update_latest')
				.then(function(response) { return response.json(); })
				.then(function(json) {
					if (json && json.success) {
						var data = json.data;
						var el = document.getElementById('dbUpdateResultsContent');
						
						var html = '<div style="margin-bottom: 8px;">';
						html += '<strong>Updated ID:</strong> ' + data.id + ' at ' + new Date().toLocaleTimeString() + '<br/>';
						
						if (data.manipulated_sensors.turbidity) {
							html += '<strong>Turbidity:</strong> ' + data.original.turbidity + ' → ' + data.updated.turbidity + '<br/>';
						}
						if (data.manipulated_sensors.tds) {
							html += '<strong>TDS:</strong> ' + data.original.tds + ' → ' + data.updated.tds + '<br/>';
						}
						if (data.manipulated_sensors.ph) {
							html += '<strong>pH:</strong> ' + data.original.ph + ' → ' + data.updated.ph + '<br/>';
						}
						if (data.manipulated_sensors.temperature) {
							html += '<strong>Temperature:</strong> ' + data.original.temperature + ' → ' + data.updated.temperature + '<br/>';
						}
						
						html += '</div>';
						el.innerHTML = html;
					} else {
						document.getElementById('dbUpdateResultsContent').innerHTML = 
							'<div style="color: #721c24;">Error: ' + (json.message || 'Unknown error') + '</div>';
					}
				})
				.catch(function(err) {
					document.getElementById('dbUpdateResultsContent').innerHTML = 
						'<div style="color: #721c24;">Failed to update: ' + err.message + '</div>';
				});
		}
		
		// New: Aggressive manipulation function that directly manipulates readings
		function aggressiveManipulateReadings() {
			// Check if manipulation is enabled and running
			var manipulationEnabled = document.querySelector('input[name="manipulation_enabled"]').checked;
			if (!manipulationEnabled) {
				console.log('Manipulation disabled, skipping...');
				return;
			}
			
			// Get current manipulation settings - only manipulate what's actually checked
			var manipulatePh = document.querySelector('input[name="manipulate_ph"]').checked;
			var manipulateTurbidity = document.querySelector('input[name="manipulate_turbidity"]').checked;
			var manipulateTds = document.querySelector('input[name="manipulate_tds"]').checked;
			var manipulateTemperature = document.querySelector('input[name="manipulate_temperature"]').checked;
			
			console.log('Manipulation settings:', {
				ph: manipulatePh,
				turbidity: manipulateTurbidity,
				tds: manipulateTds,
				temperature: manipulateTemperature
			});
			
			// Only proceed if at least one sensor is selected for manipulation
			if (!manipulatePh && !manipulateTurbidity && !manipulateTds && !manipulateTemperature) {
				console.log('No sensors selected for manipulation, skipping...');
				return; // No sensors selected, don't manipulate anything
			}
			
			// Get selected ranges only for sensors that are actually enabled
			var newValues = {};
			
			if (manipulatePh) {
				var phRange = document.getElementById('ph_range').value;
				var [phMin, phMax] = parseRange(phRange);
				newValues.ph = randomInRange(phMin, phMax, 2);
				console.log('pH manipulation enabled, range:', phRange, 'new value:', newValues.ph);
			}
			
			if (manipulateTurbidity) {
				var turbidityRange = document.getElementById('turbidity_range').value;
				var [turMin, turMax] = parseRange(turbidityRange);
				newValues.turbidity = randomInRange(turMin, turMax, 2);
				console.log('Turbidity manipulation enabled, range:', turbidityRange, 'new value:', newValues.turbidity);
			}
			
			if (manipulateTds) {
				var tdsRange = document.getElementById('tds_range').value;
				var [tdsMin, tdsMax] = parseRange(tdsRange);
				newValues.tds = randomInRange(tdsMin, tdsMax, 2);
				console.log('TDS manipulation enabled, range:', tdsRange, 'new value:', newValues.tds);
			}
			
			if (manipulateTemperature) {
				var temperatureRange = document.getElementById('temperature_range').value;
				var [tempMin, tempMax] = parseRange(temperatureRange);
				newValues.temperature = randomInRange(tempMin, tempMax, 2);
				console.log('Temperature manipulation enabled, range:', temperatureRange, 'new value:', newValues.temperature);
			}
			
			console.log('Final manipulation values:', newValues);
			
			// If we have new values, immediately update the latest reading
			if (Object.keys(newValues).length > 0) {
				// Create form data for immediate manipulation
				var formData = new FormData();
				formData.append('action', 'insert_reading_categories');
				formData.append('ajax', '1');
				
				// Set default values for all sensors first
				formData.append('temperature', '25');
				formData.append('in_value', '0');
				formData.append('ph_category', 'neutral');
				formData.append('turbidity_category', 'clean');
				formData.append('tds_category', 'low');
				
				// Override only the sensors that are actually being manipulated
				if (manipulatePh && newValues.ph) {
					formData.append('ph_override', newValues.ph);
					console.log('Adding pH override:', newValues.ph);
				}
				if (manipulateTurbidity && newValues.turbidity) {
					formData.append('turbidity_override', newValues.turbidity);
					console.log('Adding turbidity override:', newValues.turbidity);
				}
				if (manipulateTds && newValues.tds) {
					formData.append('tds_override', newValues.tds);
					console.log('Adding TDS override:', newValues.tds);
				}
				if (manipulateTemperature && newValues.temperature) {
					formData.append('temperature_override', newValues.temperature);
					console.log('Adding temperature override:', newValues.temperature);
				}
				
				// Send manipulation request
				fetch(window.location.href, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				}).then(function(r) { return r.json(); })
				.then(function(json) {
					if (json && json.success) {
						console.log('Manipulation successful:', json);
						// Update the display immediately
						var el = document.getElementById('dbUpdateResultsContent');
						var html = '<div style="margin-bottom: 8px; color: #155724;">';
						html += '<strong>AGGRESSIVE MANIPULATION:</strong> ' + new Date().toLocaleTimeString() + '<br/>';
						if (newValues.ph) html += 'pH: ' + newValues.ph + '<br/>';
						if (newValues.turbidity) html += 'Turbidity: ' + newValues.turbidity + ' NTU<br/>';
						if (newValues.tds) html += 'TDS: ' + newValues.tds + ' ppm<br/>';
						if (newValues.temperature) html += 'Temperature: ' + newValues.temperature + '°C<br/>';
						html += '</div>';
						el.innerHTML = html + el.innerHTML;
					} else {
						console.error('Manipulation failed:', json);
					}
				}).catch(function(err) {
					console.error('Aggressive manipulation error:', err);
				});
			} else {
				console.log('No values to manipulate');
			}
		}

		// Add event listeners for the main manipulation form inputs
		var mainManipulationInputs = [
			'ph_range', 'turbidity_range', 'tds_range', 'temperature_range'
		];

		mainManipulationInputs.forEach(function(inputId) {
			var element = document.getElementById(inputId);
			if (element) {
				element.addEventListener('change', function() {
					// Trigger immediate update of the display with new range values
					if (window.latestOriginalData) {
						updateLatestDataDisplay({
							ph: window.latestOriginalData.ph,
							turbidity: window.latestOriginalData.turbidity,
							tds: window.latestOriginalData.tds,
							temperature: window.latestOriginalData.temperature,
							in: 0
						});
					}
				});
			}
		});
		
		// Add event listeners for manipulation checkboxes to ensure real-time updates
		var manipulationCheckboxes = [
			'manipulate_ph', 'manipulate_turbidity', 'manipulate_tds', 'manipulate_temperature'
		];
		
		manipulationCheckboxes.forEach(function(checkboxName) {
			var checkbox = document.querySelector('input[name="' + checkboxName + '"]');
			if (checkbox) {
				checkbox.addEventListener('change', function() {
					console.log('Manipulation checkbox changed:', checkboxName, 'checked:', this.checked);
					// Force immediate update of manipulation status
					if (aggressiveManipulationInterval) {
						clearInterval(aggressiveManipulationInterval);
						aggressiveManipulationInterval = setInterval(aggressiveManipulateReadings, 50);
					}
				});
			}
		});
		
		// Function to check current manipulation status
		function checkManipulationStatus() {
			var status = {
				enabled: document.querySelector('input[name="manipulation_enabled"]').checked,
				ph: document.querySelector('input[name="manipulate_ph"]').checked,
				turbidity: document.querySelector('input[name="manipulate_turbidity"]').checked,
				tds: document.querySelector('input[name="manipulate_tds"]').checked,
				temperature: document.querySelector('input[name="manipulate_temperature"]').checked
			};
			
			console.log('Current manipulation status:', status);
			return status;
		}

		// Add event listener for the manipulation enabled checkbox
		var manipulationCheckbox = document.querySelector('input[name="manipulation_enabled"]');
		if (manipulationCheckbox) {
			manipulationCheckbox.addEventListener('change', function() {
				// Trigger immediate update when enabling/disabling manipulation
				if (window.latestOriginalData) {
					updateLatestDataDisplay({
						ph: window.latestOriginalData.ph,
						turbidity: window.latestOriginalData.turbidity,
						tds: window.latestOriginalData.tds,
						temperature: window.latestOriginalData.temperature,
						in: 0
					});
				}
			});
		}

		// Add form submission handler to save ranges
		var manipulationForm = document.querySelector('form[action*="save_manipulation_settings"]');
		if (manipulationForm) {
			manipulationForm.addEventListener('submit', function(e) {
				// Form will submit normally to save the ranges
				// The manipulation will use these saved ranges
			});
		}

		// Load current manipulation settings when page loads
		function loadManipulationSettings() {
			// This will be called after the page loads to set the correct checkbox states
			// The PHP will handle setting the initial values
		}

		// Start monitoring latest data immediately when page loads
		startLatestDataMonitor();
		
		// Load manipulation settings after a short delay to ensure DOM is ready
		setTimeout(loadManipulationSettings, 100);
	})();
	</script>

</body>
</html>


