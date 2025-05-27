#include <WiFi.h>

// WiFi credentials
const char* ssid = "Converge_2.4GHz_D167";
const char* password = "tFtFfMg6";

// Pin definitions (based on your actual wiring)
const int turbidityPin = 33;  // Turbidity sensor connected to GPIO33 (ADC1)
const int tdsPin = 32;        // TDS sensor connected to GPIO32 (ADC1)
const int relayPins[] = {25, 26, 27, 14};  // Relay pins IN1 to IN4
const int numRelays = 4;

// Relay states
bool relayStates[4] = {false, false, false, false};

// Relay control constants
const int RELAY_ON = LOW;
const int RELAY_OFF = HIGH;

// Sensor calibration constants
const int TURBIDITY_MIN = 0;
const int TURBIDITY_MAX = 4095;  // ESP32 ADC is 12-bit
const float TURBIDITY_SCALE = 20.0;

void setup() {
  Serial.begin(115200);
  delay(1000);

  // Connect to Wi-Fi
  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi Connected!");
  Serial.print("IP Address: ");
  Serial.println(WiFi.localIP());

  // Setup relay pins
  for (int i = 0; i < numRelays; i++) {
    pinMode(relayPins[i], OUTPUT);
    digitalWrite(relayPins[i], RELAY_OFF);  // Set to OFF
    relayStates[i] = false;
  }

  Serial.println("INIT:0,0,0,0");
}

void loop() {
  int turbidityValue = analogRead(turbidityPin);
  int tdsValue = analogRead(tdsPin);

  float turbidityNTU = map(turbidityValue, TURBIDITY_MIN, TURBIDITY_MAX, 100, 0) / TURBIDITY_SCALE;
  if (turbidityNTU < 0) turbidityNTU = 0;

  float tdsPPM = map(tdsValue, 0, 4095, 0, 1000);

  Serial.print("DATA:");
  Serial.print(turbidityNTU);
  Serial.print(",");
  Serial.println(tdsPPM);

  if (Serial.available() > 0) {
    String command = Serial.readStringUntil('\n');
    handleRelayCommand(command);
  }

  for (int i = 0; i < numRelays; i++) {
    digitalWrite(relayPins[i], relayStates[i] ? RELAY_ON : RELAY_OFF);
  }

  delay(2000);
}

void handleRelayCommand(String command) {
  if (command.startsWith("RELAY:")) {
    command = command.substring(6);
    int commaIndex = command.indexOf(',');

    if (commaIndex != -1) {
      int relay = command.substring(0, commaIndex).toInt();
      int state = command.substring(commaIndex + 1).toInt();

      if (relay >= 1 && relay <= 4) {
        relayStates[relay - 1] = (state == 1);
        digitalWrite(relayPins[relay - 1], relayStates[relay - 1] ? RELAY_ON : RELAY_OFF);

        Serial.print("RELAY_STATE:");
        for (int i = 0; i < 4; i++) {
          Serial.print(relayStates[i]);
          if (i < 3) Serial.print(",");
        }
        Serial.println();
      }
    }
  }
}
