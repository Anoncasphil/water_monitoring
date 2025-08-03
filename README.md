# Water Quality Monitoring System

A comprehensive real-time water quality monitoring system with ESP32 microcontroller, featuring a modern web-based admin dashboard, advanced analytics, real-time monitoring, automated controls, user management, and comprehensive activity logging.

## 🚀 Features

### 🔐 Authentication & User Management
- **Secure Login System** - Session-based authentication with password validation
- **User Management** - Complete CRUD operations for user administration
- **Role-Based Access** - Admin and staff role management
- **Password Security** - Bcrypt hashing with strong password requirements
- **User Status Management** - Archive/activate users instead of deletion

### 📊 Real-Time Monitoring & Analytics
- **Live Sensor Dashboard** - Real-time water quality parameter monitoring
- **Advanced Analytics** - Comprehensive data analysis with trend visualization
- **Interactive Charts** - Multiple chart types (line, bar, area) with Chart.js
- **Historical Data Analysis** - 24h, 7d, 30d, and 90d data views
- **Quality Insights** - Automated water quality assessment and recommendations
- **Statistical Analysis** - Min/max/average calculations with trend indicators

### 🎛️ System Control & Automation
- **Relay Control Panel** - Web-based automation system with 4-channel control
- **Schedule Management** - Automated relay scheduling with timezone support
- **Real-time Status Monitoring** - Live system status and uptime tracking
- **Automation Rules** - Smart control based on water quality parameters
- **System Logs** - Comprehensive control action logging
- **Bulk Operations** - All On/Off controls for system management

### 📈 Water Quality Monitoring
- **Multi-Sensor Support** - pH, Turbidity, TDS, and Temperature monitoring
- **Real-time Data** - Live sensor readings with automatic updates every 5 seconds
- **Quality Assessment** - Automated water quality status evaluation
- **Alert System** - Real-time notifications for water quality issues
- **Parameter Ranges** - Visual representation of acceptable ranges

### 📋 Activity Logging & Audit Trail
- **Comprehensive Activity Tracking** - Log all user actions and system events
- **Activity Logs Dashboard** - Filter, search, and paginate through activities
- **Detailed Audit Trail** - Track who performed what actions and when
- **Security Monitoring** - Monitor user creation, updates, and system access

### 🎨 Modern Admin Dashboard
- **Responsive Design** - Mobile-friendly interface with Tailwind CSS
- **Dark/Light Theme** - User preference toggle with persistent settings
- **Professional UI** - Modern card-based layout with smooth animations
- **Real-time Updates** - Live data refresh without page reload
- **Interactive Elements** - Hover effects, transitions, and loading states

### 🛡️ Security Features
- **Session Management** - Secure user sessions with proper cleanup
- **SQL Injection Prevention** - Prepared statements throughout
- **XSS Protection** - Input sanitization and output escaping
- **Protected Routes** - Authentication middleware for admin areas
- **Environment Configuration** - Secure credential management

## 🏗️ System Architecture

### **Frontend**
- **Modern UI Framework** - Tailwind CSS with responsive design
- **Interactive Components** - Real-time forms, modals, and notifications
- **Chart Visualization** - Chart.js for advanced data visualization
- **Progressive Enhancement** - Works without JavaScript
- **Accessibility** - WCAG compliant interface

### **Backend**
- **PHP 8.0+** - Modern PHP with type safety
- **MySQL/MariaDB** - Reliable data storage with optimized queries
- **RESTful APIs** - Clean API endpoints for AJAX operations
- **MVC Pattern** - Organized code structure
- **Database Abstraction** - Singleton pattern for database connections

### **Hardware Integration**
- **ESP32 Microcontroller** - WiFi-enabled sensor hub
- **Multiple Sensors** - pH, Turbidity, TDS, Temperature
- **Relay Control** - 4-channel automation system
- **Real-time Communication** - HTTP-based data transmission
- **Power Management** - Efficient power consumption monitoring

## 📋 Requirements

