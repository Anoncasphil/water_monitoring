# Hostinger Arduino Uno R4 WiFi Setup Guide

## 🚀 **Quick Setup Steps**

### 1. **Hostinger Control Panel Configuration**

#### **A. SSL Certificate**
- Go to **Hosting Control Panel** → **SSL**
- Enable **Let's Encrypt SSL** (free)
- Ensure your domain has a valid SSL certificate

#### **B. PHP Configuration**
- Go to **Advanced** → **PHP Configuration**
- Set PHP version to **8.0 or higher**
- Enable the following extensions:
  - `curl`
  - `json`
  - `mysqli`
  - `openssl`

#### **C. File Manager Upload**
- Upload your project files to `public_html/` directory
- Ensure file permissions are set correctly:
  - PHP files: `644`
  - Directories: `755`

### 2. **Database Setup**

#### **A. Create MySQL Database**
- Go to **Databases** → **MySQL Databases**
- Create a new database: `water_quality_db`
- Create a database user with full privileges
- Note down the database credentials

#### **B. Import Database Schema**
- Use **phpMyAdmin** to import your SQL files
- Import in this order:
  1. `00_database_setup.sql`
  2. `01_users.sql`
  3. `02_water_readings.sql`
  4. `03_relay_states.sql`
  5. `04_relay_schedules.sql`
  6. `05_schedules.sql`
  7. `06_schedule_logs.sql`
  8. `07_activity_logs.sql`
  9. `08_indexes_and_constraints.sql`
  10. `09_sample_data.sql`
  11. `10_automation_settings.sql`
  12. `11_retention_archival.sql`
  13. `12_retention_events.sql`

### 3. **Environment Configuration**

#### **A. Update Database Credentials**
Edit `config/database.php` with your Hostinger database details:
```php
<?php
class Database {
    private static $instance = null;
    private $connection;
    
    private $host = 'localhost'; // Usually 'localhost' on Hostinger
    private $db_name = 'your_database_name';
    private $username = 'your_username';
    private $password = 'your_password';
    private $charset = 'utf8mb4';
    
    // ... rest of the code
}
```

#### **B. Test Database Connection**
Create a test file `test_db.php`:
```php
<?php
require_once 'config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    echo "Database connection successful!";
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage();
}
?>
```

### 4. **Arduino Configuration**

#### **A. Update Server Details**
In your Arduino code, ensure these settings:
```cpp
const char* serverHost = "yourdomain.com";  // Your Hostinger domain
const int httpPort = 80;
const int httpsPort = 443;
const bool USE_HTTPS = false; // Start with HTTP, then try HTTPS
```

#### **B. Test Endpoints**
Test these URLs in your browser:
- `http://yourdomain.com/api/test_http.php`
- `http://yourdomain.com/api/relay_control.php`
- `http://yourdomain.com/api/get_readings.php`

### 5. **Troubleshooting Common Issues**

#### **A. 301 Redirects**
If you get 301 redirects, your server is forcing HTTPS. Solutions:
1. **Enable HTTPS in Arduino** (recommended):
   ```cpp
   const bool USE_HTTPS = true;
   ```

2. **Configure HTTP access** (temporary):
   - Contact Hostinger support to disable HTTPS redirect for API endpoints
   - Or modify `.htaccess` to exclude API paths from HTTPS redirect

#### **B. Connection Timeouts**
- Check if your domain is properly configured
- Verify SSL certificate is active
- Test with a simple HTTP request first

#### **C. Database Connection Issues**
- Verify database credentials in `config/database.php`
- Check if database user has proper permissions
- Ensure database exists and tables are created

### 6. **Security Considerations**

#### **A. API Security**
- Consider adding API key authentication
- Implement rate limiting
- Monitor for suspicious activity

#### **B. Database Security**
- Use strong passwords
- Limit database user permissions
- Regular backups

### 7. **Testing Checklist**

- [ ] Domain resolves correctly
- [ ] SSL certificate is active
- [ ] Database connection works
- [ ] API endpoints respond correctly
- [ ] Arduino can connect to WiFi
- [ ] Arduino can reach your server
- [ ] Sensor data uploads successfully
- [ ] Relay control works

### 8. **Hostinger-Specific Settings**

#### **A. PHP Settings**
In Hostinger control panel:
- `max_execution_time`: 300 seconds
- `memory_limit`: 256M
- `upload_max_filesize`: 10M
- `post_max_size`: 10M

#### **B. Security Settings**
- Enable **ModSecurity** (Web Application Firewall)
- Configure **IP Blocker** if needed
- Set up **Backup** schedule

### 9. **Monitoring and Maintenance**

#### **A. Log Files**
Monitor these files for errors:
- `/logs/schedule_execution.log`
- Hostinger error logs
- Apache/Nginx access logs

#### **B. Performance**
- Monitor database performance
- Check API response times
- Optimize queries if needed

## 🔧 **Quick Fixes**

### If Arduino can't connect:
1. Check WiFi credentials in Arduino code
2. Verify domain name is correct
3. Test with HTTP first, then HTTPS
4. Check firewall settings

### If data doesn't upload:
1. Test API endpoints manually
2. Check database connection
3. Verify file permissions
4. Check PHP error logs

### If relays don't work:
1. Test relay control API
2. Check database relay_states table
3. Verify Arduino relay pin connections
4. Check power supply

## 📞 **Support**

If you encounter issues:
1. Check Hostinger documentation
2. Contact Hostinger support
3. Review Arduino R4 WiFi documentation
4. Test with simple HTTP requests first

---

**Remember**: Start with HTTP communication first, then upgrade to HTTPS once everything works correctly!
