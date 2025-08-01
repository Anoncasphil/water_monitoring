#include <WiFi.h>
#include <HTTPClient.h>
#include <ESP32Servo.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

#define SDA_PIN 33
#define SCL_PIN 32
#define SERVO_PIN 27
#define COIN_PIN 35

const char* ssid = "Ochea Family 2.4Ghz";
const char* password = "Yukira_1209";
const char* serverUrl = "http://192.168.1.59/rice/upload.php";

LiquidCrystal_I2C lcd(0x27, 16, 2);
Servo myServo;

volatile int pulseCount = 0;
unsigned long lastPulseTime = 0;
int totalAmount = 0;
bool hasDispensed = false;
bool coinInserted = false;

portMUX_TYPE timerMux = portMUX_INITIALIZER_UNLOCKED;

void IRAM_ATTR countPulse() {
  portENTER_CRITICAL_ISR(&timerMux);
  pulseCount++;
  lastPulseTime = millis();
  portEXIT_CRITICAL_ISR(&timerMux);
}

void setup() {
  Serial.begin(115200);
  delay(100);

  // Connect to WiFi
  Serial.println("\n[WiFi] Connecting to WiFi...");
  WiFi.begin(ssid, password);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WiFi] Connected successfully!");
    Serial.print("[WiFi] IP Address: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\n[WiFi] Connection failed!");
  }

  Wire.begin(SDA_PIN, SCL_PIN);

  lcd.init();
  lcd.backlight();
  lcd.setCursor(0, 0);
  lcd.print("60 Pesos / kg");
  lcd.setCursor(0, 1);
  lcd.print("Waiting...");

  myServo.attach(SERVO_PIN);
  myServo.write(15);
  delay(500);

  pinMode(COIN_PIN, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(COIN_PIN), countPulse, FALLING);
}

void sendDataToServer(int amount) {
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("[Database] Attempting to connect to database...");
    HTTPClient http;
    http.begin(serverUrl);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");

    String postData = "totalAmount=" + String(amount);
    Serial.println("[Database] Sending data: " + postData);
    
    int httpResponseCode = http.POST(postData);

    if (httpResponseCode > 0) {
      Serial.printf("[Database] HTTP Response code: %d\n", httpResponseCode);
      String response = http.getString();
      Serial.println("[Database] Server response: " + response);
    } else {
      Serial.printf("[Database] Error on sending POST: %s\n", http.errorToString(httpResponseCode).c_str());
    }
    http.end();
  } else {
    Serial.println("[WiFi] WiFi Disconnected - Cannot send data to database");
  }
}

void loop() {
  // Check WiFi status periodically
  static unsigned long lastWifiCheck = 0;
  if (millis() - lastWifiCheck > 30000) { // Check every 30 seconds
    lastWifiCheck = millis();
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("[WiFi] Connection lost! Attempting to reconnect...");
      WiFi.reconnect();
    }
  }

  // Detect if coin input finished
  if (pulseCount > 0 && (millis() - lastPulseTime) > 300) {
    if (!coinInserted) {
      coinInserted = true;
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("Insert Coin:");
    }

    if (pulseCount == 1) totalAmount += 1;
    else if (pulseCount == 5) totalAmount += 5;
    else if (pulseCount == 10) totalAmount += 10;
    else if (pulseCount == 20) totalAmount += 20;

    pulseCount = 0;
  }

  lcd.setCursor(0, 1);
  lcd.print("Total: ");
  lcd.print(totalAmount);
  lcd.print(" PHP  ");

  delay(200);

  if (totalAmount >= 60 && !hasDispensed) {
    Serial.println("[System] Amount reached! Starting dispensing process...");
    Serial.print("[System] Total Amount: ");
    Serial.println(totalAmount);

    // Send successful transaction to database
    Serial.println("[System] Recording successful transaction...");
    sendDataToServer(totalAmount);

    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Dispensing 1kg...");

    Serial.println("[System] Opening dispenser...");
    for (int pos = 15; pos <= 120; pos++) {
      myServo.write(pos);
      delay(10);
    }

    Serial.println("[System] Dispensing rice (60 seconds)...");
    delay(60000); // Dispensing time

    Serial.println("[System] Closing dispenser...");
    for (int pos = 120; pos >= 15; pos--) {
      myServo.write(pos);
      delay(10);
    }

    // Reset system without uploading to database
    Serial.println("[System] Resetting system...");
    totalAmount = 0;
    hasDispensed = true;
    coinInserted = false;

    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("60 Pesos / kg");
    lcd.setCursor(0, 1);
    lcd.print("Waiting...");
  }

  if (totalAmount < 60) {
    hasDispensed = false;
  }
}
