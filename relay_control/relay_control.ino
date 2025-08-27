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
const int tdsPin = 32;
const int turbidityPin = 33;
const int pH_Pin = 35;
const int relayPins[] = { 25, 26, 27, 14 };
const int numRelays = 4;

// Relay states and constants
bool relayStates[4] = { false, false, false, false };
const int RELAY_ON = LOW;
const int RELAY_OFF = HIGH;

// pH sensor calibration (optimized)
const int NUM_SAMPLES = 7;
const int AVG_DELAY_MS = 5;
const float SLOPE = 14.38005034237246;
const float INTERCEPT = -15.497796174728297;
const float ADC_MAX = 4095.0;
const float VREF = 3.3;
// Apply simple runtime calibration without reflashing full curve
const float PH_CAL_SLOPE = 0.951f;   // two-point fit from buffer data
const float PH_CAL_OFFSET = 0.564f;  // two-point fit offset

// Sensor constants (consolidated)
#define TURBIDITY_ARRAY_LENGTH 5
#define TDS_ARRAY_LENGTH 20
#define TDS_SAMPLING_INTERVAL 10
#define TURBIDITY_SAMPLING_INTERVAL 5
#define TDS_VREF 3.3
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
#define TURBIDITY_CLEAR_RAW 3200
#define TURBIDITY_DIRTY_RAW 1200
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
  
  
  
  // Initialize ADC
  analogReadResolution(12);  // Set ADC resolution to 12 bits
  
  // Set pH pin attenuation to read up to ~3.3V (11db covers larger range)
  analogSetPinAttenuation(pH_Pin, ADC_11db);
  // Ensure other analog pins use same attenuation for full-range readings
  analogSetPinAttenuation(tdsPin, ADC_11db);
  analogSetPinAttenuation(turbidityPin, ADC_11db);
  
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
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    
  } else {
    
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
      
      // Connection checks suppressed for clean output

      // Set temperature for now
      float temperature = 25.0;  // Default temperature

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
      Serial.println(" C");

      // Upload data with converted turbidity value
      uploadSensorData(turbidityNTU, tdsValue, phCalibrated, temperature);
    } else {
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
  HTTPClient http;

  // Configure HTTP client with proper timeouts
  http.setTimeout(1000);  // 1 second timeout to keep 1s cadence
  http.setReuse(true);     // Reuse connection if possible

  // Begin HTTP request
  http.begin(serverUrl);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  http.addHeader("User-Agent", "ESP32-WaterQuality");
  http.addHeader("Connection", "close");

  // Prepare POST data
  String postData = "turbidity=" + String(turbidity) + "&tds=" + String(tds) + "&ph=" + String(ph) + "&temperature=" + String(temperature);

  // Send POST request
  int httpCode = http.POST(postData);

  if (httpCode > 0) {
    String response = http.getString();
    
    // Check if response contains error message
    if (response.indexOf("ERR_NGROK") != -1) {
      
    }
  } else {
    // Handle specific HTTP error codes
    switch (httpCode) {
      case -1:
        
        break;
      case -2:
        
        break;
      case -3:
        
        break;
      case -4:
        
        break;
      case -11:
        
        break;
      default:
        
        break;
    }
    
    // Try to reconnect WiFi if connection fails
    if (WiFi.status() != WL_CONNECTED) {
      
      WiFi.reconnect();
    }
  }

  http.end();
}

void checkRelayStates() {
  HTTPClient http;

  // Configure HTTP client with proper timeouts
  http.setTimeout(1000);  // 1 second timeout to keep loop responsive
  http.setReuse(true);     // Reuse connection if possible
  
  http.begin(relayControlUrl);
  
  // Add headers for better compatibility
  http.addHeader("User-Agent", "ESP32-WaterQuality");
  http.addHeader("Connection", "close");
  
  int httpCode = http.GET();

  if (httpCode > 0) {
    String response = http.getString();

    // Check if response contains error message
    if (response.indexOf("ERR_NGROK") != -1) {
      
      http.end();
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
                
                relayChanged = true;
              }
            }
          }
        }
      }
    }
  } else {
    // Handle specific HTTP error codes
    switch (httpCode) {
      case -1:
        
        break;
      case -2:
        
        break;
      case -3:
        
        break;
      case -4:
        
        break;
      case -11:
        
        break;
      default:
        
        break;
    }
    
    // Try to reconnect WiFi if connection fails
    if (WiFi.status() != WL_CONNECTED) {
      
      WiFi.reconnect();
    }
  }

  http.end();
}

void handleRelayCommand(int relay, int state) {
  if (relay >= 1 && relay <= 4) {
    HTTPClient http;
    
    // Configure HTTP client with proper timeouts
    http.setTimeout(1000);  // 1 second timeout to keep loop responsive
    http.setReuse(true);     // Reuse connection if possible
    
    http.begin(relayControlUrl);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    http.addHeader("User-Agent", "ESP32-WaterQuality");
    http.addHeader("Connection", "close");

    String postData = "relay=" + String(relay) + "&state=" + String(state);
    int httpCode = http.POST(postData);

    if (httpCode > 0) {
      String response = http.getString();

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
      }
    } else {
      // Handle specific HTTP error codes
      switch (httpCode) {
        case -1:
          
          break;
        case -2:
          
          break;
        case -3:
          
          break;
        case -4:
          
          break;
        case -11:
          
          break;
        default:
          
          break;
      }
    }

    http.end();
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
      
    } else if (rssi < -70) {
      
    }
  } else {
    
    WiFi.reconnect();
  }
}

void checkSensorHealth() {
  // Check pH sensor
  int phRaw = analogRead(pH_Pin);
  float phVoltage = (phRaw * VREF) / ADC_MAX;
  if (phRaw > 4085) {
    
  } else if (phRaw < 10) {
    
  }
  
  // Check turbidity sensor
  int turbidityRaw = analogRead(turbidityPin);
  if (turbidityRaw > 4085) {
    
  } else if (turbidityRaw < 10) {
    
  }
  
  // Check TDS sensor
  int tdsRaw = analogRead(tdsPin);
  if (tdsRaw > 4085) {
    
  } else if (tdsRaw < 10) {
    
  }
}