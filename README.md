# Water Quality Monitoring System

A comprehensive real-time water quality monitoring system with ESP32 microcontroller, featuring a modern web-based admin dashboard, user management, activity logging, and relay control capabilities.

## 🚀 Features

### 🔐 Authentication & User Management
- **Secure Login System** - Session-based authentication with password validation
- **User Management** - Complete CRUD operations for user administration
- **Role-Based Access** - Admin and staff role management
- **Password Security** - Bcrypt hashing with strong password requirements
- **User Status Management** - Archive/activate users instead of deletion

### 📊 Activity Logging & Audit Trail
- **Comprehensive Activity Tracking** - Log all user actions and system events
- **Activity Logs Dashboard** - Filter, search, and paginate through activities
- **Detailed Audit Trail** - Track who performed what actions and when
- **Security Monitoring** - Monitor user creation, updates, and system access

### 🎨 Modern Admin Dashboard
- **Real-time Monitoring** - Live water quality data visualization
- **Interactive Charts** - Historical data with Chart.js integration
- **Responsive Design** - Mobile-friendly interface with Tailwind CSS
- **Dark/Light Theme** - User preference toggle
- **Relay Control Panel** - Web-based system automation control

### 📈 Water Quality Monitoring
- **Multi-Sensor Support** - pH, Turbidity, TDS, and Temperature monitoring
- **Real-time Data** - Live sensor readings with automatic updates
- **Historical Analysis** - Trend visualization and data export
- **Quality Alerts** - Automated notifications for water quality issues
- **Data Visualization** - Interactive charts and graphs

### 🛡️ Security Features
- **Session Management** - Secure user sessions with proper cleanup
- **SQL Injection Prevention** - Prepared statements throughout
- **XSS Protection** - Input sanitization and output escaping
- **Protected Routes** - Authentication middleware for admin areas
- **Environment Configuration** - Secure credential management

## 🏗️ System Architecture

### **Frontend**
- **Modern UI** - Tailwind CSS with responsive design
- **Interactive Components** - Real-time forms, modals, and notifications
- **Progressive Enhancement** - Works without JavaScript
- **Accessibility** - WCAG compliant interface

### **Backend**
- **PHP 8.0+** - Modern PHP with type safety
- **MySQL/MariaDB** - Reliable data storage
- **RESTful APIs** - Clean API endpoints for AJAX operations
- **MVC Pattern** - Organized code structure

### **Hardware Integration**
- **ESP32 Microcontroller** - WiFi-enabled sensor hub
- **Multiple Sensors** - pH, Turbidity, TDS, Temperature
- **Relay Control** - 4-channel automation system
- **Real-time Communication** - HTTP-based data transmission

## 📋 Requirements

### Hardware Requirements
- ESP32 Development Board
- DFRobot pH Sensor
- Turbidity Sensor
- TDS (Total Dissolved Solids) Sensor
- 4-Channel Relay Module
- Jumper Wires
- Power Supply (5V/3.3V)
- USB Cable (for programming ESP32)

### Software Requirements
- **Arduino IDE** with ESP32 board support
- **XAMPP** (Apache + MySQL + PHP)
- **PHP 8.0** or higher
- **MySQL/MariaDB** 5.7+
- **Required Libraries**:
  - WiFi.h (ESP32)
  - HTTPClient.h (ESP32)
  - ArduinoJson.h (version 6.x)
  - DFRobot_PH.h

## 🔧 Installation & Setup

### 1. ESP32 Setup
```bash
# Install Arduino IDE
# Add ESP32 board support:
# 1. File > Preferences
# 2. Add: https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
# 3. Tools > Board > Boards Manager > Search "esp32" > Install
# 4. Install required libraries:
#    - ArduinoJson
#    - DFRobot_PH
# 5. Select board: Tools > Board > ESP32 Arduino > ESP32 Dev Module
# 6. Upload relay_control.ino to ESP32
```