### Hardware Requirements
- **ESP32 Development Board** (ESP32-WROOM-32 or similar)
- **DFRobot pH Sensor** with calibration kit
- **Turbidity Sensor** (TSW-30 or similar)
- **TDS (Total Dissolved Solids) Sensor** (Gravity Analog TDS Sensor)
- **Temperature Sensor** (DS18B20 or similar)
- **4-Channel Relay Module** (5V/3.3V compatible)
- **Jumper Wires** and breadboard
- **Power Supply** (5V/3.3V, 2A minimum)
- **USB Cable** (for programming ESP32)

### Software Requirements
- **Arduino IDE** with ESP32 board support (v2.0+)
- **XAMPP** (Apache + MySQL + PHP) or similar stack
- **PHP 8.0** or higher
- **MySQL/MariaDB** 5.7+
- **Required Libraries**:
  - WiFi.h (ESP32 core)
  - HTTPClient.h (ESP32 core)
  - ArduinoJson.h (version 6.x)
  - DFRobot_PH.h
  - OneWire.h (for temperature sensor)
  - DallasTemperature.h

## 🔧 Installation & Setup

### 1. ESP32 Setup
```bash
# Install Arduino IDE
# Add ESP32 board support:
# 1. File > Preferences
# 2. Add: https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
# 3. Tools > Board > Boards Manager > Search "esp32" > Install
# 4. Install required libraries:
#    - ArduinoJson by Benoit Blanchon
#    - DFRobot_PH by DFRobot
#    - OneWire by Paul Stoffregen
#    - DallasTemperature by Miles Burton
# 5. Select board: Tools > Board > ESP32 Arduino > ESP32 Dev Module
# 6. Configure WiFi settings in relay_control.ino
# 7. Upload relay_control.ino to ESP32
```

### 2. Web Server Setup
```bash
# Install XAMPP or similar
# Copy project files to htdocs/projtest/
# Start Apache and MySQL services
# Ensure mod_rewrite is enabled
```

### 3. Database Setup
```sql
-- Create database
CREATE DATABASE water_quality_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Import schema
-- Run the SQL files in order:
-- 1. config/database.php (creates users table)
-- 2. admin/actlogs/create_activity_logs_table.sql
-- 3. Any additional setup scripts

-- Create water_readings table
CREATE TABLE water_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turbidity_ntu DECIMAL(5,2) NOT NULL,
    tds_ppm DECIMAL(6,2) NOT NULL,
    ph DECIMAL(3,2) NOT NULL,
    temperature DECIMAL(4,2) NOT NULL,
    reading_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 4. Environment Configuration
```bash
# Copy environment template
cp config/env.example config/.env

