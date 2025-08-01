# Water Quality Monitoring System

A real-time water quality monitoring system using ESP32 microcontroller, with web-based dashboard for data visualization and relay control.

## Hardware Requirements

- ESP32 Development Board
- DFRobot pH Sensor
- Turbidity Sensor
- TDS (Total Dissolved Solids) Sensor
- 4-Channel Relay Module
- Jumper Wires
- Power Supply (5V/3.3V)
- USB Cable (for programming ESP32)

## Software Requirements

- Arduino IDE with ESP32 board support
- Required Libraries:
  - WiFi.h (ESP32)
  - HTTPClient.h (ESP32)
  - ArduinoJson.h (version 6.x)
  - DFRobot_PH.h
- XAMPP (for local web server)
- PHP 8.0 or higher
- MySQL/MariaDB

## Pin Configuration (ESP32)

- pH Sensor: GPIO34 (ADC1)
- Turbidity Sensor: GPIO33 (ADC1)
- TDS Sensor: GPIO32 (ADC1)
- Relay Module:
  - IN1: GPIO25
  - IN2: GPIO26
  - IN3: GPIO27
  - IN4: GPIO14

## Setup Instructions

1. **ESP32 Setup**:
   - Install Arduino IDE
   - Add ESP32 board support:
     - Open Arduino IDE
     - Go to File > Preferences
     - Add `https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json` to Additional Board Manager URLs
     - Go to Tools > Board > Boards Manager
     - Search for "esp32" and install
   - Install required libraries:
     - Tools > Manage Libraries
     - Search and install:
       - ArduinoJson
       - DFRobot_PH
   - Select ESP32 board:
     - Tools > Board > ESP32 Arduino > ESP32 Dev Module
   - Upload the code to ESP32

2. **Web Server Setup**:
   - Install XAMPP
   - Copy project files to `htdocs/projtest/`
   - Start Apache and MySQL services
   - Import database schema from `setup_database.sql`

3. **Database Setup**:
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create new database named `water_quality_db`
   - Import `setup_database.sql`

4. **Environment Configuration**:
   - Copy the environment template: `cp config/env.example config/.env`
   - Edit `config/.env` with your database credentials:
     ```env
     # Database Configuration
     DB_HOST=127.0.0.1
     DB_PORT=3307
     DB_NAME=water_quality_db
     DB_USERNAME=root
     DB_PASSWORD=
     
     # Environment
     APP_ENV=development
     APP_DEBUG=true
     ```
   - The application will automatically load these settings

5. **Configuration**:
   - Update WiFi credentials in `relay_control.ino`
   - Update server URL in `relay_control.ino` if using ngrok or different server
   - Configure sensor calibration values if needed
   - Calibrate pH sensor using standard solutions (4.0, 7.0, 10.0)

## Features

- Real-time water quality monitoring
- pH measurement with DFRobot sensor
- Web-based dashboard with modern UI
- Historical data visualization with charts
- Relay control for water treatment
- Mobile-responsive design
- Dark/Light mode support
- Automatic data logging
- Sensor calibration support
- Environment-based configuration
- Secure API endpoints
- Admin area protection
- Water quality alerts and notifications

## Project Structure

```
projtest/
├── admin/                  # Admin dashboard
│   ├── dashboard/         # Dashboard files
│   │   ├── index.php     # Dashboard entry point
│   │   └── dashboard.php # Main dashboard interface
│   └── .htaccess         # Admin area security
├── api/                   # API endpoints
│   ├── get_readings.php  # Data retrieval endpoint
│   ├── upload.php        # Sensor data endpoint
│   ├── relay_control.php # Relay control endpoint
│   └── .htaccess         # API security
├── config/                # Configuration files
│   ├── database.php      # Database connection class
│   ├── EnvLoader.php     # Environment variable loader
│   ├── env.example       # Environment configuration template
│   ├── .env              # Environment configuration (create from template)
│   └── README.md         # Configuration documentation
├── relay_control/         # ESP32 code
│   └── relay_control.ino
├── index.php              # Main application entry point
├── get_readings.php       # Legacy data endpoint
├── upload.php             # Legacy sensor endpoint
├── relay_control.php      # Legacy relay endpoint
├── check_data.php         # Database verification utility
└── setup_database.sql     # Database schema
```

## Usage

1. Power on the ESP32
2. Access the main application at `http://localhost/projtest/`
3. Access the admin dashboard at `http://localhost/projtest/admin/dashboard/`
4. Monitor water quality parameters:
   - pH levels
   - Turbidity
   - TDS
   - Temperature
5. Control relays through the web interface
6. View historical data and trends
7. Monitor water quality alerts and notifications

## Troubleshooting

- If ESP32 fails to connect:
  - Check WiFi credentials
  - Verify server URL
  - Check power supply
- If sensors show incorrect values:
  - Check wiring
  - Verify sensor calibration
  - Check power supply
  - For pH sensor:
    - Ensure proper calibration
    - Check electrode condition
    - Verify buffer solutions
- If relays don't respond:
  - Check relay module connections
  - Verify GPIO pin assignments
  - Check power supply to relay module
- If database connection fails:
  - Verify database credentials in `config/database.php`
  - Check if MySQL service is running
  - Verify database port number
  - Check database user permissions

## Security Features

- **Environment-based configuration**: No hardcoded credentials
- **Secure API endpoints**: Protected with .htaccess rules
- **Admin area protection**: IP-restricted access
- **Database security**: Connection encryption and prepared statements
- **Input validation**: All sensor data is validated
- **Error handling**: Secure error messages in production
- **File protection**: Sensitive files are protected from direct access

## Security Notes

- **Never commit `.env` files** to version control
- **Use HTTPS in production** for all communications
- **Implement proper authentication** for admin access
- **Regular security updates** for all components
- **Validate all sensor data** before processing
- **Use strong passwords** for database access
- **Monitor access logs** for suspicious activity
- **Keep encryption keys secure** and rotate regularly

## License

This project is licensed under the MIT License - see the LICENSE file for details. 