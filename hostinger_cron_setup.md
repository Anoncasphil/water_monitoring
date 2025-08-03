# Hostinger Cron Job Setup Guide

## Method 1: Hostinger Control Panel (Recommended)

### Step 1: Access cPanel
1. Log in to your Hostinger control panel
2. Go to "Advanced" → "Cron Jobs"

### Step 2: Create New Cron Job
1. **Common Settings:**
   - **Minute:** `*/5` (every 5 minutes)
   - **Hour:** `*` (every hour)
   - **Day:** `*` (every day)
   - **Month:** `*` (every month)
   - **Weekday:** `*` (every day of week)

2. **Command to run:**
   ```bash
   php /home/username/public_html/projtest/api/execute_schedules.php
   ```
   Replace `username` with your actual Hostinger username.

### Step 3: Alternative Command (if above doesn't work)
```bash
/usr/bin/php /home/username/public_html/projtest/api/execute_schedules.php
```

## Method 2: Using .htaccess Auto-Execution

### Create a trigger file:
```php
<?php
// File: /public_html/projtest/api/cron_trigger.php
// This file can be accessed via web to trigger schedule execution

// Prevent direct access
if (!isset($_GET['key']) || $_GET['key'] !== 'your_secret_key_here') {
    http_response_code(403);
    exit('Access denied');
}

// Include and run the schedule execution
include_once 'execute_schedules.php';
```

### Set up external cron service:
Use services like:
- **cron-job.org** (free)
- **EasyCron** (free tier available)
- **SetCronJob** (free)

**URL to call:** `https://yourdomain.com/projtest/api/cron_trigger.php?key=your_secret_key_here`

## Method 3: Using Hostinger's Advanced Cron

### For more frequent execution (every minute):
```bash
* * * * * php /home/username/public_html/projtest/api/execute_schedules.php
```

### For specific times (e.g., every 10 minutes):
```bash
*/10 * * * * php /home/username/public_html/projtest/api/execute_schedules.php
```

## Method 4: Using Hostinger's Task Scheduler (if available)

Some Hostinger plans include a Task Scheduler:
1. Go to "Advanced" → "Task Scheduler"
2. Create new task
3. Set frequency to every 5 minutes
4. Command: `php /home/username/public_html/projtest/api/execute_schedules.php`

## Troubleshooting

### Check if cron is working:
1. Create a test file: `/public_html/projtest/api/test_cron.php`
```php
<?php
file_put_contents('/home/username/public_html/projtest/logs/cron_test.log', 
    date('Y-m-d H:i:s') . ' - Cron job executed' . PHP_EOL, 
    FILE_APPEND);
```

2. Set up cron to run this test file every minute
3. Check the log file to see if it's working

### Common Issues:
- **Permission denied:** Make sure the path is correct
- **PHP not found:** Use full path `/usr/bin/php`
- **File not found:** Check the exact path to your files

## Security Considerations

### For Method 2 (.htaccess trigger):
1. Use a strong secret key
2. Consider IP whitelisting
3. Add rate limiting

### For all methods:
1. Keep your `execute_schedules.php` file secure
2. Don't expose sensitive information in logs
3. Monitor execution logs regularly

## Recommended Setup

**For your water monitoring system, use Method 1 with:**
- **Frequency:** Every 5 minutes (`*/5 * * * *`)
- **Command:** `php /home/username/public_html/projtest/api/execute_schedules.php`
- **Log monitoring:** Check `/public_html/projtest/logs/schedule_execution.log`

This will ensure your schedules execute reliably on Hostinger's infrastructure. 