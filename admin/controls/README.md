# Automation System Documentation

## Overview

The automation system automatically controls the water quality system based on sensor readings. When both TDS (Total Dissolved Solids) and Turbidity sensors detect values in critical or medium ranges, the system automatically activates Relay 1 (Filter) to improve water quality.

## Features

### Automatic Filter Control
- **TDS Thresholds:**
  - Medium: 150-200 ppm
  - Critical: 200-500 ppm
- **Turbidity Thresholds:**
  - Medium: 2.0-5.0 NTU
  - Critical: 5.0-10.0 NTU

### Activation Logic
The filter (Relay 1) will automatically activate when:
1. Both TDS and Turbidity are in critical OR medium range, OR
2. Either TDS OR Turbidity is in critical range

The filter will automatically deactivate when water quality returns to normal levels.

## Components

### 1. Automation Control Panel (`admin/controls/controls.php`)
- Real-time status display
- Manual automation controls
- Sensor data analysis
- Automation settings toggles

### 2. Automation API (`api/automation_control.php`)
- RESTful API for automation operations
- Sensor data analysis
- Manual trigger functionality
- Settings management

### 3. Background Automation (`api/automation_cron.php`)
- Cron job script for automatic monitoring
- Logs all automation actions
- Runs independently of web interface

### 4. Database Table (`database/10_automation_settings.sql`)
- Stores automation configuration
- Tracks last check times
- Configurable thresholds

## Setup Instructions

### 1. Database Setup
Run the automation settings SQL file:
```sql
mysql -u username -p database_name < database/10_automation_settings.sql
```

### 2. Cron Job Setup
Add the following cron job to run automation every 5 minutes:
```bash
# Edit crontab
crontab -e

# Add this line
*/5 * * * * php /path/to/your/project/api/automation_cron.php
```

### 3. Log Directory
Ensure the logs directory exists and is writable:
```bash
mkdir -p logs
chmod 755 logs
```

## Usage

### Web Interface
1. Navigate to `admin/controls/`
2. View real-time automation status
3. Toggle automation settings
4. Manually trigger automation checks

### API Endpoints

#### GET `/api/automation_control.php`
Returns current automation status and sensor analysis.

#### POST `/api/automation_control.php`
- `action=update_settings`: Update automation settings
- `action=check_and_trigger`: Manually trigger automation check

### Manual Testing
Test the automation system manually:
```bash
php api/automation_cron.php
```

## Monitoring

### Log Files
- `logs/automation_cron.log`: Background automation logs
- `logs/schedule_execution.log`: General system logs

### Activity Logs
All automation actions are logged in the `activity_logs` table with:
- User ID: 0 (System user)
- Action descriptions
- Detailed reasons for automation decisions

## Configuration

### Thresholds
Edit thresholds in `api/automation_control.php`:
```php
define('TDS_CRITICAL_MIN', 200);
define('TDS_CRITICAL_MAX', 500);
define('TDS_MEDIUM_MIN', 150);
define('TDS_MEDIUM_MAX', 200);

define('TURBIDITY_CRITICAL_MIN', 5.0);
define('TURBIDITY_CRITICAL_MAX', 10.0);
define('TURBIDITY_MEDIUM_MIN', 2.0);
define('TURBIDITY_MEDIUM_MAX', 5.0);
```

### Check Interval
Modify the cron schedule or update the `check_interval` field in the database.

## Troubleshooting

### Common Issues

1. **Automation not working:**
   - Check if automation is enabled in settings
   - Verify cron job is running
   - Check log files for errors

2. **Filter not activating:**
   - Verify sensor data is being received
   - Check threshold values
   - Ensure relay states table exists

3. **Permission errors:**
   - Check log directory permissions
   - Verify database connection
   - Ensure proper file ownership

### Debug Mode
Enable debug logging by modifying the cron script:
```php
ini_set('display_errors', 1);
```

## Security Considerations

- Automation actions are logged for audit trails
- System user (ID: 0) is used for automation actions
- All automation decisions are based on sensor data only
- Manual override capabilities are available through the web interface

## Future Enhancements

- Additional sensor support (pH, temperature)
- Multiple relay automation
- Email/SMS notifications
- Advanced scheduling
- Machine learning-based threshold optimization 