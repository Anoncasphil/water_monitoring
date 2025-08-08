#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// WiFi credentials
const char* ssid = "Converge_2.4GHz_3Fsb56";
const char* password = "TQkcXYGS";

// Server details
const char* serverUrl = "https://waterquality.triple7autosupply.com/api/upload.php";
const char* relayControlUrl = "https://waterquality.triple7autosupply.com/api/relay_control.php";

// Pin definitions
const int tdsPin = 32;        // TDS sensor on GPIO32
const int turbidityPin = 34;  // Turbidity sensor on GPIO34 (was 35)
const int phPin = 35;         // DFRobot pH sensor on GPIO35 (was 34)
const int relayPins[] = { 25, 26, 27, 14 };
const int numRelays = 4;
  
// Relay states
bool relayStates[4] = { false, false, false, false };

// Relay control constants
const int RELAY_ON = LOW;
const int RELAY_OFF = HIGH;

// DFRobot pH sensor calibration
#define PH_OFFSET 0.0        // Offset for calibration
#define PH_SLOPE 1.0         // Slope for calibration
#define PH_SAMPLING_INTERVAL 20
#define PH_PRINT_INTERVAL 800
#define PH_ARRAY_LENGTH 40

// Temperature sensor calibration
#define TEMP_OFFSET 0.0      // Temperature offset
#define TEMP_SLOPE 1.0       // Temperature slope

// Improved pH calibration for better sensitivity
// Adjusted pH calibration based on storage solution reading
#define PH_NEUTRAL_VOLTAGE 2.56   // Voltage at pH 7.0 (based on storage solution)
#define PH_ACID_VOLTAGE 3.8       // Voltage at pH 2.0 (very acidic)
#define PH_NEUTRAL_PH 7.0         // Neutral pH value
#define PH_ACID_PH 2.0            // Acid pH value (more acidic range)
#define PH_VREF 5.0               // Reference voltage for 5V sensors

// Updated pH calibration based on actual voltage reading
#define PH_NEUTRAL_VOLTAGE 1.94   // Voltage at pH 7.0 (based on actual reading)
#define PH_ACID_VOLTAGE 2.5       // Voltage at pH 2.0 (very acidic)
#define PH_NEUTRAL_PH 7.0         // Neutral pH value
#define PH_ACID_PH 2.0            // Acid pH value (more acidic range)
#define PH_VREF 5.0               // Reference voltage for 5V sensors

// DFRobot Turbidity Sensor calibration constants
#define TURBIDITY_VREF 5.0          // Reference voltage (DFRobot sensor uses 5V)
#define TURBIDITY_ADC_RESOLUTION 4095.0  // 12-bit ADC resolution
#define TURBIDITY_CLEAR_VOLTAGE 4.5  // Voltage at 0 NTU (clear water)
#define TURBIDITY_TURBID_VOLTAGE 0.4 // Voltage at 100 NTU (very turbid water)

// Corrected turbidity calibration based on actual readings
#define TURBIDITY_MIN_VOLTAGE 0.8   // Voltage in clear water (0 NTU)
#define TURBIDITY_MAX_VOLTAGE 0.4   // Voltage in turbid water (100 NTU)

// Updated turbidity calibration for high voltage readings
#define TURBIDITY_MIN_VOLTAGE 2.5   // Voltage in clear water (0 NTU)
#define TURBIDITY_MAX_VOLTAGE 1.5   // Voltage in turbid water (100 NTU)

// Turbidity sensor constants
#define TURBIDITY_ARRAY_LENGTH 10  // Number of readings to average
#define TURBIDITY_SAMPLING_INTERVAL 50  // Time between readings (ms)

// TDS sensor constants
#define TDS_ARRAY_LENGTH 40       // Number of readings to average
#define TDS_SAMPLING_INTERVAL 20  // Time between readings (ms)
#define TDS_VOLTAGE_REF 5.0       // Reference voltage for 5V sensors
#define TDS_ADC_RESOLUTION 4095.0 // 12-bit ADC resolution
#define TDS_SPIKE_THRESHOLD 500   // Maximum allowed change between readings
#define TDS_VREF 5.0              // Reference voltage for TDS calculation
#define TDS_KVALUE 1.0            // TDS calibration constant

