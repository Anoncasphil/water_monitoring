# Water Quality Monitoring System

[![Version](https://img.shields.io/badge/version-3.2.0-blue.svg)](https://github.com/Anoncasphil/water_monitoring)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1.svg)](https://mysql.com)
[![ESP32](https://img.shields.io/badge/ESP32-Arduino-00979D.svg)](https://www.espressif.com/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.0+-38B2AC.svg)](https://tailwindcss.com/)

A comprehensive, real-time water quality monitoring system built with modern web technologies and IoT hardware. This system provides continuous monitoring of water quality parameters, automated control systems, and advanced analytics for water treatment facilities.

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Architecture](#architecture)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Documentation](#api-documentation)
- [Development](#development)
- [Deployment](#deployment)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## 🎯 Overview

The Water Quality Monitoring System is designed for real-time monitoring and control of water treatment processes. It combines IoT sensors, automated relay control, and a modern web-based dashboard to provide comprehensive water quality management.

### Key Capabilities

- **Real-time Monitoring**: Continuous tracking of pH, turbidity, TDS, and temperature
- **Automated Control**: Intelligent relay management with scheduling capabilities
- **Advanced Analytics**: Historical data analysis and trend visualization
- **User Management**: Role-based access control with audit trails
- **Mobile Responsive**: Modern web interface accessible from any device

## ✨ Features

### 🔐 Authentication & Security
- **Secure Authentication**: Session-based login with bcrypt password hashing
- **Role-Based Access Control**: Admin and staff role management
- **Audit Trail**: Complete logging of all user activities
- **Session Management**: Secure session handling with automatic cleanup
- **Input Validation**: Comprehensive sanitization and validation

### 📊 Real-Time Monitoring
- **Live Dashboard**: Real-time sensor data visualization
- **Quality Assessment**: Automated water quality status evaluation
- **Alert System**: Real-time notifications for parameter violations
- **Historical Analysis**: Trend analysis and statistical reporting
- **Data Export**: CSV and chart export capabilities

### 🎛️ System Control
- **Relay Management**: 4-channel relay control system with real-time status
- **Automated Scheduling**: Advanced time-based and recurring schedules with execution logs
- **Manual Override**: Direct control with safety measures and validation
- **Status Monitoring**: Real-time system status tracking with visual indicators
- **Execution Logging**: Complete control action history with detailed error reporting
- **Schedule Management**: Intuitive interface for creating and managing automated operations

### 📈 Analytics & Reporting
- **Interactive Charts**: Multiple chart types with Chart.js
- **Statistical Analysis**: Min/max/average calculations
- **Trend Visualization**: Historical data analysis
- **Quality Insights**: Automated recommendations
- **Performance Metrics**: Key performance indicators

### 🎨 User Interface
- **Modern Design**: Clean, responsive interface with Tailwind CSS 3.0+
- **Dark/Light Theme**: User preference toggle with persistent settings
- **Mobile Responsive**: Optimized for all device sizes and orientations
- **Real-time Updates**: Live data refresh without page reload using AJAX
- **Accessibility**: WCAG compliant interface with keyboard navigation
- **Consistent Layout**: Unified design system across all admin sections
- **Interactive Elements**: Hover effects, transitions, and visual feedback

## 🏗️ Architecture

### System Components

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   ESP32 Device  │    │   Web Server    │    │   Database      │
│                 │    │                 │    │                 │
│ • pH Sensor     │◄──►│ • PHP Backend   │◄──►│ • MySQL/MariaDB │
│ • Turbidity     │    │ • RESTful APIs  │    │ • InnoDB Engine │
│ • TDS Sensor    │    │ • Session Mgmt  │    │ • Optimized     │
│ • Temperature   │    │ • File Upload   │    │ • Indexed       │
│ • Relay Control │    │ • Authentication│    │ • Partitioned   │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Technology Stack

#### Frontend
- **HTML5**: Semantic markup and modern web standards
- **Tailwind CSS**: Utility-first CSS framework
- **JavaScript (ES6+)**: Modern JavaScript with async/await
- **Chart.js**: Interactive data visualization
- **Font Awesome**: Icon library

#### Backend
- **PHP 8.0+**: Modern PHP with type safety
- **MySQL/MariaDB**: Reliable relational database
- **RESTful APIs**: Clean API design principles
- **MVC Pattern**: Organized code architecture
- **Composer**: Dependency management (future)

#### Hardware
- **ESP32**: WiFi-enabled microcontroller
- **Sensors**: pH, Turbidity, TDS, Temperature
- **Relay Module**: 4-channel automation system
- **Power Management**: Efficient power consumption

## 📋 Prerequisites

### System Requirements

#### Server Environment
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: 8.0 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.4+
- **Memory**: Minimum 512MB RAM
- **Storage**: 1GB available space
- **Network**: Stable internet connection

#### Development Environment
- **Arduino IDE**: 2.0+ with ESP32 board support
- **Code Editor**: VS Code, Sublime Text, or similar
- **Version Control**: Git 2.0+
- **Local Server**: XAMPP, WAMP, or similar

#### Hardware Requirements
- **ESP32 Development Board**: ESP32-WROOM-32 or equivalent
- **Sensors**:
  - DFRobot pH Sensor with calibration kit
  - Turbidity Sensor (TSW-30 or similar)
  - Gravity Analog TDS Sensor
  - DS18B20 Temperature Sensor
- **Relay Module**: 4-channel 5V/3.3V compatible
- **Power Supply**: 5V/3.3V, 2A minimum
- **Connectors**: Jumper wires and breadboard

## 🚀 Installation

### 1. Repository Setup

```bash
# Clone the repository
git clone https://github.com/Anoncasphil/water_monitoring.git
cd water_monitoring

# Checkout the latest release
git checkout main
```

### 2. Web Server Configuration

```bash
# Copy project to web server directory
cp -r . /var/www/html/water_monitoring/

# Set proper permissions
chmod -R 755 /var/www/html/water_monitoring/
chmod -R 644 /var/www/html/water_monitoring/*.php
```

### 3. Database Setup

#### Option A: Quick Installation
```sql
-- Import complete database
SOURCE database/water_quality_db_complete.sql;
```

#### Option B: Modular Installation (Recommended)
```sql
-- Create database and tables
SOURCE database/00_database_setup.sql;
SOURCE database/01_users.sql;
SOURCE database/02_water_readings.sql;
SOURCE database/03_relay_states.sql;
SOURCE database/04_relay_schedules.sql;
SOURCE database/05_schedules.sql;
SOURCE database/06_schedule_logs.sql;
SOURCE database/07_activity_logs.sql;
SOURCE database/08_indexes_and_constraints.sql;

-- Optional: Add sample data
SOURCE database/09_sample_data.sql;
```

### 4. Environment Configuration

```bash
# Copy environment template
cp config/env.example config/.env

# Edit configuration file
nano config/.env
```

#### Environment Variables
```env
# Database Configuration
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=water_quality_db
DB_USERNAME=your_username
DB_PASSWORD=your_secure_password

# Application Configuration
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com/water_monitoring
APP_TIMEZONE=UTC

# Security Configuration
SESSION_SECURE=true
SESSION_HTTP_ONLY=true
```

### 5. ESP32 Setup

#### Arduino IDE Configuration
1. **Add ESP32 Board Support**:
   - File → Preferences
   - Add: `https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json`
   - Tools → Board → Boards Manager → Install "esp32"

2. **Install Required Libraries**:
   ```bash
   # Core libraries
   - WiFi.h (ESP32 core)
   - HTTPClient.h (ESP32 core)
   - ArduinoJson.h (version 6.x)
   
   # Sensor libraries
   - DFRobot_PH.h
   - OneWire.h
   - DallasTemperature.h
   ```

3. **Configure Hardware**:
   - Select board: Tools → Board → ESP32 Arduino → ESP32 Dev Module
   - Set upload speed: 115200
   - Configure WiFi credentials in `relay_control/relay_control.ino`

4. **Upload Code**:
```bash
   # Upload to ESP32
   Tools → Board → ESP32 Dev Module
   Tools → Port → Select ESP32 port
   Sketch → Upload
   ```

## ⚙️ Configuration

### Database Configuration

#### Connection Settings
```php
// config/database.php
class Database {
    private static $host = '127.0.0.1';
    private static $port = 3306;
    private static $database = 'water_quality_db';
    private static $username = 'your_username';
    private static $password = 'your_password';
}
```

#### Performance Optimization
```sql
-- Enable query cache
SET GLOBAL query_cache_size = 67108864;
SET GLOBAL query_cache_type = 1;

-- Optimize table performance
OPTIMIZE TABLE water_readings;
ANALYZE TABLE water_readings;
```

### Hardware Configuration

#### Pin Assignment
| Component | GPIO Pin | Description | Voltage |
|-----------|----------|-------------|---------|
| pH Sensor | GPIO34 | ADC1 input | 3.3V |
| Turbidity Sensor | GPIO33 | ADC1 input | 3.3V |
| TDS Sensor | GPIO32 | ADC1 input | 3.3V |
| Temperature Sensor | GPIO4 | OneWire bus | 3.3V |
| Relay IN1 | GPIO25 | Filter Control | 3.3V |
| Relay IN2 | GPIO26 | Dispense Water | 3.3V |
| Relay IN3 | GPIO27 | Reserved | 3.3V |
| Relay IN4 | GPIO14 | Reserved | 3.3V |

#### Sensor Calibration
```cpp
// pH Sensor Calibration
#define PH_OFFSET 0.00
#define PH_SLOPE 1.00

// TDS Sensor Calibration
#define TDS_OFFSET 0.00
#define TDS_SLOPE 1.00
```

## 📖 Usage

### Access Points

| Service | URL | Description |
|---------|-----|-------------|
| Main Application | `http://your-domain.com/water_monitoring/` | Landing page |
| Admin Dashboard | `http://your-domain.com/water_monitoring/admin/dashboard/` | Main dashboard |
| Real-time Monitor | `http://your-domain.com/water_monitoring/admin/monitor/` | Live monitoring |
| Analytics | `http://your-domain.com/water_monitoring/admin/analytics/` | Data analysis |
| Control Panel | `http://your-domain.com/water_monitoring/admin/controls/` | System control |
| Schedule Management | `http://your-domain.com/water_monitoring/admin/schedule/` | Automation |
| User Management | `http://your-domain.com/water_monitoring/admin/user/` | User administration |
| Activity Logs | `http://your-domain.com/water_monitoring/admin/actlogs/` | Audit trail |
| Login | `http://your-domain.com/water_monitoring/login/` | Authentication |

### Default Credentials

| Username | Password | Role | Access |
|----------|----------|------|--------|
| admin | password123 | Admin | Full access |
| staff1 | password123 | Staff | Limited access |

**⚠️ Security Note**: Change default passwords immediately after installation.

### Key Operations

#### Real-time Monitoring
1. Navigate to **Monitor** section
2. View live sensor readings
3. Monitor water quality status
4. Check system alerts

#### System Control
1. Access **Controls** section
2. Toggle relay states manually
3. Monitor system status
4. View control history

#### Schedule Management
1. Go to **Schedule** section
2. Create new schedules with date, time, and frequency settings
3. Set recurring patterns (once, daily, weekly, monthly)
4. Monitor execution logs with detailed success/failure tracking
5. Manage active/inactive schedules with bulk operations
6. View real-time schedule statistics and next execution times

#### Data Analytics
1. Visit **Analytics** section
2. View historical trends
3. Export data and charts
4. Analyze performance metrics

## 🔌 API Documentation

### Authentication
All API endpoints require valid session authentication.

### Sensor Data Endpoints

#### Get Latest Readings
```http
GET /api/get_readings.php
```

**Response:**
```json
{
  "success": true,
  "latest": {
    "turbidity": 1.7,
    "tds": 150,
    "ph": 7.2,
    "temperature": 25.5,
    "reading_time": "2024-12-15 10:30:00"
  },
  "historical": [...]
}
```

#### Upload Sensor Data
```http
POST /api/upload.php
Content-Type: application/json

{
  "turbidity": 1.7,
  "tds": 150,
  "ph": 7.2,
  "temperature": 25.5
}
```

### Control Endpoints

#### Get Relay Status
```http
GET /api/relay_control.php
```

#### Control Relay
```http
POST /api/relay_control.php
Content-Type: application/json

{
  "relay": 1,
  "action": "on"
}
```

### User Management Endpoints

#### Create User
```http
POST /api/create_user.php
Content-Type: application/json

{
  "username": "newuser",
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "password": "securepassword",
  "role": "staff"
}
```

### Schedule Management Endpoints

#### Get Schedules
```http
GET /api/schedule_control.php
```

#### Create Schedule
```http
POST /api/schedule_control.php
Content-Type: application/json

{
  "relay_number": 1,
  "action": 1,
  "schedule_date": "2024-12-20",
  "schedule_time": "08:00:00",
  "frequency": "daily",
  "is_active": 1,
  "description": "Morning filter activation"
}
```

#### Update Schedule
```http
POST /api/schedule_control.php
Content-Type: application/json

{
  "id": 1,
  "relay_number": 1,
  "action": 1,
  "schedule_date": "2024-12-20",
  "schedule_time": "08:00:00",
  "frequency": "daily",
  "is_active": 1,
  "description": "Updated schedule description"
}
```

#### Delete Schedule
```http
POST /api/schedule_control.php
Content-Type: application/json

{
  "_method": "DELETE",
  "id": 1
}
```

#### Clear Schedule Logs
```http
POST /api/clear_schedule_logs.php
```

## 🛠️ Development

### Project Structure
```
water_monitoring/
├── admin/                     # Admin dashboard
│   ├── dashboard/            # Main dashboard
│   ├── monitor/              # Real-time monitoring
│   ├── analytics/            # Data analytics
│   ├── controls/             # System controls
│   ├── schedule/             # Schedule management
│   ├── user/                 # User management
│   ├── actlogs/              # Activity logging
│   └── sidebar/              # Navigation component
├── api/                      # RESTful API endpoints
├── config/                   # Configuration files
├── database/                 # Database schema and structure
├── login/                    # Authentication system
├── relay_control/            # ESP32 Arduino code
├── logs/                     # Application logs
└── docs/                     # Documentation
```

### Development Setup

#### Local Development
```bash
# Clone repository
git clone https://github.com/Anoncasphil/water_monitoring.git
cd water_monitoring

# Setup local environment
cp config/env.example config/.env
# Edit config/.env with local settings

# Import database
mysql -u root -p < database/water_quality_db_complete.sql

# Start development server
php -S localhost:8000
```

#### Code Standards
- **PHP**: PSR-12 coding standards
- **JavaScript**: ESLint configuration
- **CSS**: Tailwind CSS utility classes
- **SQL**: Consistent naming conventions

#### Testing
```bash
# Run database tests
php tests/database_test.php

# Run API tests
php tests/api_test.php

# Run integration tests
php tests/integration_test.php
```

### Contributing

1. **Fork the repository**
2. **Create feature branch**: `git checkout -b feature/amazing-feature`
3. **Make changes** with proper documentation
4. **Test thoroughly** on different environments
5. **Follow coding standards** and project guidelines
6. **Submit pull request** with detailed description

## 🚀 Deployment

### Production Deployment

#### Server Requirements
- **Operating System**: Ubuntu 20.04+ or CentOS 8+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: 8.0+ with required extensions
- **Database**: MySQL 5.7+ or MariaDB 10.4+
- **SSL Certificate**: Valid SSL certificate for HTTPS

#### Deployment Steps

1. **Server Preparation**
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install apache2 php mysql-server php-mysql php-curl php-json
```

2. **Application Deployment**
```bash
# Clone application
sudo git clone https://github.com/Anoncasphil/water_monitoring.git /var/www/water_monitoring

# Set permissions
sudo chown -R www-data:www-data /var/www/water_monitoring
sudo chmod -R 755 /var/www/water_monitoring
```

3. **Database Setup**
```bash
# Create database
sudo mysql -u root -p
CREATE DATABASE water_quality_db;
CREATE USER 'water_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON water_quality_db.* TO 'water_user'@'localhost';
FLUSH PRIVILEGES;

# Import schema
mysql -u water_user -p water_quality_db < database/water_quality_db_complete.sql
```

4. **Configuration**
```bash
# Copy and configure environment
cp config/env.example config/.env
nano config/.env

# Configure web server
sudo nano /etc/apache2/sites-available/water_monitoring.conf
```

5. **Security Hardening**
```bash
# Enable HTTPS
sudo a2enmod ssl
sudo a2enmod rewrite

# Configure firewall
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp
```

#### Performance Optimization

1. **Database Optimization**
```sql
-- Enable query cache
SET GLOBAL query_cache_size = 134217728;
SET GLOBAL query_cache_type = 1;

-- Optimize tables
OPTIMIZE TABLE water_readings;
ANALYZE TABLE water_readings;
```

2. **PHP Optimization**
```ini
; php.ini optimizations
memory_limit = 256M
max_execution_time = 300
opcache.enable = 1
opcache.memory_consumption = 128
```

3. **Web Server Optimization**
```apache
# Apache optimizations
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

### Monitoring & Maintenance

#### Health Checks
```bash
# Database connectivity
mysql -u water_user -p -e "SELECT 1;"

# Application status
curl -I http://your-domain.com/water_monitoring/

# Log monitoring
tail -f /var/log/apache2/error.log
```

#### Backup Procedures
```bash
# Database backup
mysqldump -u water_user -p water_quality_db > backup_$(date +%Y%m%d).sql

# Application backup
tar -czf water_monitoring_$(date +%Y%m%d).tar.gz /var/www/water_monitoring/

# Automated backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u water_user -p water_quality_db > /backups/db_$DATE.sql
tar -czf /backups/app_$DATE.tar.gz /var/www/water_monitoring/
find /backups -name "*.sql" -mtime +7 -delete
find /backups -name "*.tar.gz" -mtime +7 -delete
```

## 🔧 Troubleshooting

### Common Issues

#### Database Connection Issues
```bash
# Check database service
sudo systemctl status mysql

# Verify connection settings
mysql -u water_user -p -h localhost

# Check error logs
sudo tail -f /var/log/mysql/error.log
```

#### ESP32 Connection Problems
```cpp
// Debug WiFi connection
void debugWiFi() {
    Serial.print("WiFi Status: ");
    Serial.println(WiFi.status());
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
}
```

#### Sensor Reading Issues
```cpp
// Verify sensor connections
void testSensors() {
    Serial.print("pH: ");
    Serial.println(analogRead(PH_PIN));
    Serial.print("Turbidity: ");
    Serial.println(analogRead(TURBIDITY_PIN));
    Serial.print("TDS: ");
    Serial.println(analogRead(TDS_PIN));
}
```

#### Web Interface Issues
```bash
# Check PHP errors
sudo tail -f /var/log/apache2/error.log

# Verify file permissions
ls -la /var/www/water_monitoring/

# Test PHP configuration
php -v
php -m | grep mysql
```

### Performance Issues

#### Slow Database Queries
```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- Analyze slow queries
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;
```

#### Memory Issues
```bash
# Check memory usage
free -h
ps aux --sort=-%mem | head -10

# Optimize PHP memory
php -i | grep memory_limit
```

### Security Issues

#### Session Security
```php
// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
```

#### SQL Injection Prevention
```php
// Use prepared statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
```

## 📞 Support

### Getting Help

- **Documentation**: Check this README and inline code comments
- **Issues**: [GitHub Issues](https://github.com/Anoncasphil/water_monitoring/issues)
- **Discussions**: [GitHub Discussions](https://github.com/Anoncasphil/water_monitoring/discussions)
- **Wiki**: [Project Wiki](https://github.com/Anoncasphil/water_monitoring/wiki)

### Community

- **Contributors**: See [Contributors](https://github.com/Anoncasphil/water_monitoring/graphs/contributors)
- **Code of Conduct**: [Contributor Covenant](CODE_OF_CONDUCT.md)
- **Changelog**: [CHANGELOG.md](CHANGELOG.md)

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

### License Summary

- **Commercial Use**: ✅ Allowed
- **Modification**: ✅ Allowed
- **Distribution**: ✅ Allowed
- **Private Use**: ✅ Allowed
- **Liability**: ❌ No liability
- **Warranty**: ❌ No warranty

## 🙏 Acknowledgments

- **ESP32 Community**: For hardware support and examples
- **Arduino Community**: For sensor libraries and examples
- **PHP Community**: For web development best practices
- **Open Source Contributors**: For various libraries and tools

---

**Version**: 3.2.0  
**Last Updated**: December 2024  
**Maintainer**: Water Quality Monitoring System Team  
**Repository**: [https://github.com/Anoncasphil/water_monitoring](https://github.com/Anoncasphil/water_monitoring)

## 📝 Recent Updates (v3.2.0)

### ✨ New Features
- **Enhanced Schedule Management**: Improved layout consistency and streamlined interface
- **Real-time Statistics**: Live schedule statistics with next execution tracking
- **Bulk Operations**: Support for bulk schedule deletion and management
- **Execution Logs**: Detailed logging with success/failure tracking and error messages
- **Improved UI/UX**: Better visual consistency across admin sections

### 🔧 Improvements
- **Layout Consistency**: Fixed execution logs section positioning to match scheduled operations
- **UI Streamlining**: Removed redundant statistics cards for cleaner interface
- **Code Organization**: Better structured schedule management code
- **Error Handling**: Enhanced error reporting and user feedback
- **Performance**: Optimized schedule loading and status updates

### 🐛 Bug Fixes
- **Schedule Display**: Fixed schedule table rendering and status indicators
- **Modal Functionality**: Improved schedule creation and editing modals
- **Real-time Updates**: Enhanced live status updates and statistics
- **Mobile Responsiveness**: Better mobile layout for schedule management 