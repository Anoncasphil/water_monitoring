import serial
import time
import mysql.connector
from datetime import datetime
import json
from flask import Flask, request, jsonify
from flask_cors import CORS  # Add CORS support

app = Flask(__name__)
CORS(app)  # Enable CORS for all routes

# Database configuration
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',  # Default XAMPP MySQL password is empty
    'database': 'water_quality_db'
}

# Configure the serial port
ser = serial.Serial('COM3', 9600, timeout=1)

# Keep track of relay states (all OFF by default)
relay_states = {1: 0, 2: 0, 3: 0, 4: 0}

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

def send_relay_command(relay, state):
    try:
        command = f"RELAY:{relay},{state}\n"
        ser.write(command.encode())
        relay_states[relay] = state  # Update the state in our tracking
        print(f"Sent command: {command.strip()}")
    except Exception as e:
        print(f"Error sending relay command: {e}")

def update_relay_states_from_arduino(states_str):
    try:
        states = [int(x) for x in states_str.split(',')]
        for i, state in enumerate(states, 1):
            if i <= 4:  # Ensure we only update valid relays
                relay_states[i] = state
    except Exception as e:
        print(f"Error parsing relay states: {e}")

@app.route('/relay_control.php', methods=['POST'])
def control_relay():
    try:
        relay = int(request.form.get('relay'))
        state = int(request.form.get('state'))
        
        if 1 <= relay <= 4 and state in [0, 1]:
            send_relay_command(relay, state)
            return jsonify({'success': True, 'states': relay_states})
        else:
            return jsonify({'error': 'Invalid parameters'})
    except Exception as e:
        return jsonify({'error': str(e)})

@app.route('/get_relay_states.php', methods=['GET'])
def get_relay_states():
    return jsonify({'states': relay_states})

def read_serial_data():
    while True:
        if ser.in_waiting:
            line = ser.readline().decode('utf-8').strip()
            
            # Handle relay state updates
            if line.startswith('RELAY_STATE:'):
                states = line[12:]  # Remove 'RELAY_STATE:'
                update_relay_states_from_arduino(states)
                print(f"Updated relay states: {relay_states}")
            
            # Handle initial state
            elif line.startswith('INIT:'):
                states = line[5:]  # Remove 'INIT:'
                update_relay_states_from_arduino(states)
                print(f"Initial relay states: {relay_states}")
            
            # Handle sensor data
            elif line.startswith('DATA:'):
                data = line[5:].split(',')  # Remove 'DATA:' and split by comma
                if len(data) == 2:
                    try:
                        turbidity = float(data[0])
                        tds = float(data[1])
                        save_to_database(turbidity, tds)
                    except ValueError as e:
                        print(f"Error parsing data: {e}")
        
        time.sleep(0.1)

if __name__ == '__main__':
    print("Starting water quality monitoring with relay control...")
    print("Press Ctrl+C to exit")
    
    # Start the serial data reading in a separate thread
    import threading
    serial_thread = threading.Thread(target=read_serial_data, daemon=True)
    serial_thread.start()
    
    # Start the Flask server
    app.run(host='0.0.0.0', port=5000) 