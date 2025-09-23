#include <WiFiS3.h>
#include <ArduinoJson.h>
#include <OneWire.h>
#include <DallasTemperature.h>
 
// WiFi credentials
const char* ssid = "Sean";
const char* password = "mine3you";

// Server details (HTTP - Arduino R4 WiFi doesn't support HTTPS natively)
const char* serverUrl = "http://waterquality.triple7autosupply.com/api/http_proxy.php?endpoint=upload";
const char* relayControlUrl = "http://waterquality.triple7autosupply.com/api/http_proxy.php?endpoint=relay_control";
const char* relayControlUrlBackup = "http://waterquality.triple7autosupply.com/api/relay_control.php"; // Fallback
const char* serverHost = "waterquality.triple7autosupply.com";
const int serverPort = 80;

// Pin definitions for Arduino R4 WiFi
const int tdsPin = A0;        // Analog pin A0
const int turbidityPin = A1;  // Analog pin A1
const int pH_Pin = A2;        // Analog pin A2
const int relayPins[] = { 3, 4, 5, 6 };  // Digital pins 3-6 a(moved to avoid temp sensor)
const int numRelays = 4;

// Temperature sensor setup
#define ONE_WIRE_BUS 2        // Digital pin 2 for DS18B20
OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature tempSensors(&oneWire);
DeviceAddress tempSensorAddress;
bool tempSensorFound = false;

// Relay states and constants
bool relayStates[4] = { false, false, false, false };
const int RELAY_ON = LOW;
const int RELAY_OFF = HIGH;
bool relayControlDisabled = false; // Will be set to true if server requires HTTPS

// pH sensor calibration (optimized)
const int NUM_SAMPLES = 7;
const int AVG_DELAY_MS = 5;
const float SLOPE = 14.38005034237246;
const float INTERCEPT = -15.497796174728297;
const float ADC_MAX = 1023.0;  // 10-bit ADC for Arduino R4
const float VREF = 5.0;        // 5V reference for Arduino R4
// Apply simple runtime calibration without reflashing full curve
const float PH_CAL_SLOPE = 0.951f;   // two-point fit from buffer data
const float PH_CAL_OFFSET = 0.564f;  // two-point fit offset

// Sensor constants (consolidated)
#define TURBIDITY_ARRAY_LENGTH 5
#define TDS_ARRAY_LENGTH 20
#define TDS_SAMPLING_INTERVAL 10
#define TURBIDITY_SAMPLING_INTERVAL 5
#define TDS_VREF 5.0
#define TDS_KVALUE 1.0
#define TDS_SPIKE_THRESHOLD 500
// If your TDS board outputs higher voltage in cleaner water, invert the reading
#define TDS_INVERT 0
// Calibration for TDS conversion
#define TDS_FACTOR 0.5f        // 0.5 (DFRobot default). Adjust 0.45–0.7 to match your solution
#define TDS_CAL_SLOPE 1.00f    // Post-polynomial scale
#define TDS_OFFSET 0.0f        // Post-polynomial offset (ppm)

// Turbidity calibration endpoints (ADC raw). Adjust these to your sensor.
// Clean water typically outputs higher voltage (higher ADC) than dirty for common sensors.
#define TURBIDITY_CLEAR_RAW 800   // Adjusted for 10-bit ADC
#define TURBIDITY_DIRTY_RAW 300   // Adjusted for 10-bit ADC
// Some turbidity sensors may be wired such that voltage decreases with clarity
#define TURBIDITY_INVERT 0     // Set to 1 if your readings act inverted

// Timing constants
const unsigned long SENSOR_READ_INTERVAL = 1000;
const unsigned long RELAY_CHECK_INTERVAL = 1000;
unsigned long lastSensorRead = 0;
unsigned long lastRelayCheck = 0;

// Sensor arrays
int tdsArray[TDS_ARRAY_LENGTH];
int turbidityArray[TURBIDITY_ARRAY_LENGTH];
int tdsArrayIndex = 0;
float lastValidTds = 0.0;
bool tdsInitialized = false;

// Debug/telemetry holders for raw/voltage values
float lastPhVoltage = 0.0f;
int lastPhRaw = 0;
int lastTurbidityRawAvg = 0;
float lastTurbidityVoltage = 0.0f;
float lastTdsRawAvg = 0.0f;
float lastTdsVoltage = 0.0f;
float lastTemperature = 25.0f;  // Default temperature if sensor fails