# Edit config/.env with your settings:
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=water_quality_db
DB_USERNAME=root
DB_PASSWORD=your_password
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/projtest
```

### 5. Initial Setup
```bash
# Create first admin user
# Access: http://localhost/projtest/login/
# Default credentials will be set up during first run
# Ensure proper file permissions (755 for directories, 644 for files)
```

### 6. Schedule System Setup (Optional)
```bash
# For automatic schedule execution, set up Windows Task Scheduler:
# 1. Open Task Scheduler (Win + R → taskschd.msc)
# 2. Create Basic Task: "Schedule Execution"
# 3. Trigger: Daily, repeat every 1 minute
# 4. Action: Start program
# 5. Program: C:\xampp\php\php.exe
# 6. Arguments: C:\xampp\htdocs\projtest\api\execute_schedules.php
# 7. Start in: C:\xampp\htdocs\projtest\api
```

## 📁 Project Structure

```
projtest/
├── admin/                     # Admin dashboard
│   ├── actlogs/              # Activity logging system
│   │   ├── actlogs.php       # Activity logs interface
│   │   ├── index.php         # Entry point
│   │   └── *.sql             # Database schema files
│   ├── dashboard/            # Main dashboard
│   │   ├── dashboard.php     # Dashboard interface
│   │   └── index.php         # Entry point
│   ├── monitor/              # Real-time monitoring
│   │   ├── monitor.php       # Sensor monitoring interface
│   │   └── index.php         # Entry point
│   ├── analytics/            # Data analytics
│   │   ├── analytics.php     # Analytics dashboard
│   │   └── index.php         # Entry point
│   ├── controls/             # System controls
│   │   ├── controls.php      # Control panel interface
│   │   └── index.php         # Entry point
│   ├── user/                 # User management
│   │   ├── user.php          # User management interface
│   │   └── index.php         # Entry point
│   ├── sidebar/              # Navigation component
│   │   └── sidebar.php       # Sidebar navigation
│   ├── schedule/             # Schedule management system
│   │   ├── schedule.php      # Schedule management interface
│   │   ├── index.php         # Entry point
│   │   └── README.md         # Schedule system documentation
│   ├── alerts/               # Alert management
│   ├── reports/              # Reporting system
│   └── .htaccess             # Security rules
├── api/                      # API endpoints
│   ├── create_user.php       # User creation API
│   ├── update_user.php       # User update API
│   ├── update_user_status.php # User status management
│   ├── get_readings.php      # Sensor data retrieval
│   ├── upload.php            # Sensor data upload
│   ├── relay_control.php     # Relay control API
│   ├── schedule_control.php  # Schedule management API
│   ├── execute_schedules.php # Automated schedule execution
│   ├── check_data.php        # Data verification
│   ├── check_email.php       # Email validation
│   └── .htaccess             # API security
├── config/                   # Configuration
│   ├── database.php          # Database connection class
│   ├── EnvLoader.php         # Environment loader
│   ├── env.example           # Environment template
│   └── README.md             # Configuration docs
├── login/                    # Authentication
│   ├── index.php             # Login interface
│   └── logout.php            # Logout handler
├── relay_control/            # ESP32 code
│   └── relay_control.ino     # Main Arduino sketch
├── index.php                 # Main application entry
├── LICENSE                   # MIT License
└── README.md                 # This file
```

## 🔌 Pin Configuration (ESP32)

| Component | GPIO Pin | Description | Voltage |
|-----------|----------|-------------|---------|
| pH Sensor | GPIO34 | ADC1 input | 3.3V |
| Turbidity Sensor | GPIO33 | ADC1 input | 3.3V |
| TDS Sensor | GPIO32 | ADC1 input | 3.3V |
| Temperature Sensor | GPIO4 | OneWire bus | 3.3V |
| Relay IN1 | GPIO25 | Pool to Filter | 3.3V |
| Relay IN2 | GPIO26 | Filter to Pool | 3.3V |
| Relay IN3 | GPIO27 | Dispenser | 3.3V |
| Relay IN4 | GPIO14 | Spare | 3.3V |

## 🚀 Usage

### Access Points
- **Main Application**: `http://localhost/projtest/`
- **Admin Dashboard**: `http://localhost/projtest/admin/dashboard/`
- **Real-time Monitor**: `http://localhost/projtest/admin/monitor/`
- **Analytics**: `http://localhost/projtest/admin/analytics/`
- **Control Panel**: `http://localhost/projtest/admin/controls/`
- **Schedule Management**: `http://localhost/projtest/admin/schedule/`
- **User Management**: `http://localhost/projtest/admin/user/`
- **Activity Logs**: `http://localhost/projtest/admin/actlogs/`
- **Login**: `http://localhost/projtest/login/`

### Key Features

#### 📊 Real-time Monitoring
- **Live Sensor Data** - Real-time updates every 5 seconds
- **Quality Status** - Automated water quality assessment
- **Parameter Tracking** - All sensors with visual indicators
- **Alert System** - Immediate notifications for issues

#### 📈 Advanced Analytics
- **Trend Analysis** - Historical data visualization
- **Statistical Insights** - Min/max/average calculations
- **Quality Insights** - Automated recommendations
- **Data Export** - Export capabilities for reporting

#### 🎛️ System Control
- **Relay Management** - Individual and bulk control
- **Schedule Management** - Automated relay scheduling with timezone support
- **Automation Rules** - Smart system automation
- **Status Monitoring** - Real-time system status
- **Control Logs** - Complete action tracking

#### 👥 User Management
- **User Administration** - Create, edit, archive users
- **Role Management** - Admin and staff roles
- **Activity Tracking** - Complete audit trail
- **Security Features** - Password policies and session management

## 🔒 Security

### Authentication
- **Session-based authentication** with secure session handling
- **Password hashing** with bcrypt (cost factor 12)
- **Role-based access control** with permission validation
- **Secure logout** with session cleanup and regeneration

### Data Protection
- **SQL injection prevention** using prepared statements
- **XSS protection** with input sanitization and output escaping
- **CSRF protection** with token validation
- **Input validation** with comprehensive sanitization