// Timing constants
const unsigned long SENSOR_READ_INTERVAL = 5000;  // Changed from 2000 to 5000 (5 seconds)
const unsigned long RELAY_CHECK_INTERVAL = 5000;  // Changed from 1000 to 5000 (5 seconds)
unsigned long lastSensorRead = 0;
unsigned long lastRelayCheck = 0;

// pH sensor variables
int phArray[PH_ARRAY_LENGTH];
int phArrayIndex = 0;

// TDS sensor variables
int tdsArray[TDS_ARRAY_LENGTH];
int tdsArrayIndex = 0;
float lastValidTds = 0.0;
bool tdsInitialized = false;

// Turbidity sensor variables
int turbidityArray[TURBIDITY_ARRAY_LENGTH];
int turbidityArrayIndex = 0;

// Function to map float values (like Arduino's map but for floats)
float mapFloat(float x, float in_min, float in_max, float out_min, float out_max) {
  return (x - in_min) * (out_max - out_min) / (in_max - in_min) + out_min;
}

void setup() {
  Serial.begin(115200);
  delay(1000);
  
  Serial.println("🌊 Water Quality Monitoring System Starting...");
  Serial.println("⚠️  WARNING: Sensors use 5V but ESP32 ADC max is 3.3V!");
  Serial.println("   Make sure you have voltage dividers (2:1 ratio) on sensor pins!");
  Serial.println("🔧 REQUIRED: Add 10kΩ + 10kΩ resistors to each sensor pin!");
  Serial.println("   - pH sensor: GPIO35");
  Serial.println("   - Turbidity sensor: GPIO34");
  Serial.println("   - TDS sensor: GPIO32");
  Serial.println();
  
  // Initialize ADC
  analogReadResolution(12);  // Set ADC resolution to 12 bits
  
  // Setup relay pins
  for (int i = 0; i < numRelays; i++) {
    pinMode(relayPins[i], OUTPUT);
    digitalWrite(relayPins[i], RELAY_OFF);
    relayStates[i] = false;
  }
  
  // Initialize TDS array with default values
  for (int i = 0; i < TDS_ARRAY_LENGTH; i++) {
    tdsArray[i] = 0;
  }

  // Initialize turbidity array with default values
  for (int i = 0; i < TURBIDITY_ARRAY_LENGTH; i++) {
    turbidityArray[i] = 0;
  }

  // Connect to Wi-Fi
  WiFi.begin(ssid, password);
  Serial.print("📶 Connecting to WiFi");
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println(" ✅");
    Serial.print("📍 IP: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println(" ❌");
    Serial.println("⚠️  WiFi connection failed!");
  }
}

