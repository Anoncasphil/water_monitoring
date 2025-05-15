import serial
import mysql.connector
import time
from datetime import datetime

# Database configuration
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'water_quality'
}

def connect_to_database():
    try:
        conn = mysql.connector.connect(**db_config)
        return conn
    except mysql.connector.Error as err:
        print(f"Database connection error: {err}")
        return None

def save_to_database(conn, turbidity, tds):
    try:
        cursor = conn.cursor()
        query = "INSERT INTO water_readings (turbidity, tds) VALUES (%s, %s)"
        cursor.execute(query, (turbidity, tds))
        conn.commit()
        print(f"Data saved: Turbidity={turbidity} NTU, TDS={tds} ppm")
    except mysql.connector.Error as err:
        print(f"Error saving data: {err}")

def main():
    # Connect to database
    conn = connect_to_database()
    if not conn:
        return

    try:
        # Open serial port
        ser = serial.Serial('COM3', 9600, timeout=1)
        print("Serial port opened successfully")
        print("Reading data...")

        while True:
            if ser.in_waiting:
                # Read line from serial port
                line = ser.readline().decode('utf-8').strip()
                
                # Check if data starts with "DATA:"
                if line.startswith("DATA:"):
                    # Extract values
                    values = line[5:].split(',')
                    if len(values) == 2:
                        try:
                            turbidity = float(values[0])
                            tds = float(values[1])
                            save_to_database(conn, turbidity, tds)
                        except ValueError as e:
                            print(f"Error parsing values: {e}")
            
            time.sleep(0.1)  # Small delay to prevent CPU overuse

    except serial.SerialException as e:
        print(f"Serial port error: {e}")
    except KeyboardInterrupt:
        print("\nProgram terminated by user")
    finally:
        if 'ser' in locals():
            ser.close()
        if conn:
            conn.close()

if __name__ == "__main__":
    main() 