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
			
			$settings = [
				'ph_offset', 'ph_multiplier', 'turbidity_offset', 'turbidity_multiplier',
				'tds_offset', 'tds_multiplier', 'temperature_offset', 'temperature_multiplier'
			];
			
			foreach ($settings as $setting) {
				$value = isset($_POST[$setting]) && $_POST[$setting] !== '' ? (string)$_POST[$setting] : '0.0';
				if ($setting === 'multiplier') {
					$value = isset($_POST[$setting]) && $_POST[$setting] !== '' ? (string)$_POST[$setting] : '1.0';
				}
				setSetting($conn, $setting, $value);
			}
			
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

		if ($action === 'insert_reading_categories') {
			$phCategory = isset($_POST['ph_category']) ? (string)$_POST['ph_category'] : 'neutral';
			$turbidityCategory = isset($_POST['turbidity_category']) ? (string)$_POST['turbidity_category'] : 'clean';
			$tdsCategory = isset($_POST['tds_category']) ? (string)$_POST['tds_category'] : 'low';
			$temperature = isset($_POST['temperature']) && $_POST['temperature'] !== '' ? (float)$_POST['temperature'] : 25.0;
			$inValue = isset($_POST['in_value']) && $_POST['in_value'] !== '' ? (float)$_POST['in_value'] : 0.0;

			[$phRangeStr, $turbidityRangeStr, $tdsRangeStr] = getRangesFromCategories($phCategory, $turbidityCategory, $tdsCategory);

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
						'categories' => [
							'ph' => $phCategory,
							'turbidity' => $turbidityCategory,
							'tds' => $tdsCategory,
						]
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
	}

	$currentUploadsDisabled = getSetting($conn, 'uploads_disabled', '0') === '1';
	$manipulationSettings = getDataManipulationSettings($conn);
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
		<p class="small">Control data manipulation with start/stop. When running, applies manipulation to every new reading every second.</p>
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
						<label for="ph_offset">Offset:</label>
						<input id="ph_offset" name="ph_offset" type="number" step="0.01" value="<?php echo $manipulationSettings['ph_offset']; ?>" />
					</div>
					<div class="row">
						<label for="ph_multiplier">Multiplier:</label>
						<input id="ph_multiplier" name="ph_multiplier" type="number" step="0.01" value="<?php echo $manipulationSettings['ph_multiplier']; ?>" />
					</div>
				</div>
				
				<div>
					<h3>Turbidity Sensor</h3>
					<div class="row">
						<label for="turbidity_offset">Offset:</label>
						<input id="turbidity_offset" name="turbidity_offset" type="number" step="0.01" value="<?php echo $manipulationSettings['turbidity_offset']; ?>" />
					</div>
					<div class="row">
						<label for="turbidity_multiplier">Multiplier:</label>
						<input id="turbidity_multiplier" name="turbidity_multiplier" type="number" step="0.01" value="<?php echo $manipulationSettings['turbidity_multiplier']; ?>" />
					</div>
				</div>
				
				<div>
					<h3>TDS Sensor</h3>
					<div class="row">
						<label for="tds_offset">Offset:</label>
						<input id="tds_offset" name="tds_offset" type="number" step="0.01" value="<?php echo $manipulationSettings['tds_offset']; ?>" />
					</div>
					<div class="row">
						<label for="tds_multiplier">Multiplier:</label>
						<input id="tds_multiplier" name="tds_multiplier" type="number" step="0.01" value="<?php echo $manipulationSettings['tds_multiplier']; ?>" />
					</div>
				</div>
				
				<div>
					<h3>Temperature Sensor</h3>
					<div class="row">
						<label for="temperature_offset">Offset:</label>
						<input id="temperature_offset" name="temperature_offset" type="number" step="0.01" value="<?php echo $manipulationSettings['temperature_offset']; ?>" />
					</div>
					<div class="row">
						<label for="temperature_multiplier">Multiplier:</label>
						<input id="temperature_multiplier" name="temperature_multiplier" type="number" step="0.01" value="<?php echo $manipulationSettings['temperature_multiplier']; ?>" />
					</div>
				</div>
			</div>
			
			<div class="row" style="margin-top: 16px;">
				<button type="submit">Save Manipulation Settings</button>
			</div>
			
			<div class="formula-box">
				<div class="small">
					<strong>Formula:</strong> Final Value = (Original Value + Offset) × Multiplier<br/>
					<strong>Note:</strong> These settings are applied continuously every second to the latest reading display only.<br/>
					<strong>Example:</strong> pH 7.0 + 1.0 offset × 0.8 multiplier = (7.0 + 1.0) × 0.8 = 6.4
				</div>
			</div>
		</form>
		
		<div style="margin-top: 20px; padding: 16px; background: #f8f9fa; border-radius: 8px;">
			<h3>Manipulation Control</h3>
			<p class="small">Start/stop the continuous manipulation process. When running, applies your settings to every new reading every second.</p>
			<div class="row">
				<button id="startManipulationBtn" type="button">Start Manipulation</button>
				<button id="stopManipulationBtn" type="button" disabled>Stop Manipulation</button>
			</div>
			<div id="manipulation_status" class="small" style="margin-top: 8px;"></div>
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
					el.textContent = 'Inserted: pH ' + json.data.ph + ', Turbidity ' + json.data.turbidity + ', TDS ' + json.data.tds + ' at ' + (new Date()).toLocaleTimeString();
				} else {
					el.textContent = 'Insert failed at ' + (new Date()).toLocaleTimeString();
				}
			}).catch(function(err) {
				var el = document.getElementById('cont_status');
				el.textContent = 'Request error: ' + err;
			});
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
					// applyLiveManipulation(); // This is now handled by updateLatestDataDisplay
				}
			}).catch(function(err) {
				console.error('Error fetching latest data:', err);
			});
		}

		function startManipulation() {
			if (manipulationTimerId !== null) return;
			var el = document.getElementById('manipulation_status');
			el.textContent = 'Manipulation started. Applying settings to every new reading...';
			
			// Check if manipulation is enabled in the form
			var manipulationEnabled = document.querySelector('input[name="manipulation_enabled"]').checked;
			if (!manipulationEnabled) {
				el.textContent = 'Error: Please enable data manipulation system first.';
				return;
			}
			
			manipulationTimerId = setInterval(function() {
				if (window.latestOriginalData) {
					// Apply manipulation to the current display
					applyLiveManipulation();
					el.textContent = 'Manipulation applied. Updated: ' + new Date().toLocaleTimeString();
				} else {
					el.textContent = 'No data to manipulate. Please insert a reading first.';
				}
			}, 1000); // Apply every second
			
			document.getElementById('startManipulationBtn').disabled = true;
			document.getElementById('stopManipulationBtn').disabled = false;
		}

		function stopManipulation() {
			if (manipulationTimerId !== null) {
				clearInterval(manipulationTimerId);
				manipulationTimerId = null;
				var el = document.getElementById('manipulation_status');
				el.textContent = 'Manipulation stopped.';
				document.getElementById('startManipulationBtn').disabled = false;
				document.getElementById('stopManipulationBtn').disabled = true;
			}
		}

		function updateLatestDataDisplay(data) {
			// Update the summary display
			var el = document.getElementById('latest_data');
			
			// Get manipulation settings from the form
			var phOffset = parseFloat(document.getElementById('ph_offset').value) || 0;
			var phMultiplier = parseFloat(document.getElementById('ph_multiplier').value) || 1;
			var turbidityOffset = parseFloat(document.getElementById('turbidity_offset').value) || 0;
			var turbidityMultiplier = parseFloat(document.getElementById('turbidity_multiplier').value) || 1;
			var tdsOffset = parseFloat(document.getElementById('tds_offset').value) || 0;
			var tdsMultiplier = parseFloat(document.getElementById('tds_multiplier').value) || 1;
			var temperatureOffset = parseFloat(document.getElementById('temperature_offset').value) || 0;
			var temperatureMultiplier = parseFloat(document.getElementById('temperature_multiplier').value) || 1;
			
			// Check if manipulation is enabled AND manipulation timer is running
			var manipulationEnabled = document.querySelector('input[name="manipulation_enabled"]').checked;
			var manipulationRunning = manipulationTimerId !== null;
			
			// Apply manipulation if both enabled and running
			var phDisplay = data.ph;
			var turbidityDisplay = data.turbidity;
			var tdsDisplay = data.tds;
			var temperatureDisplay = data.temperature;
			
			if (manipulationEnabled && manipulationRunning) {
				phDisplay = (data.ph + phOffset) * phMultiplier;
				turbidityDisplay = (data.turbidity + turbidityOffset) * turbidityMultiplier;
				tdsDisplay = (data.tds + tdsOffset) * tdsMultiplier;
				temperatureDisplay = (data.temperature + temperatureOffset) * temperatureMultiplier;
			}
			
			// Show manipulated values in the summary if both enabled and running
			if (manipulationEnabled && manipulationRunning) {
				el.innerHTML = '<strong>Latest Reading (Manipulated):</strong> pH: ' + phDisplay.toFixed(2) + 
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
			
			// Get manipulation values from the main form
			var phOffset = parseFloat(document.getElementById('ph_offset').value) || 0;
			var phMultiplier = parseFloat(document.getElementById('ph_multiplier').value) || 1;
			var turbidityOffset = parseFloat(document.getElementById('turbidity_offset').value) || 0;
			var turbidityMultiplier = parseFloat(document.getElementById('turbidity_multiplier').value) || 1;
			var tdsOffset = parseFloat(document.getElementById('tds_offset').value) || 0;
			var tdsMultiplier = parseFloat(document.getElementById('tds_multiplier').value) || 1;
			var temperatureOffset = parseFloat(document.getElementById('temperature_offset').value) || 0;
			var temperatureMultiplier = parseFloat(document.getElementById('temperature_multiplier').value) || 1;

			// Apply manipulation formula: (value + offset) * multiplier
			var phModified = (data.ph + phOffset) * phMultiplier;
			var turbidityModified = (data.turbidity + turbidityOffset) * turbidityMultiplier;
			var tdsModified = (data.tds + tdsOffset) * tdsMultiplier;
			var temperatureModified = (data.temperature + temperatureOffset) * temperatureMultiplier;

			// Update the latest data display with modified values if manipulation is running
			if (manipulationTimerId !== null && document.querySelector('input[name="manipulation_enabled"]').checked) {
				var el = document.getElementById('latest_data');
				el.innerHTML = '<strong>Latest Reading (Manipulated):</strong> pH: ' + phModified.toFixed(2) + 
							  ', Turbidity: ' + turbidityModified.toFixed(2) + ' NTU' +
							  ', TDS: ' + tdsModified.toFixed(2) + ' ppm' +
							  ', Temperature: ' + temperatureModified.toFixed(2) + '°C' +
							  ', In: ' + (window.latestOriginalData.in || 0) +
							  ' <br><small>Updated: ' + new Date().toLocaleTimeString() + ' | Manipulation: RUNNING</small>';
			}
		}

		function startLatestDataMonitor() {
			if (latestDataTimerId !== null) return;
			fetchLatestData(); // Fetch immediately
			latestDataTimerId = setInterval(fetchLatestData, 1000); // Then every second
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

		// Add event listeners for the main manipulation form inputs
		var mainManipulationInputs = [
			'ph_offset', 'ph_multiplier',
			'turbidity_offset', 'turbidity_multiplier',
			'tds_offset', 'tds_multiplier',
			'temperature_offset', 'temperature_multiplier'
		];

		mainManipulationInputs.forEach(function(inputId) {
			document.getElementById(inputId).addEventListener('input', function() {
				// Trigger immediate update of the display with new manipulation values
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
		});

		// Add event listener for the manipulation enabled checkbox
		document.querySelector('input[name="manipulation_enabled"]').addEventListener('change', function() {
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

		// Start monitoring latest data immediately when page loads
		startLatestDataMonitor();
	})();
	</script>

</body>
</html>


