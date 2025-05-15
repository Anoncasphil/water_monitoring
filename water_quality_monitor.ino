// Pin definitions
const int turbidityPin = A0;  // Turbidity sensor analog output
const int tdsPin = A1;        // TDS sensor analog output

// Raw and converted sensor readings
int rawTurbidity = 0;
int rawTDS = 0;
float turbidityNTU = 0.0;
float tdsPPM = 0.0;

void setup() {
  Serial.begin(9600);
  Serial.println("Starting TDS and Turbidity Sensor Readings...");
}

void loop() {
  // Read raw analog values
  rawTurbidity = analogRead(turbidityPin);
  rawTDS = analogRead(tdsPin);

  // --- TURBIDITY ---
  // Convert to NTU (you may need to calibrate this formula)
  turbidityNTU = map(rawTurbidity, 0, 1023, 100, 0);  // higher raw = cleaner water

  // --- TDS ---
  // Convert to approximate PPM (calibrate for your sensor)
  tdsPPM = map(rawTDS, 0, 1023, 0, 1000);

  // Print values to Serial Monitor
  Serial.println("================================");
  Serial.print("Raw Turbidity Value: ");
  Serial.println(rawTurbidity);
  Serial.print("Turbidity (NTU): ");
  Serial.println(turbidityNTU);

  Serial.print("Raw TDS Value: ");
  Serial.println(rawTDS);
  Serial.print("TDS (ppm): ");
  Serial.println(tdsPPM);
  Serial.println("================================");

  // Format data for PHP script
  Serial.print("DATA:");
  Serial.print(turbidityNTU);
  Serial.print(",");
  Serial.println(tdsPPM);

  delay(2000);  // Delay 2 seconds between readings
}