void loop() {
  unsigned long currentMillis = millis();

  // Read and upload sensor data
  if (currentMillis - lastSensorRead >= SENSOR_READ_INTERVAL) {
    lastSensorRead = currentMillis;

    if (WiFi.status() == WL_CONNECTED) {
      // Read pH sensor with averaging
      static unsigned long samplingTime = millis();
      static unsigned long printTime = millis();
      
      if (millis() - samplingTime > PH_SAMPLING_INTERVAL) {
        phArray[phArrayIndex++] = analogRead(phPin);
        if (phArrayIndex == PH_ARRAY_LENGTH) phArrayIndex = 0;
        samplingTime = millis();
      }
      
      if (millis() - printTime > PH_PRINT_INTERVAL) {
        // Read pH sensor
        int phValue = 0;
        for (int i = 0; i < PH_ARRAY_LENGTH; i++) {
          phArray[i] = analogRead(phPin);
          phValue += phArray[i];
          delay(10);  // Small delay between readings
        }
        phValue = phValue / PH_ARRAY_LENGTH;
        
        // Convert to voltage (ESP32 reference voltage is 3.3V)
        float phVoltage = (phValue * PH_VREF) / 4095.0;
        
        // Calculate pH value using DFRobot calibration formula
        // pH = 7 - ((voltage - neutral_voltage) * (7 - 4) / (acid_voltage - neutral_voltage))
        float voltageDiff = phVoltage - PH_NEUTRAL_VOLTAGE;
        float voltageRange = PH_ACID_VOLTAGE - PH_NEUTRAL_VOLTAGE;
        float phRange = PH_NEUTRAL_PH - PH_ACID_PH;
        
        float ph = PH_NEUTRAL_PH - (voltageDiff * phRange / voltageRange);
        
        // Ensure pH is within reasonable range (0-14)
        ph = constrain(ph, 0.0, 14.0);

        // Invert the pH result
        ph = 14.0 - ph;

        // Debug pH sensor
        Serial.print("pH Raw: ");
        Serial.print(phValue);
        Serial.print(", V: ");
        Serial.print(phVoltage, 3);
        Serial.print("V, pH: ");
        Serial.print(ph, 2);
        Serial.println();
        
        // Check if pH sensor is connected properly
        if (phValue < 10 || phValue > 4085) {
          Serial.println("⚠️  pH sensor may not be connected properly!");
        }
        
        // Check if pH sensor is responding to different solutions
        static float lastPhVoltage = 0;
        float voltageChange = abs(phVoltage - lastPhVoltage);
        if (voltageChange < 0.01 && lastPhVoltage > 0) {
          Serial.println("⚠️  pH sensor not responding to solution changes!");
        }
        lastPhVoltage = phVoltage;
        
        // Check for pH sensor voltage divider issues
        if (phValue > 4085) {
          Serial.println("⚠️  pH: No voltage divider! Sensor outputting 5V to ESP32!");
          Serial.println("   Add 2:1 voltage divider (10kΩ + 10kΩ resistors)");
        }
        
        // Additional pH debugging for calibration
        Serial.print("  pH Cal: V7=");
        Serial.print(PH_NEUTRAL_VOLTAGE, 2);
        Serial.print("V, V4=");
        Serial.print(PH_ACID_VOLTAGE, 2);
        Serial.print("V, Diff=");
        Serial.print(voltageDiff, 3);
        Serial.print("V, Range=");
        Serial.print(voltageRange, 3);
        Serial.println("V");

        // Set temperature for now
        float temperature = 25.0;  // Default temperature

        // Read turbidity sensor with debugging
        float turbidityNTU = readTurbidityValue();

        // Read TDS sensor with averaging and spike filtering
        float tdsValue = readTDSValue();

        // Debug prints
        Serial.println("\n=== SENSOR READINGS ===");
        Serial.print("Turbidity: ");
        Serial.print(turbidityNTU, 1);
        Serial.print(" NTU | TDS: ");
        Serial.print(tdsValue, 1);
        Serial.print(" ppm | pH: ");
        Serial.print(ph, 2);
        Serial.print(" | Temp: ");
        Serial.print(temperature, 1);
        Serial.println("°C");
        
        // Upload data with converted turbidity value
        uploadSensorData(turbidityNTU, tdsValue, ph, temperature);
        printTime = millis();
      }
    } else {
      Serial.println("📶 WiFi disconnected - reconnecting...");
      WiFi.reconnect();
    }
  }

  // Check relay states
  if (currentMillis - lastRelayCheck >= RELAY_CHECK_INTERVAL) {
    lastRelayCheck = currentMillis;
    if (WiFi.status() == WL_CONNECTED) {
      checkRelayStates();
    }
  }

  // Sensor health check (every 10 seconds)
  static unsigned long lastHealthCheck = 0;
  if (currentMillis - lastHealthCheck >= 10000) {
    lastHealthCheck = currentMillis;
    checkSensorHealth();
  }

  // Update relay outputs
  for (int i = 0; i < numRelays; i++) {
    digitalWrite(relayPins[i], relayStates[i] ? RELAY_ON : RELAY_OFF);
  }
}

void uploadSensorData(float turbidity, float tds, float ph, float temperature) {
  HTTPClient http;

  // Begin HTTP request
  http.begin(serverUrl);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");

  // Prepare POST data
  String postData = "turbidity=" + String(turbidity) + "&tds=" + String(tds) + "&ph=" + String(ph) + "&temperature=" + String(temperature);

  // Send POST request
  int httpCode = http.POST(postData);

  if (httpCode > 0) {
    String response = http.getString();
    
    // Check if response contains error message
    if (response.indexOf("ERR_NGROK") != -1) {
      Serial.println("⚠️  Server offline - update ngrok URL");
    } else {
      Serial.println("✅ Data uploaded successfully");
    }
  } else {
    Serial.println("❌ Upload failed - HTTP error: " + String(httpCode));
  }

  http.end();
}

