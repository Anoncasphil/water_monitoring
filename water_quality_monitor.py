import serial
import time
import mysql.connector
from datetime import datetime

# Database configuration
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',  # Default XAMPP MySQL password is empty
    'database': 'water_quality_db'
}

# Configure the serial port
ser = serial.Serial('COM3', 9600, timeout=1)

def save_to_database(turbidity, tds):
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()
        
        query = "INSERT INTO water_quality_readings (turbidity_ntu, tds_ppm) VALUES (%s, %s)"
        cursor.execute(query, (turbidity, tds))
        
        conn.commit()
        cursor.close()
        conn.close()
        print(f"Data saved: Turbidity={turbidity}NTU, TDS={tds}ppm")
    except Exception as e:
        print(f"Database error: {e}")

print("Starting water quality monitoring...")
print("Press Ctrl+C to exit")

try:
    while True:
        if ser.in_waiting:
            line = ser.readline().decode('utf-8').strip()
            
            # Look for the DATA: line
            if line.startswith('DATA:'):
                # Parse the data
                data = line[5:].split(',')  # Remove 'DATA:' and split by comma
                if len(data) == 2:
                    try:
                        turbidity = float(data[0])
                        tds = float(data[1])
                        save_to_database(turbidity, tds)
                    except ValueError as e:
                        print(f"Error parsing data: {e}")
            
        time.sleep(0.1)

except KeyboardInterrupt:
    print("\nExiting...")
    ser.close() 