### Access Control
- **Protected admin routes** with authentication middleware
- **User permission management** with role-based access
- **Activity logging** for complete audit trails
- **Secure API endpoints** with proper validation

## 🐛 Troubleshooting

### ESP32 Issues
- **Connection Problems**: 
  - Check WiFi credentials in relay_control.ino
  - Verify server URL and port
  - Ensure ESP32 has stable power supply
- **Sensor Readings**: 
  - Verify wiring connections
  - Check sensor calibration
  - Ensure proper voltage levels (3.3V)
- **Relay Control**: 
  - Check GPIO connections
  - Verify relay module power supply
  - Test individual relay channels

### Web Application Issues
- **Database Connection**: 
  - Verify credentials in `.env` file
  - Check MySQL service status
  - Ensure database exists and is accessible
- **Session Problems**: 
  - Check PHP session configuration
  - Verify session storage permissions
  - Clear browser cookies if needed
- **Permission Errors**: 
  - Ensure proper file permissions (755 for dirs, 644 for files)
  - Check web server user permissions
  - Verify .htaccess file configuration

### Sensor Calibration
- **pH Sensor**: 
  - Use standard buffer solutions (4.0, 7.0, 10.0)
  - Calibrate at room temperature
  - Rinse sensor between measurements
- **Turbidity**: 
  - Calibrate with known turbidity standards
  - Clean sensor regularly
  - Avoid air bubbles in measurements
- **TDS**: 
  - Use calibration solutions for accurate readings
  - Check temperature compensation
  - Clean electrodes regularly

### Performance Optimization
- **Database Queries**: 
  - Optimize slow queries with proper indexing
  - Use connection pooling for high traffic
  - Implement query caching where appropriate
- **Real-time Updates**: 
  - Adjust update frequency based on needs
  - Implement data compression for large datasets
  - Use WebSocket for better real-time performance

## 📊 API Documentation

### Sensor Data Endpoints
- `GET /api/get_readings.php` - Retrieve sensor data
- `POST /api/upload.php` - Upload sensor readings
- `GET /api/check_data.php` - Verify data integrity

### User Management Endpoints
- `POST /api/create_user.php` - Create new user
- `POST /api/update_user.php` - Update user information
- `POST /api/update_user_status.php` - Change user status
- `GET /api/check_email.php` - Validate email uniqueness

### Control Endpoints
- `GET /api/relay_control.php` - Get relay status
- `POST /api/relay_control.php` - Control relay states

### Schedule Management Endpoints
- `GET /api/schedule_control.php` - Get all schedules
- `POST /api/schedule_control.php` - Create/update schedule
- `DELETE /api/schedule_control.php` - Delete schedule(s)
- `GET /api/execute_schedules.php` - Manual schedule execution

## 🔄 Updates & Maintenance

### Regular Maintenance
- **Database Optimization** - Regular table optimization and cleanup
- **Log Rotation** - Manage activity log file sizes
- **Security Updates** - Keep dependencies updated
- **Backup Procedures** - Regular database and file backups

### Monitoring
- **System Health** - Monitor server resources and performance
- **Error Logging** - Track and resolve application errors
- **User Activity** - Monitor for suspicious activity
- **Sensor Health** - Track sensor accuracy and calibration

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes with proper documentation
4. Test thoroughly on different environments
5. Ensure code follows project standards
6. Submit a pull request with detailed description

## 📞 Support

For support and questions:
- **Documentation**: Check this README and inline code comments
- **Troubleshooting**: Review the troubleshooting section above
- **Configuration**: Verify all requirements and setup steps
- **Issues**: Check existing issues before creating new ones

### Common Issues
- **ESP32 not connecting**: Check WiFi credentials and server URL
- **Database errors**: Verify connection settings and permissions
- **Sensor readings inaccurate**: Check calibration and wiring
- **Relay not responding**: Verify GPIO connections and power supply

---

**Version**: 3.0.0  
**Last Updated**: December 2024  
**Compatibility**: PHP 8.0+, MySQL 5.7+, ESP32 Arduino Core 2.0+  
**License**: MIT License  
**Author**: Water Quality Monitoring System Team 