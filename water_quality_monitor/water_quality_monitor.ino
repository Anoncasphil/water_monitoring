// Pin definitions
const int turbidityPin = A0;  // Turbidity sensor connected to A0
const int tdsPin = A1;        // TDS sensor connected to A1

// Variables to store sensor readings
float turbidityValue = 0;
float tdsValue = 0;

void setup() {
  Serial.begin(9600);
  Serial.println("Sensor reading started...");
}

void loop() {
  // Read sensor values
  turbidityValue = analogRead(turbidityPin);
  tdsValue = analogRead(tdsPin);
  
  // Convert analog readings to appropriate units
  // Note: These conversion formulas should be calibrated based on your specific sensors
  turbidityValue = map(turbidityValue, 0, 1023, 0, 100);  // Assuming 0-100 NTU range
  tdsValue = map(tdsValue, 0, 1023, 0, 1000);             // Assuming 0-1000 ppm range

  // Display sensor values on Serial Monitor
  Serial.print("Turbidity (NTU): ");
  Serial.println(turbidityValue);
  Serial.print("TDS (ppm): ");
  Serial.println(tdsValue);
  Serial.println("----------------------");
  
  // Wait for 10 seconds before next reading
  delay(10000);
}
