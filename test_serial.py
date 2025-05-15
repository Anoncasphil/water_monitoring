import serial
import time

# Configure the serial port
# Change 'COM3' to match your Arduino's port
ser = serial.Serial('COM3', 9600, timeout=1)

print("Waiting for data from Arduino...")
print("Press Ctrl+C to exit")

try:
    while True:
        if ser.in_waiting:
            # Read a line from the serial port
            line = ser.readline().decode('utf-8').strip()
            print(f"Received: {line}")
        time.sleep(0.1)  # Small delay to prevent CPU overuse

except KeyboardInterrupt:
    print("\nExiting...")
    ser.close() 