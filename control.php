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

			$stmt = $conn->prepare("INSERT INTO water_readings (turbidity, tds, ph, temperature, `in`) VALUES (?, ?, ?, ?, ?)");
			$stmt->bind_param('ddddd', $turbidity, $tds, $ph, $temperature, $inValue);
			if ($stmt->execute()) {
				$messages[] = 'Reading inserted successfully.';
			} else {
				$errors[] = 'Failed to insert reading.';
			}
			$stmt->close();
		}
	}

	$currentUploadsDisabled = getSetting($conn, 'uploads_disabled', '0') === '1';
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

</body>
</html>


