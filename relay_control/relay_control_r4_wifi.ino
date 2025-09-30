#include <WiFiS3.h>
#include <ArduinoHttpClient.h>
#include <ArduinoJson.h>

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

// ---------------- Pins ----------------
const int relayPins[4] = {3, 4, 5, 6};  
const int numRelays = 4;

#define RELAY_ON  LOW   // active LOW
#define RELAY_OFF HIGH  // OFF = HIGH

#define tempPin 2
#define tdsPin A0
#define turbidityPin A1
#define phPin A2

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
}

// ---------------- Loop ----------------
void loop() {
  readAndUploadSensors();
  checkRelayStates();
  delay(5000); // run every 5s
}

// ---------------- Functions ----------------
void readAndUploadSensors() {
  // Read sensors (dummy analog for now)
  int tempVal = analogRead(tempPin);
  int tdsVal = analogRead(tdsPin);
  int turbidityVal = analogRead(turbidityPin);
  int phVal = analogRead(phPin);

  // Convert tempVal (replace with DS18B20/DHT if needed)
  float temperature = (tempVal / 1023.0) * 100.0;

  Serial.println("---- Sensor Data ----");
  Serial.print("Temperature: "); Serial.println(temperature);
  Serial.print("TDS: "); Serial.println(tdsVal);
  Serial.print("Turbidity: "); Serial.println(turbidityVal);
  Serial.print("pH: "); Serial.println(phVal);

  // Build JSON payload
  String postData = "{";
  postData += "\"temperature\":" + String(temperature) + ",";
  postData += "\"tds\":" + String(tdsVal) + ",";
  postData += "\"turbidity\":" + String(turbidityVal) + ",";
  postData += "\"ph\":" + String(phVal);
  postData += "}";

  // Send POST
  uploadClient.beginRequest();
  uploadClient.post(uploadPath);
  uploadClient.sendHeader("Content-Type", "application/json");
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

            digitalWrite(
              relayPins[relay - 1],
              relayStates[relay - 1] ? RELAY_ON : RELAY_OFF
            );

              Serial.print("Relay ");
              Serial.print(relay);
            Serial.print(" set to ");
            Serial.println(relayStates[relay - 1] ? "ON" : "OFF");
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
