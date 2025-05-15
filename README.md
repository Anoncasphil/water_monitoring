# Water Quality Monitoring System

A real-time water quality monitoring system that displays turbidity and TDS (Total Dissolved Solids) readings from Arduino sensors.

## Features

- Real-time sensor data display
- Historical data visualization
- Responsive web interface
- Automatic data logging to MySQL database
- Live updates every 5 seconds

## Hardware Requirements

- Arduino board
- Turbidity sensor
- TDS sensor
- USB cable for Arduino connection

## Software Requirements

- XAMPP (Apache + MySQL)
- Python 3.x
- Required Python packages:
  - pyserial
  - mysql-connector-python

## Installation

1. Clone this repository:
```bash
git clone https://github.com/yourusername/water-quality-monitor.git
cd water-quality-monitor
```

2. Set up the database:
   - Start XAMPP and ensure MySQL is running
   - Create a new database named `water_quality_db`
   - Import the `create_table.sql` file

3. Install Python dependencies:
```bash
pip install pyserial mysql-connector-python
```

4. Configure the Arduino:
   - Upload the `water_quality_monitor.ino` sketch to your Arduino
   - Note the COM port your Arduino is connected to

5. Update the Python script:
   - Open `water_quality_monitor.py`
   - Update the COM port if necessary (default is 'COM3')

## Usage

1. Start the Python script to read sensor data:
```bash
python water_quality_monitor.py
```

2. Access the web interface:
   - Open your web browser
   - Navigate to `http://localhost/water-quality-monitor`

## Project Structure

```
water-quality-monitor/
├── README.md
├── water_quality_monitor.ino    # Arduino code
├── water_quality_monitor.py     # Python script for data logging
├── create_table.sql            # Database schema
├── index.php                   # Main web interface
└── get_readings.php           # API endpoint for data retrieval
```

## Contributing

Feel free to submit issues and enhancement requests!

## License

This project is licensed under the MIT License - see the LICENSE file for details. 