// Function declarations
void reconnectWiFi();
void uploadSensorData(float turbidity, float tds, float ph, float temperature);
void checkRelayStates();
void handleRelayCommand(int relay, int state);
float readTDSValue(float temperatureC);
float readTurbidityValue();
float readTemperatureValue();
void checkWiFiHealth();
void checkSensorHealth();
String cleanJsonResponse(String response);
bool detectTemperatureSensor();

// Function to map float values (like Arduino's map but for floats)
float mapFloat(float x, float in_min, float in_max, float out_min, float out_max) {
  return (x - in_min) * (out_max - out_min) / (in_max - in_min) + out_min;
}

// Median filter function for pH sensor
float medianFilter(int arr[], int n) {
  // simple insertion-sort-based median (n is small)
  for (int i = 1; i < n; i++) {
    int key = arr[i];
    int j = i - 1;
    while (j >= 0 && arr[j] > key) {
      arr[j + 1] = arr[j];
      j--;
    }
    arr[j + 1] = key;
  }
  return arr[n/2];
}

void setup() {
  Serial.begin(115200);
  delay(1000);
  
  // Initialize analog reference (Arduino R4 uses 5V by default)
  // analogReference is not needed for Arduino R4 WiFi as it uses 5V by default
  
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

  // Initialize temperature sensor
  tempSensors.begin();
  Serial.println("DS18B20 Temperature Sensor initialized");
  tempSensorFound = detectTemperatureSensor();
  if (!tempSensorFound) {
    Serial.println("No DS18B20 address detected. Will try index-based reads.");
  }

  // Connect to Wi-Fi
  Serial.println("Connecting to WiFi...");
  WiFi.begin(ssid, password);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println();
    Serial.println("WiFi connected successfully!");
    Serial.print("IP address: ");
    Serial.println(WiFi.localIP());
    Serial.print("Signal strength (RSSI): ");
    Serial.print(WiFi.RSSI());
    Serial.println(" dBm");
    
    // Test server connection (HTTP)
    Serial.println("Testing server connection (HTTP)...");
    WiFiClient testClient;
    if (testClient.connect(serverHost, serverPort)) {
      Serial.println("Server connection test successful!");
      testClient.stop();
    } else {
      Serial.println("Server connection test failed!");
    }
    // Fetch initial relay states immediately after WiFi connects
    Serial.println("Checking initial relay states...");
    checkRelayStates();
  } else {
    Serial.println();
    Serial.println("WiFi connection failed! Check credentials and try again.");
    Serial.println("The device will continue to attempt reconnection...");
  }
}

