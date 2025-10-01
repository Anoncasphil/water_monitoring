#include <WiFiS3.h>
#include <ArduinoHttpClient.h>
#include <ArduinoJson.h>
// =========================
// Libraries for DS18B20 Waterproof Temp Sensor
// =========================
#include <OneWire.h>
#include <DallasTemperature.h>

// ---------------- WiFi Config ----------------
char ssid[] = "Converge_2.4GHz_3Fsb56";
char pass[] = "TQkcXYGS";

// ---------------- API Config ----------------
const char server[] = "waterquality.triple7autosupply.com";
int port = 80;

// Upload endpoint for sensor data
const char uploadPath[] = "/api/upload.php";
// Relay control endpoint
const char relayPath[] = "/api/relay_control.php";

WiFiClient wifi;
HttpClient uploadClient = HttpClient(wifi, server, port);
HttpClient relayClient  = HttpClient(wifi, server, port);

// ---------------- Timers ----------------
unsigned long lastUploadMs = 0;
unsigned long lastRelayCheckMs = 0;
const unsigned long UPLOAD_INTERVAL_MS = 5000;   // 5 seconds
const unsigned long RELAY_INTERVAL_MS  = 1000;   // 1 second

// ---------------- Pins ----------------
const int relayPins[4] = {3, 4, 5, 6};  
const int numRelays = 4;

#define RELAY_ON  LOW   // active LOW
#define RELAY_OFF HIGH  // OFF = HIGH

// =========================
// Sensor Pins and Calibration (DO NOT CHANGE CALIBRATION)
// =========================
// DS18B20 on digital pin 2
#define ONE_WIRE_BUS 2
OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);

// pH Sensor on A0
const int PH_PIN = A0;
const int ADC_MAX = 1023;      // 10-bit ADC on Uno R4
const float VREF = 5.0;        // Reference voltage (5V on Uno R4)

// Calibration values (as provided)
float slope = -5.3;
float offset = 21.34;

// TDS Sensor on A2
const int TDS_PIN = A2;

// Turbidity Sensor on A1
const int TURBIDITY_PIN = A1;

// Turbidity calibration constants (provided)
const float V_CLEAR = 2.33;   // Voltage in clear water
const float V_MURKY = 0.26;   // Voltage in murky water
const float MAX_NTU = 3000.0; // Arbitrary max NTU

// Relay states
bool relayStates[4] = {false, false, false, false};

// ---------------- Setup ----------------
void setup() {
  Serial.begin(115200);
  
  // Init relays
  for (int i = 0; i < numRelays; i++) {
    pinMode(relayPins[i], OUTPUT);
    digitalWrite(relayPins[i], RELAY_OFF); // start OFF
  }

  // Connect WiFi
  Serial.print("Connecting to WiFi: ");
  Serial.println(ssid);

  while (WiFi.begin(ssid, pass) != WL_CONNECTED) {
    delay(1000);
    Serial.print(".");
  }
  Serial.println("\nWiFi connected!");

  // Start DS18B20 sensor
  sensors.begin();
}

// ---------------- Loop ----------------
void loop() {
  unsigned long now = millis();

  // Relay check every 1s
  if (now - lastRelayCheckMs >= RELAY_INTERVAL_MS) {
    lastRelayCheckMs = now;
    checkRelayStates();
  }

  // Upload sensors every 5s
  if (now - lastUploadMs >= UPLOAD_INTERVAL_MS) {
    lastUploadMs = now;
    readAndUploadSensors();
  }

  delay(10); // small yield to avoid tight loop
}

// ---------------- Functions ----------------
// Map turbidity voltage to NTU using calibrated linear mapping
float mapToNTU(float voltage) {
  if (voltage >= V_CLEAR) return 0.0f;      // At/above clear → 0 NTU
  if (voltage <= V_MURKY) return MAX_NTU;   // At/below murky → MAX_NTU
  float ntu = (V_CLEAR - voltage) * (MAX_NTU / (V_CLEAR - V_MURKY));
  return ntu;
}

void readAndUploadSensors() {
  // =========================
  // pH Measurement
  // =========================
  int rawPH = analogRead(PH_PIN);
  float voltagePH = rawPH * (VREF / ADC_MAX);
  float pHValue = (slope * voltagePH) + offset;

  // =========================
  // Turbidity Measurement
  // =========================
  int rawTurbidity = analogRead(TURBIDITY_PIN);
  float voltageTurb = rawTurbidity * (VREF / ADC_MAX);
  float NTU = mapToNTU(voltageTurb);

  // =========================
  // TDS Measurement
  // =========================
  int rawTDS = analogRead(TDS_PIN);
  float voltageTDS = rawTDS * (VREF / ADC_MAX);
  float tdsValue = (133.42 * voltageTDS * voltageTDS * voltageTDS
                  - 255.86 * voltageTDS * voltageTDS
                  + 857.39 * voltageTDS) * 0.5;

  // =========================
  // Temperature Measurement (DS18B20)
  // =========================
  sensors.requestTemperatures();
  float temperatureC = sensors.getTempCByIndex(0);

  // =========================
  // Print Results
  // =========================
  Serial.println("---- Sensor Data ----");
  Serial.print("pH: ");
  Serial.print(pHValue, 2);
  Serial.print(" | Turbidity: ");
  Serial.print(NTU, 1);
  Serial.print(" NTU");
  Serial.print(" | TDS: ");
  Serial.print(tdsValue, 0);
  Serial.print(" ppm");
  Serial.print(" | Temp: ");
  if (temperatureC == DEVICE_DISCONNECTED_C) {
    Serial.print("Error");
  } else {
    Serial.print(temperatureC, 2);
    Serial.print(" °C");
  }
  Serial.println();

  // Build URL-encoded form payload to match PHP $_POST expectations
  String postData = "turbidity=" + String(NTU, 1);
  postData += "&tds=" + String(tdsValue, 0);
  postData += "&ph=" + String(pHValue, 2);
  postData += "&temperature=" + String(temperatureC, 2);
  postData += "&in=0";

  // Send POST
  uploadClient.beginRequest();
  uploadClient.post(uploadPath);
  uploadClient.sendHeader("Content-Type", "application/x-www-form-urlencoded");
  uploadClient.sendHeader("Content-Length", postData.length());
  uploadClient.beginBody();
  uploadClient.print(postData);
  uploadClient.endRequest();

  int statusCode = uploadClient.responseStatusCode();
  String response = uploadClient.responseBody();

  Serial.print("Upload status: "); Serial.println(statusCode);
  Serial.println("Response: " + response);
}

void checkRelayStates() {
  relayClient.get(relayPath);
  int status = relayClient.responseStatusCode();
  String body = relayClient.responseBody();

  if (status == 200) {
    DynamicJsonDocument doc(2048);
    DeserializationError error = deserializeJson(doc, body);

    if (!error) {
      if (doc.containsKey("states")) {
        JsonArray states = doc["states"];

        for (JsonObject s : states) {
          int relay = s["relay_number"];
          int value = s["state"];

          if (relay >= 1 && relay <= numRelays) {
            relayStates[relay - 1] = (value == 1);

            digitalWrite(relayPins[relay - 1], relayStates[relay - 1] ? RELAY_ON : RELAY_OFF);
            Serial.println(String("Relay ") + relay + " set to " + (relayStates[relay - 1] ? "ON" : "OFF"));
          }
        }
      }
    } else {
      Serial.print("JSON parse failed: ");
      Serial.println(error.c_str());
    }
  } else {
    Serial.print("Relay check HTTP error: ");
    Serial.println(status);
  }
}
