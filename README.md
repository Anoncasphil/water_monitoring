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
   - Create new database named `water_quality`
   - Import `setup_database.sql`

4. **Configuration**:
   - Update WiFi credentials in `relay_control.ino`
   - Update server URL in `relay_control.ino` if using ngrok or different server
   - Configure sensor calibration values if needed
   - Calibrate pH sensor using standard solutions (4.0, 7.0, 10.0)

## Features

- Real-time water quality monitoring
- pH measurement with DFRobot sensor
- Web-based dashboard
- Historical data visualization
- Relay control for water treatment
- Mobile-responsive design
- Dark/Light mode support
- Automatic data logging
- Sensor calibration support

## Project Structure

```
projtest/
├── relay_control/           # ESP32 code
│   └── relay_control.ino
├── config/                  # Configuration files
│   └── database.php
├── index.php               # Main dashboard
├── upload.php              # Sensor data endpoint
├── get_readings.php        # Data retrieval endpoint
├── relay_control.php       # Relay control endpoint
├── create_relay_table.php  # Database setup for relays
├── serial_handler.php      # Serial communication handler
└── setup_database.sql      # Database schema
```

## Usage

1. Power on the ESP32
2. Access the dashboard at `http://localhost/projtest/`
3. Monitor water quality parameters:
   - pH levels
   - Turbidity
   - TDS
4. Control relays through the web interface
5. View historical data and trends

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

## Security Notes

- Change default database credentials
- Use HTTPS in production
- Implement proper authentication
- Regular security updates
- Secure API endpoints
- Validate sensor data

## License

This project is licensed under the MIT License - see the LICENSE file for details. 