void loop() {
  unsigned long currentMillis = millis();

  // Read and upload sensor data
  if (currentMillis - lastSensorRead >= SENSOR_READ_INTERVAL) {
    lastSensorRead = currentMillis;

    if (WiFi.status() == WL_CONNECTED) {
      // Read pH sensor with improved calibration method
      int phSamples[NUM_SAMPLES];
      long phSum = 0;
      
      // Take multiple samples for pH reading
      for (int i = 0; i < NUM_SAMPLES; i++) {
        int reading = analogRead(pH_Pin);
        phSamples[i] = reading;
        phSum += reading;
        delay(AVG_DELAY_MS);
      }
      
      // Compute median to reject spike outliers
      float phMedianVal = medianFilter(phSamples, NUM_SAMPLES);
      // Compute average
      float phAvg = (float)phSum / (float)NUM_SAMPLES;
      
      // Use the median when spikes present, otherwise average — blend approach
      float phRawValue = (phAvg * 0.6f) + (phMedianVal * 0.4f);
      
      // Bad/disconnected sensor detection
      if ((int)phRawValue == 0) {
        delay(1000);
        return;
      }
      
      // Convert ADC -> Voltage (V)
      float phVoltage = (phRawValue / ADC_MAX) * VREF;
      lastPhRaw = (int)phRawValue;
      lastPhVoltage = phVoltage;
      
      // Convert voltage -> pH using calibration fit
      float ph = SLOPE * phVoltage + INTERCEPT;
      // Apply runtime calibration (adjust offset/slope to match buffer solutions)
      float phCalibrated = (ph * PH_CAL_SLOPE) + PH_CAL_OFFSET;
      
      // Limit pH to physically sensible range
      if (phCalibrated < 0.0) phCalibrated = 0.0;
      if (phCalibrated > 14.0) phCalibrated = 14.0;

      // Read temperature sensor
      float temperature = readTemperatureValue();

      // Read turbidity sensor with debugging
      float turbidityNTU = readTurbidityValue();

      // Read TDS sensor using provided sampling and conversion
      float tdsValue = readTDSValue(temperature);
      
      // Debug: print turbidity averaged ADC and voltage
      Serial.print("Turbidity ADC: ");
      Serial.print(lastTurbidityRawAvg);
      Serial.print(", V: ");
      Serial.println(lastTurbidityVoltage, 3);
      
      // Single-line labeled data output
      Serial.print("pH: ");
      Serial.print(phCalibrated, 2);
      Serial.print(", Turbidity: ");
      Serial.print(turbidityNTU, 1);
      Serial.print(" NTU, TDS: ");
      Serial.print(tdsValue, 1);
      Serial.print(" ppm, Temp: ");
      Serial.print(temperature, 1);
      Serial.print(" C | Relays: ");
      for (int i = 0; i < 4; i++) {
        Serial.print("R");
        Serial.print(i + 1);
        Serial.print(":");
        Serial.print(relayStates[i] ? "ON" : "OFF");
        if (i < 3) Serial.print(" ");
      }
      if (relayControlDisabled) {
        Serial.print(" [RELAY CONTROL DISABLED - HTTPS REQUIRED]");
      }
      Serial.println();

      // Upload data with converted turbidity value
      uploadSensorData(turbidityNTU, tdsValue, phCalibrated, temperature);
    } else {
      Serial.println("WiFi disconnected, attempting to reconnect...");
      reconnectWiFi();
    }
  }

  // Check relay states (only if not disabled due to HTTPS requirement)
  if (currentMillis - lastRelayCheck >= RELAY_CHECK_INTERVAL) {
    lastRelayCheck = currentMillis;
    if (WiFi.status() == WL_CONNECTED && !relayControlDisabled) {
      checkRelayStates();
    }
  }

  // Sensor health check (every 10 seconds)
  static unsigned long lastHealthCheck = 0;
  if (currentMillis - lastHealthCheck >= 10000) {
    lastHealthCheck = currentMillis;
    checkSensorHealth();
  }

  // WiFi health check (every 30 seconds)
  static unsigned long lastWiFiCheck = 0;
  if (currentMillis - lastWiFiCheck >= 30000) {
    lastWiFiCheck = currentMillis;
    checkWiFiHealth();
  }

  // Update relay outputs
  for (int i = 0; i < numRelays; i++) {
    digitalWrite(relayPins[i], relayStates[i] ? RELAY_ON : RELAY_OFF);
  }
}

void uploadSensorData(float turbidity, float tds, float ph, float temperature) {
  WiFiClient client;
  client.setTimeout(5000); // 5 second timeout
  
  if (client.connect(serverHost, serverPort)) {
    // Prepare POST data
    String postData = "turbidity=" + String(turbidity) + "&tds=" + String(tds) + "&ph=" + String(ph) + "&temperature=" + String(temperature);
    
    // Send HTTP POST request
    client.println("POST /api/http_proxy.php?endpoint=upload HTTP/1.1");
    client.println("Host: " + String(serverHost));
    client.println("Content-Type: application/x-www-form-urlencoded");
    client.println("User-Agent: Arduino-R4-WaterQuality");
    client.println("Connection: close");
    client.print("Content-Length: ");
    client.println(postData.length());
    client.println();
    client.print(postData);
    
    // Read response
    while (client.connected()) {
      String line = client.readStringUntil('\n');
      if (line == "\r") {
        break;
      }
    }
    
    String response = client.readString();
    
    // Check if response contains error message
    if (response.indexOf("ERR_NGROK") != -1) {
      Serial.println("Server error: NGROK tunnel issue");
    } else {
      Serial.println("Data uploaded successfully");
    }
  } else {
    Serial.println("Failed to connect to server");
  }
  
  client.stop();
}

