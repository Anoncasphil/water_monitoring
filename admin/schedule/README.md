# Schedule Management System

This module provides automated scheduling for relay operations in the water quality monitoring system.

## Features

- **Schedule Creation**: Create schedules to turn relays ON/OFF at specific times
- **Multiple Frequencies**: Once, Daily, Weekly, Monthly schedules
- **Active/Inactive Status**: Enable or disable schedules without deleting them
- **Execution Logging**: Track when schedules were executed and their success status
- **Bulk Operations**: Select and delete multiple schedules at once
- **Real-time Statistics**: View total schedules, active schedules, and upcoming tasks

## Database Tables

### relay_schedules
Stores all scheduled relay operations.

| Field | Type | Description |
|-------|------|-------------|
| id | INT | Primary key |
| relay_number | INT | Relay number (1-4) |
| action | TINYINT(1) | 1 for ON, 0 for OFF |
| schedule_date | DATE | Date to execute |
| schedule_time | TIME | Time to execute |
| frequency | ENUM | once, daily, weekly, monthly |
| is_active | TINYINT(1) | 1 for active, 0 for inactive |
| description | TEXT | Optional description |
| last_executed | TIMESTAMP | When last executed |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |

### schedule_logs
Logs all schedule executions for auditing.

| Field | Type | Description |
|-------|------|-------------|
| id | INT | Primary key |
| schedule_id | INT | Foreign key to relay_schedules |
| relay_number | INT | Relay number executed |
| action | TINYINT(1) | Action performed (1=ON, 0=OFF) |
| execution_time | TIMESTAMP | When executed |
| success | TINYINT(1) | 1 for success, 0 for failure |
| error_message | TEXT | Error details if failed |

## Setup Instructions

### 1. Database Setup
The required tables are automatically created when you first access the schedule page or API.

### 2. Cron Job Setup
To enable automatic execution of scheduled tasks, set up a cron job:

```bash
# Edit crontab
crontab -e

# Add this line to run every 5 minutes
*/5 * * * * php /path/to/your/project/api/execute_schedules.php

# Or run every minute for more precise timing
* * * * * php /path/to/your/project/api/execute_schedules.php
```

### 3. File Permissions
Ensure the logs directory is writable:

```bash
mkdir -p logs
chmod 755 logs
```

## Usage

### Creating a Schedule
1. Navigate to Admin > Schedule Management
2. Click "Add Schedule"
3. Select the relay (1-4)
4. Choose action (ON/OFF)
5. Set date and time
6. Choose frequency
7. Set status (Active/Inactive)
8. Add optional description
9. Click "Create Schedule"

### Managing Schedules
- **Edit**: Click the edit icon to modify a schedule
- **Delete**: Click the trash icon to delete a schedule
- **Bulk Delete**: Select multiple schedules and click "Delete Selected"
- **Refresh**: Click "Refresh" to reload the schedule list

### Relay Mapping
- **Relay 1**: Pool to Filter Pump
- **Relay 2**: Filter to Pool Pump
- **Relay 3**: Dispenser
- **Relay 4**: Spare Relay

## API Endpoints

### GET /api/schedule_control.php
- Get all schedules: `GET /api/schedule_control.php`
- Get specific schedule: `GET /api/schedule_control.php?id=1`

### POST /api/schedule_control.php
Create or update a schedule:
```json
{
  "relay_number": 1,
  "action": 1,
  "schedule_date": "2024-01-15",
  "schedule_time": "14:30",
  "frequency": "daily",
  "is_active": 1,
  "description": "Daily filter pump activation"
}
```

### DELETE /api/schedule_control.php
- Delete single schedule: `DELETE /api/schedule_control.php` with `id=1`
- Bulk delete: `DELETE /api/schedule_control.php` with `ids=[1,2,3]`

## Monitoring

### Log Files
Schedule executions are logged to:
- `logs/schedule_execution.log` - Detailed execution logs
- Database table `schedule_logs` - Structured execution history

### Manual Execution
You can manually trigger schedule execution by running:
```bash
php api/execute_schedules.php
```

## Troubleshooting

### Schedules Not Executing
1. Check if cron job is running: `crontab -l`
2. Verify file permissions on execute_schedules.php
3. Check logs for errors: `tail -f logs/schedule_execution.log`
4. Ensure relay_control.php is accessible

### Database Errors
1. Verify database connection in config/.env
2. Check if tables exist: `SHOW TABLES LIKE 'relay_schedules'`
3. Ensure proper permissions for database user

### Relay Control Issues
1. Verify ESP32 is connected and responding
2. Check network connectivity to ESP32
3. Test relay control manually via the Controls page

## Security Notes

- All API endpoints require user authentication
- Schedule execution logs all actions for audit purposes
- Input validation prevents SQL injection and invalid data
- Only authorized users can create/modify schedules 