void checkRelayStates() {
  HTTPClient http;

  http.begin(relayControlUrl);
  int httpCode = http.GET();

  if (httpCode > 0) {
    String response = http.getString();

    // Check if response contains error message
    if (response.indexOf("ERR_NGROK") != -1) {
      Serial.println("⚠️  Relay server offline - update ngrok URL");
      return;
    }

    // Parse JSON response
    DynamicJsonDocument doc(1024);
    DeserializationError error = deserializeJson(doc, response);

    if (!error) {
      if (doc.containsKey("states") && doc["states"].is<JsonArray>()) {
        JsonArray states = doc["states"];
        bool relayChanged = false;
        
        for (JsonObject state : states) {
          if (state.containsKey("relay_number") && state.containsKey("state")) {
            int relay = state["relay_number"];
            int value = state["state"];
            if (relay >= 1 && relay <= 4) {
              bool newState = (value == 1);
              if (relayStates[relay - 1] != newState) {
                relayStates[relay - 1] = newState;
                digitalWrite(relayPins[relay - 1], relayStates[relay - 1] ? RELAY_ON : RELAY_OFF);
                Serial.print("🔌 Relay ");
                Serial.print(relay);
                Serial.print(": ");
                Serial.println(newState ? "ON" : "OFF");
                relayChanged = true;
              }
            }
          }
        }
        
        if (!relayChanged) {
          Serial.println("🔌 All relays: OFF");
        }
      } else {
        Serial.println("❌ Invalid relay data format");
      }
    } else {
      Serial.println("❌ Relay data parsing failed");
    }
  } else {
    Serial.println("❌ Relay check failed - HTTP error: " + String(httpCode));
  }

  http.end();
}

void handleRelayCommand(int relay, int state) {
  if (relay >= 1 && relay <= 4) {
    HTTPClient http;
    http.begin(relayControlUrl);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");

    String postData = "relay=" + String(relay) + "&state=" + String(state);
    int httpCode = http.POST(postData);

    if (httpCode > 0) {
      String response = http.getString();
      Serial.println("Relay command response: " + response);

      // Parse response to get updated states
      DynamicJsonDocument doc(1024);
      DeserializationError error = deserializeJson(doc, response);

      if (!error) {
        JsonArray states = doc["states"];
        for (JsonObject state : states) {
          int relayNum = state["relay_number"];
          int value = state["state"];
          if (relayNum >= 1 && relayNum <= 4) {
            relayStates[relayNum - 1] = (value == 1);
            digitalWrite(relayPins[relayNum - 1], relayStates[relayNum - 1] ? RELAY_ON : RELAY_OFF);
          }
        }
      } else {
        Serial.println("JSON parsing failed: " + String(error.c_str()));
      }
    } else {
      Serial.println("Error sending relay command: " + String(httpCode));
    }

    http.end();
  }
}

float readTDSValue() {
  // Read multiple TDS samples with averaging
  int tdsRawSum = 0;
  int validReadings = 0;
  
  // Take multiple readings and average them
  for (int i = 0; i < TDS_ARRAY_LENGTH; i++) {
    int reading = analogRead(tdsPin);
    tdsArray[i] = reading;
    tdsRawSum += reading;
    validReadings++;
    delay(TDS_SAMPLING_INTERVAL);
  }
  
  // Calculate average raw value
  float tdsRawAverage = (float)tdsRawSum / validReadings;
  
  // Convert to voltage
  float tdsVoltage = (tdsRawAverage * TDS_VREF) / TDS_ADC_RESOLUTION;
  
  // Convert voltage to TDS value (ppm)
  // TDS formula: TDS = (133.42 * voltage^3 - 255.86 * voltage^2 + 857.39 * voltage) * KVALUE
  // Simplified linear approximation for more stable readings
  float tdsValue = (tdsVoltage * 1000.0) * TDS_KVALUE;  // Basic conversion
  
  // Apply spike filtering
  if (tdsInitialized) {
    float difference = abs(tdsValue - lastValidTds);
    if (difference > TDS_SPIKE_THRESHOLD) {
      // If change is too large, use weighted average with previous value
      tdsValue = (lastValidTds * 0.8) + (tdsValue * 0.2);
      Serial.print("TDS spike detected, filtered value: ");
      Serial.println(tdsValue);
    }
  }
  
  // Update last valid reading
  lastValidTds = tdsValue;
  tdsInitialized = true;
  
  // Debug output
  Serial.print("TDS: ");
  Serial.print(tdsValue, 1);
  Serial.println(" ppm");
  
  return tdsValue;
}