void checkRelayStates() {
  WiFiClient client;
  client.setTimeout(5000); // 5 second timeout
  
  if (client.connect(serverHost, serverPort)) {
    // Send HTTP GET request
    client.println("GET /api/http_proxy.php?endpoint=relay_control HTTP/1.1");
    client.println("Host: " + String(serverHost));
    client.println("User-Agent: Arduino-R4-WaterQuality");
    client.println("Connection: close");
    client.println();
    
    // Read response headers and check for redirects
    String statusLine = "";
    bool isRedirect = false;
    
    while (client.connected()) {
      String line = client.readStringUntil('\n');
      line.trim();
      
      if (line.startsWith("HTTP/")) {
        statusLine = line;
        if (line.indexOf("301") != -1 || line.indexOf("302") != -1) {
          isRedirect = true;
        }
      }
      
      if (line == "\r" || line.length() == 0) {
        break;
      }
    }
    
    // Read JSON response
    String response = client.readString();
    
    // Handle redirect - Arduino R4 WiFi cannot follow HTTPS redirects
    if (isRedirect) {
      if (!relayControlDisabled) {
        Serial.println("Server redirects to HTTPS - Arduino R4 WiFi cannot handle SSL");
        Serial.println("Relay control disabled due to HTTPS requirement");
        Serial.println("Status: " + statusLine);
        Serial.println("Consider using ESP32 for HTTPS support or configure server for HTTP API access");
        relayControlDisabled = true;
      }
      client.stop();
      return;
    }
    
    // Debug: Print the raw response for troubleshooting
    Serial.println("Raw server response: " + response);
    
    // Check if response contains HTML (error page)
    if (response.indexOf("<!DOCTYPE html>") != -1 || response.indexOf("<html") != -1) {
      Serial.println("Server returned HTML instead of JSON - possible redirect or error page");
      Serial.println("Status: " + statusLine);
      client.stop();
      return;
    }
    
    // Check if response contains error message
    if (response.indexOf("ERR_NGROK") != -1) {
      Serial.println("Server error: NGROK tunnel issue");
      client.stop();
      return;
    }

    // Check if response is empty or too short
    if (response.length() < 10) {
      Serial.println("Server response too short: " + response);
      client.stop();
      return;
    }

    // Clean the JSON response
    String cleanResponse = cleanJsonResponse(response);
    if (cleanResponse.length() == 0) {
      Serial.println("No valid JSON found in response");
      client.stop();
      return;
    }
    
    Serial.println("Cleaned JSON: " + cleanResponse);

    // Parse JSON response
    DynamicJsonDocument doc(1024);
    DeserializationError error = deserializeJson(doc, cleanResponse);

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
                Serial.print("Relay ");
                Serial.print(relay);
                Serial.print(" changed to: ");
                Serial.println(newState ? "ON" : "OFF");
                relayChanged = true;
              }
            }
          }
        }
        
        // Display current relay status
        Serial.print("Relay Status: ");
        for (int i = 0; i < 4; i++) {
          Serial.print("R");
          Serial.print(i + 1);
          Serial.print(":");
          Serial.print(relayStates[i] ? "ON" : "OFF");
          if (i < 3) Serial.print(" ");
        }
        Serial.println();
      }
    } else {
      Serial.println("Failed to parse JSON response");
      Serial.println("Error: " + String(error.c_str()));
      Serial.println("Response length: " + String(response.length()));
      Serial.println("First 100 chars: " + response.substring(0, 100));
    }
  } else {
    Serial.println("Failed to connect to server for relay check");
  }
  
  client.stop();
}

void handleRelayCommand(int relay, int state) {
  if (relay >= 1 && relay <= 4) {
    WiFiClient client;
    client.setTimeout(5000); // 5 second timeout
    
    if (client.connect(serverHost, serverPort)) {
      // Prepare POST data
      String postData = "relay=" + String(relay) + "&state=" + String(state);
      
      // Send HTTP POST request
      client.println("POST /api/http_proxy.php?endpoint=relay_control HTTP/1.1");
      client.println("Host: " + String(serverHost));
      client.println("Content-Type: application/x-www-form-urlencoded");
      client.println("User-Agent: Arduino-R4-WaterQuality");
      client.println("Connection: close");
      client.print("Content-Length: ");
      client.println(postData.length());
      client.println();
      client.print(postData);
      
      // Read response headers
      while (client.connected()) {
        String line = client.readStringUntil('\n');
        if (line == "\r") {
          break;
        }
      }
      
      // Read JSON response
      String response = client.readString();
      
      // Debug: Print the raw response for troubleshooting
      Serial.println("Relay command response: " + response);

      // Clean the JSON response
      String cleanResponse = cleanJsonResponse(response);
      if (cleanResponse.length() == 0) {
        Serial.println("No valid JSON found in relay command response");
        client.stop();
        return;
      }
      
      Serial.println("Cleaned relay JSON: " + cleanResponse);

      // Parse response to get updated states
      DynamicJsonDocument doc(1024);
      DeserializationError error = deserializeJson(doc, cleanResponse);

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
        Serial.println("Failed to parse relay command JSON response");
        Serial.println("Error: " + String(error.c_str()));
        Serial.println("Response: " + response);
      }
    } else {
      Serial.println("Failed to connect to server for relay command");
    }
    
    client.stop();
  }
}

