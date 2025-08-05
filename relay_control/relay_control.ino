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
const int turbidityPin = 35;  // Turbidity sensor on GPIO33
const int phPin = 34;         // DFRobot pH sensor on GPIO34
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

// Calibration values (adjust these after calibration)
#define PH_NEUTRAL_VOLTAGE 1.5    // Voltage at pH 7.0
#define PH_ACID_VOLTAGE 2.0       // Voltage at pH 4.0
#define PH_NEUTRAL_PH 7.0         // Neutral pH value
#define PH_ACID_PH 4.0            // Acid pH value

// Add these constants after the other calibration constants
#define TURBIDITY_VOLTAGE_OFFSET 0.0
#define TURBIDITY_VOLTAGE_SLOPE 1.0
#define TURBIDITY_NTU_OFFSET 0.0
#define TURBIDITY_NTU_SLOPE 1.0

// Timing constants
const unsigned long SENSOR_READ_INTERVAL = 2000;
const unsigned long RELAY_CHECK_INTERVAL = 1000;
unsigned long lastSensorRead = 0;
unsigned long lastRelayCheck = 0;

// pH sensor variables
int phArray[PH_ARRAY_LENGTH];
int phArrayIndex = 0;

void setup() {
  Serial.begin(115200);
  delay(1000);
  
  // Initialize ADC
  analogReadResolution(12);  // Set ADC resolution to 12 bits
  
  // Setup relay pins
  for (int i = 0; i < numRelays; i++) {
    pinMode(relayPins[i], OUTPUT);
    digitalWrite(relayPins[i], RELAY_OFF);
    relayStates[i] = false;
  }

  // Connect to Wi-Fi
  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi");
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWiFi Connected!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\nWiFi Connection Failed!");
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
        float phVoltage = (phValue * 3.3) / 4095.0;
        
        // Calculate pH value using calibration
        float ph = ((phVoltage - PH_NEUTRAL_VOLTAGE) * (PH_ACID_PH - PH_NEUTRAL_PH) / 
                   (PH_ACID_VOLTAGE - PH_NEUTRAL_VOLTAGE)) + PH_NEUTRAL_PH;

        // Set temperature for now
        float temperature = 25.0;  // Default temperature

        // Read turbidity sensor with debugging
        int turbidityRaw = analogRead(turbidityPin);
        float turbidityVoltage = (turbidityRaw * 3.3) / 4095.0;
        
        // Convert voltage to NTU
        // For this sensor, higher voltage means clearer water (lower turbidity)
        // Using an inverse linear conversion: NTU = 100 - ((voltage - min_voltage) * scale_factor)
        float minVoltage = 0.0;  // Minimum voltage at 100 NTU
        float maxVoltage = 3.3;  // Maximum voltage at 0 NTU
        float scaleFactor = 100.0 / (maxVoltage - minVoltage);
        float turbidityNTU = 100.0 - ((turbidityVoltage - minVoltage) * scaleFactor);
        
        // Ensure turbidity is within reasonable range (0-100 NTU)
        turbidityNTU = constrain(turbidityNTU, 0.0, 100.0);

        // Read TDS sensor
        int tdsValue = analogRead(tdsPin);

        // Debug prints
        Serial.println("\n--- Sensor Readings ---");
        Serial.print("Turbidity Raw: ");
        Serial.print(turbidityRaw);
        Serial.print(", Voltage: ");
        Serial.print(turbidityVoltage, 3);
        Serial.print("V, NTU: ");
        Serial.print(turbidityNTU, 1);
        Serial.println(" NTU");
        
        Serial.print("pH Raw: ");
        Serial.print(phValue);
        Serial.print(", Voltage: ");
        Serial.print(phVoltage, 3);
        Serial.print("V, pH: ");
        Serial.print(ph, 2);
        Serial.print(", Temperature: ");
        Serial.print(temperature, 1);
        Serial.println("°C");
        
        // Upload data with converted turbidity value
        uploadSensorData(turbidityNTU, tdsValue, ph, temperature);
        printTime = millis();
      }
    } else {
      Serial.println("WiFi disconnected. Reconnecting...");
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
    Serial.println("Server response: " + response);
    
    // Check if response contains error message
    if (response.indexOf("ERR_NGROK") != -1) {
      Serial.println("Warning: Server endpoint is offline. Please update the ngrok URL.");
    }
  } else {
    Serial.println("Error on HTTP request: " + String(httpCode));
  }

  http.end();
}

void checkRelayStates() {
  HTTPClient http;

  http.begin(relayControlUrl);
  int httpCode = http.GET();

  if (httpCode > 0) {
    String response = http.getString();
    Serial.println("Relay states: " + response);

    // Check if response contains error message
    if (response.indexOf("ERR_NGROK") != -1) {
      Serial.println("Warning: Server endpoint is offline. Please update the ngrok URL.");
      return;
    }

    // Parse JSON response
    DynamicJsonDocument doc(1024);
    DeserializationError error = deserializeJson(doc, response);

    if (!error) {
      if (doc.containsKey("states") && doc["states"].is<JsonArray>()) {
        JsonArray states = doc["states"];
        for (JsonObject state : states) {
          if (state.containsKey("relay_number") && state.containsKey("state")) {
            int relay = state["relay_number"];
            int value = state["state"];
            if (relay >= 1 && relay <= 4) {
              bool newState = (value == 1);
              if (relayStates[relay - 1] != newState) {
                relayStates[relay - 1] = newState;
                digitalWrite(relayPins[relay - 1], relayStates[relay - 1] ? RELAY_ON : RELAY_OFF);
                Serial.print("Relay ");
                Serial.print(relay);
                Serial.print(" set to ");
                Serial.println(newState ? "ON" : "OFF");
              }
            }
          }
        }
      } else {
        Serial.println("Invalid JSON format: missing 'states' array");
      }
    } else {
      Serial.println("JSON parsing failed: " + String(error.c_str()));
    }
  } else {
    Serial.println("Error checking relay states: " + String(httpCode));
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