### 2. Web Server Setup
```bash
# Install XAMPP
# Copy project files to htdocs/projtest/
# Start Apache and MySQL services
```

### 3. Database Setup
```sql
-- Create database
CREATE DATABASE water_quality_db;

-- Import schema
-- Run the SQL files in order:
-- 1. config/database.php (creates users table)
-- 2. admin/actlogs/create_activity_logs_table.sql
-- 3. Any additional setup scripts
```

### 4. Environment Configuration
```bash
# Copy environment template
cp config/env.example config/.env

# Edit config/.env with your settings:
DB_HOST=127.0.0.1
DB_PORT=3307
DB_NAME=water_quality_db
DB_USERNAME=root
DB_PASSWORD=
APP_ENV=development
APP_DEBUG=true
```

### 5. Initial Setup
```bash
# Create first admin user
# Access: http://localhost/projtest/login/
# Default credentials will be set up during first run
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
│   ├── user/                 # User management
│   │   ├── user.php          # User management interface
│   │   └── index.php         # Entry point
│   ├── sidebar/              # Navigation component
│   │   └── sidebar.php       # Sidebar navigation
│   ├── monitor/              # Real-time monitoring
│   ├── analytics/            # Data analytics
│   ├── controls/             # System controls
│   ├── schedule/             # Scheduling system
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
│   ├── check_data.php        # Data verification
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
└── README.md                 # This file
```

## 🔌 Pin Configuration (ESP32)

| Component | GPIO Pin | Description |
|-----------|----------|-------------|
| pH Sensor | GPIO34 | ADC1 input |
| Turbidity Sensor | GPIO33 | ADC1 input |
| TDS Sensor | GPIO32 | ADC1 input |
| Relay IN1 | GPIO25 | Pool to Filter |
| Relay IN2 | GPIO26 | Filter to Pool |
| Relay IN3 | GPIO27 | Dispenser |
| Relay IN4 | GPIO14 | Spare |

## 🚀 Usage

### Access Points
- **Main Application**: `http://localhost/projtest/`
- **Admin Dashboard**: `http://localhost/projtest/admin/dashboard/`
- **User Management**: `http://localhost/projtest/admin/user/`
- **Activity Logs**: `http://localhost/projtest/admin/actlogs/`
- **Login**: `http://localhost/projtest/login/`

### Key Features
1. **Real-time Monitoring** - Live sensor data with automatic updates
2. **User Management** - Create, edit, archive, and activate users
3. **Activity Tracking** - Complete audit trail of all system activities
4. **Relay Control** - Web-based automation control
5. **Data Visualization** - Interactive charts and historical analysis
6. **Mobile Responsive** - Works on all devices

## 🔒 Security

### Authentication
- Session-based authentication
- Password hashing with bcrypt
- Role-based access control
- Secure logout with session cleanup

### Data Protection
- SQL injection prevention
- XSS protection
- Input validation and sanitization
- Environment-based configuration

### Access Control
- Protected admin routes
- User permission management
- Activity logging for audit trails
- Secure API endpoints

## 🐛 Troubleshooting

### ESP32 Issues
- **Connection Problems**: Check WiFi credentials and server URL
- **Sensor Readings**: Verify wiring and calibration
- **Relay Control**: Check GPIO connections and power supply

### Web Application Issues
- **Database Connection**: Verify credentials in `.env` file
- **Session Problems**: Check PHP session configuration
- **Permission Errors**: Ensure proper file permissions

### Sensor Calibration
- **pH Sensor**: Use standard buffer solutions (4.0, 7.0, 10.0)
- **Turbidity**: Calibrate with known turbidity standards
- **TDS**: Use calibration solutions for accurate readings

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📞 Support

For support and questions:
- Check the troubleshooting section
- Review the configuration documentation
- Ensure all requirements are met
- Verify database and server setup

---

**Version**: 2.0.0  
**Last Updated**: August 2025  
**Compatibility**: PHP 8.0+, MySQL 5.7+, ESP32 Arduino Core 2.0+ 