float readTDSValue(float temperatureC) {
  int adcValue = 0;
  long sum = 0;

  // Take 30 samples and average
  for (int i = 0; i < 30; i++) {
    adcValue = analogRead(tdsPin);
    sum += adcValue;
    delay(40);
  }
  adcValue = sum / 30;

  // Save debug values
  lastTdsRawAvg = adcValue;

  // Convert to voltage
  float voltage = (adcValue * TDS_VREF) / ADC_MAX;
  lastTdsVoltage = voltage;

  // Temperature compensation (simple linear formula)
  float compensationCoefficient = 1.0 + 0.02f * (temperatureC - 25.0f);
  float compensationVoltage = voltage / compensationCoefficient;

  // Convert voltage to TDS (ppm) using DFRobot polynomial with adjustable factor
  float tdsValue = (133.42f * compensationVoltage * compensationVoltage * compensationVoltage
                   - 255.86f * compensationVoltage * compensationVoltage
                   + 857.39f * compensationVoltage) * TDS_FACTOR;

  // Optional post-calibration (two-point field calibration)
  tdsValue = (tdsValue * TDS_CAL_SLOPE) + TDS_OFFSET;

  if (tdsValue < 0.0f) tdsValue = 0.0f;

  // Update last valid reading flags
  lastValidTds = tdsValue;
  tdsInitialized = true;

  return tdsValue;
}

float readTurbidityValue() {
  int rawValue = 0;

  // Take multiple readings for smoothing
  for (int i = 0; i < TURBIDITY_ARRAY_LENGTH; i++) {
    rawValue += analogRead(turbidityPin);
    delay(TURBIDITY_SAMPLING_INTERVAL);
  }
  rawValue /= TURBIDITY_ARRAY_LENGTH;
  lastTurbidityRawAvg = rawValue;
  lastTurbidityVoltage = (rawValue / ADC_MAX) * VREF;
  
  // Convert voltage to NTU using provided linear model
  float voltage = lastTurbidityVoltage;
  float ntu = -309.0f * (voltage - 1.964f) + 1.0f; // clean ~1 NTU, dirtier -> higher
  if (ntu < 0.0f) ntu = 0.0f;
  
  return ntu;
}

void checkWiFiHealth() {
  if (WiFi.status() == WL_CONNECTED) {
    long rssi = WiFi.RSSI();
    if (rssi < -80) {
      Serial.println("WiFi signal weak (RSSI: " + String(rssi) + ")");
    } else if (rssi < -70) {
      Serial.println("WiFi signal moderate (RSSI: " + String(rssi) + ")");
    }
  } else {
    Serial.println("WiFi disconnected, attempting to reconnect...");
    reconnectWiFi();
  }
}

void reconnectWiFi() {
  static unsigned long lastReconnectAttempt = 0;
  static int reconnectAttempts = 0;
  
  unsigned long currentTime = millis();
  
  // Only attempt reconnection every 10 seconds
  if (currentTime - lastReconnectAttempt >= 10000) {
    lastReconnectAttempt = currentTime;
    reconnectAttempts++;
    
    Serial.println("Reconnection attempt #" + String(reconnectAttempts));
    
    WiFi.disconnect();
    delay(1000);
    WiFi.begin(ssid, password);
    
    // Wait up to 10 seconds for connection
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
      delay(500);
      attempts++;
    }
    
    if (WiFi.status() == WL_CONNECTED) {
      Serial.println("WiFi reconnected successfully!");
      Serial.print("IP address: ");
      Serial.println(WiFi.localIP());
      reconnectAttempts = 0; // Reset counter on successful connection
    } else {
      Serial.println("Reconnection failed. Will try again in 10 seconds...");
    }
  }
}