float readTurbidityValue() {
  int rawValue = 0;

  // Take 10 readings for smoothing
  for (int i = 0; i < 10; i++) {
    rawValue += analogRead(turbidityPin);
    delay(10);
  }
  rawValue /= 10;

  // Convert Raw ADC (0–4095) to 1–1000 NTU based on your observed range
  // Example: 1700 ~ cloudy, 2100 ~ clear
  float ntu = mapFloat(rawValue, 1700, 2100, 1000, 1);

  // Clamp NTU to stay in range
  if (ntu < 1) ntu = 1;
  if (ntu > 1000) ntu = 1000;

  // Print results
  Serial.print("Turbidity Raw: ");
  Serial.print(rawValue);
  Serial.print(" | NTU: ");
  Serial.println(ntu, 2);

  return ntu;
}

void checkSensorHealth() {
  Serial.println("\n🔍 SENSOR HEALTH CHECK:");
  
  // Check pH sensor
  int phRaw = analogRead(phPin);
  float phVoltage = (phRaw * 5.0) / 4095.0;
  Serial.print("pH: Raw=");
  Serial.print(phRaw);
  Serial.print(", V=");
  Serial.print(phVoltage, 3);
  Serial.print("V");
  if (phRaw > 4085) {
    Serial.println(" ❌ NO VOLTAGE DIVIDER!");
  } else if (phRaw < 10) {
    Serial.println(" ❌ NOT CONNECTED!");
  } else {
    Serial.println(" ✅ OK");
  }
  
  // Check turbidity sensor
  int turbidityRaw = analogRead(turbidityPin);
  float turbidityVoltage = (turbidityRaw * 5.0) / 4095.0;
  Serial.print("Turbidity: Raw=");
  Serial.print(turbidityRaw);
  Serial.print(", V=");
  Serial.print(turbidityVoltage, 3);
  Serial.print("V");
  if (turbidityRaw > 4085) {
    Serial.println(" ❌ NO VOLTAGE DIVIDER!");
  } else if (turbidityRaw < 10) {
    Serial.println(" ❌ NOT CONNECTED!");
  } else {
    Serial.println(" ✅ OK");
  }
  
  // Check TDS sensor
  int tdsRaw = analogRead(tdsPin);
  float tdsVoltage = (tdsRaw * 5.0) / 4095.0;
  Serial.print("TDS: Raw=");
  Serial.print(tdsRaw);
  Serial.print(", V=");
  Serial.print(tdsVoltage, 3);
  Serial.print("V");
  if (tdsRaw > 4085) {
    Serial.println(" ❌ NO VOLTAGE DIVIDER!");
  } else if (tdsRaw < 10) {
    Serial.println(" ❌ NOT CONNECTED!");
  } else {
    Serial.println(" ✅ OK");
  }
  
  Serial.println();
  
  // Additional troubleshooting for sensors reading 0
  if (turbidityRaw == 0) {
    Serial.println("🔧 TURBIDITY TROUBLESHOOTING:");
    Serial.println("   - Check if sensor is powered (red wire to 5V)");
    Serial.println("   - Check if GND is connected (black wire)");
    Serial.println("   - Check voltage divider wiring (2 resistors)");
    Serial.println("   - Try disconnecting and reconnecting sensor");
    
    // Test voltage divider
    Serial.println("🔧 TESTING TURBIDITY VOLTAGE DIVIDER:");
    Serial.println("   - Disconnect sensor signal wire from voltage divider");
    Serial.println("   - Connect 5V directly to voltage divider input");
    Serial.println("   - Should read ~2048 (2.5V) if voltage divider is working");
    Serial.println("   - If still 0, voltage divider wiring is wrong");
  }
  
  if (tdsRaw == 0) {
    Serial.println("🔧 TDS TROUBLESHOOTING:");
    Serial.println("   - Check if sensor is powered (red wire to 5V)");
    Serial.println("   - Check if GND is connected (black wire)");
    Serial.println("   - Check voltage divider wiring (2 resistors)");
    Serial.println("   - Try disconnecting and reconnecting sensor");
    
    // Test voltage divider
    Serial.println("🔧 TESTING TDS VOLTAGE DIVIDER:");
    Serial.println("   - Disconnect sensor signal wire from voltage divider");
    Serial.println("   - Connect 5V directly to voltage divider input");
    Serial.println("   - Should read ~2048 (2.5V) if voltage divider is working");
    Serial.println("   - If still 0, voltage divider wiring is wrong");
  }
}