float readTemperatureValue() {
  // Ensure sensor presence is tracked
  if (!tempSensorFound) {
    tempSensorFound = detectTemperatureSensor();
  }

  // Request temperature conversion
  tempSensors.requestTemperatures();

  // Prefer address-based read when available
  float temperatureC = tempSensorFound
    ? tempSensors.getTempC(tempSensorAddress)
    : tempSensors.getTempCByIndex(0);

  // Retry a few times if disconnected/invalid
  int retries = 0;
  while ((temperatureC == DEVICE_DISCONNECTED_C || isnan(temperatureC)) && retries < 3) {
    delay(200);
    tempSensors.requestTemperatures();
    temperatureC = tempSensorFound
      ? tempSensors.getTempC(tempSensorAddress)
      : tempSensors.getTempCByIndex(0);
    retries++;
  }

  if (temperatureC == DEVICE_DISCONNECTED_C || isnan(temperatureC)) {
    Serial.println("Temperature read failed - using last valid value");
    return lastTemperature;
  }

  lastTemperature = temperatureC;
  return temperatureC;
}

String cleanJsonResponse(String response) {
  // Remove any leading/trailing whitespace
  response.trim();
  
  // Find the start of JSON (look for '{' or '[')
  int jsonStart = -1;
  for (int i = 0; i < response.length(); i++) {
    if (response.charAt(i) == '{' || response.charAt(i) == '[') {
      jsonStart = i;
      break;
    }
  }
  
  if (jsonStart == -1) {
    Serial.println("No JSON found in response");
    return "";
  }
  
  // Find the end of JSON (look for matching '}' or ']')
  int braceCount = 0;
  int bracketCount = 0;
  int jsonEnd = -1;
  
  for (int i = jsonStart; i < response.length(); i++) {
    char c = response.charAt(i);
    if (c == '{') braceCount++;
    else if (c == '}') braceCount--;
    else if (c == '[') bracketCount++;
    else if (c == ']') bracketCount--;
    
    if (braceCount == 0 && bracketCount == 0) {
      jsonEnd = i;
      break;
    }
  }
  
  if (jsonEnd == -1) {
    Serial.println("Incomplete JSON in response");
    return response.substring(jsonStart); // Return what we have
  }
  
  return response.substring(jsonStart, jsonEnd + 1);
}

void checkSensorHealth() {
  // Check pH sensor
  int phRaw = analogRead(pH_Pin);
  float phVoltage = (phRaw * VREF) / ADC_MAX;
  if (phRaw > 1015) {  // Adjusted for 10-bit ADC
    Serial.println("pH sensor reading too high - check connection");
  } else if (phRaw < 10) {
    Serial.println("pH sensor reading too low - check connection");
  }
  
  // Check turbidity sensor
  int turbidityRaw = analogRead(turbidityPin);
  if (turbidityRaw > 1015) {  // Adjusted for 10-bit ADC
    Serial.println("Turbidity sensor reading too high - check connection");
  } else if (turbidityRaw < 10) {
    Serial.println("Turbidity sensor reading too low - check connection");
  }
  
  // Check TDS sensor
  int tdsRaw = analogRead(tdsPin);
  if (tdsRaw > 1015) {  // Adjusted for 10-bit ADC
    Serial.println("TDS sensor reading too high - check connection");
  } else if (tdsRaw < 10) {
    Serial.println("TDS sensor reading too low - check connection");
  }
  
  // Check temperature sensor
  tempSensors.requestTemperatures();
  float tempC = tempSensors.getTempCByIndex(0);
  if (tempC == DEVICE_DISCONNECTED_C) {
    Serial.println("Temperature sensor disconnected - check connection");
  } else if (tempC < -40.0 || tempC > 85.0) {
    Serial.println("Temperature reading out of range - check sensor");
  }
}

bool detectTemperatureSensor() {
  // Attempt to obtain the first sensor's address
  if (tempSensors.getAddress(tempSensorAddress, 0)) {
    Serial.print("DS18B20 address: ");
    for (uint8_t i = 0; i < 8; i++) {
      if (tempSensorAddress[i] < 16) Serial.print("0");
      Serial.print(tempSensorAddress[i], HEX);
    }
    Serial.println();
    tempSensors.setResolution(tempSensorAddress, 12);
    return true;
  }
  return false;
}

bool tryHttpsConnection() {
  // Note: Arduino R4 WiFi doesn't have built-in SSL/TLS support like ESP32
  // This is a placeholder function that would need WiFiSSLClient or similar
  // For now, we'll just return false and suggest manual URL verification
  Serial.println("HTTPS not supported on Arduino R4 WiFi without additional libraries");
  Serial.println("Please verify the correct URL in your web browser:");
  Serial.println("- Try: https://waterquality.triple7autosupply.com/api/relay_control.php");
  Serial.println("- Or: http://waterquality.triple7autosupply.com/api/relay_control.php");
  Serial.println("If HTTPS is required, consider using ESP32 or add SSL support library");
  return false;
}
