# NexaScanqr - QR Code Scanner Component

Komponen React Native untuk scanning QR code dan barcode dengan fitur lengkap dan antarmuka yang user-friendly.

## Installation

Untuk menggunakan fitur scanning secara penuh, install dependencies berikut:

```bash
npm install expo-camera expo-barcode-scanner
```

## Import

```javascript
import { NexaScanqr } from "NexaUI";
// atau
import NexaScanqr from "NexaUI/Scanner";
```

## Basic Usage

```javascript
import React, { useState } from "react";
import { View, Button } from "react-native";
import { NexaScanqr } from "NexaUI";

const MyComponent = () => {
  const [showScanner, setShowScanner] = useState(false);

  const handleScanSuccess = (result) => {
    console.log("Scanned:", result.data);
    setShowScanner(false);
  };

  return (
    <View>
      <Button title="Open Scanner" onPress={() => setShowScanner(true)} />

      <NexaScanqr
        isVisible={showScanner}
        onScanSuccess={handleScanSuccess}
        onClose={() => setShowScanner(false)}
      />
    </View>
  );
};
```

## Props

| Prop            | Type       | Default                   | Description                       |
| --------------- | ---------- | ------------------------- | --------------------------------- |
| `showScanner`   | `boolean`  | `false`                   | Toggle untuk menampilkan scanner  |
| `onScanSuccess` | `function` | `undefined`               | Callback ketika scan berhasil     |
| `onScanError`   | `function` | `undefined`               | Callback ketika terjadi error     |
| `onClose`       | `function` | `undefined`               | Callback untuk menutup scanner    |
| `scannerTitle`  | `string`   | `"Scan QR Code"`          | Judul yang ditampilkan di header  |
| `enableTorch`   | `boolean`  | `true`                    | Enable/disable tombol flash       |
| `enableFlip`    | `boolean`  | `true`                    | Enable/disable tombol flip camera |
| `showScanArea`  | `boolean`  | `true`                    | Tampilkan area scan dengan frame  |
| `scanAreaSize`  | `number`   | `250`                     | Ukuran area scan dalam pixel      |
| `autoFocus`     | `boolean`  | `true`                    | Auto focus kamera                 |
| `scanTypes`     | `array`    | `[qr, aztec, ean13, ...]` | Tipe barcode yang didukung        |
| `style`         | `object`   | `undefined`               | Custom style untuk container      |
| `overlayColor`  | `string`   | `"rgba(0,0,0,0.5)"`       | Warna overlay                     |

## Scan Result Object

Ketika scan berhasil, callback `onScanSuccess` akan menerima object dengan struktur:

```javascript
{
  type: "qr",                           // Tipe barcode
  data: "https://example.com",          // Data yang di-scan
  bounds: { ... },                      // Koordinat lokasi barcode
  timestamp: "2024-01-01T00:00:00.000Z" // Waktu scan
}
```

## Advanced Usage

```javascript
<NexaScanqr
  showScanner={showScanner}
  onScanSuccess={(result) => {
    console.log("Scan Success:", result);
    // Process scan result
    if (result.type === "qr") {
      // Handle QR code
    }
  }}
  onScanError={(error) => {
    console.error("Scan Error:", error);
    Alert.alert("Error", error);
  }}
  onClose={() => setShowScanner(false)}
  scannerTitle="Scan Barcode"
  enableTorch={true}
  enableFlip={true}
  showScanArea={true}
  scanAreaSize={300}
  autoFocus={true}
  scanTypes={["qr", "ean13", "code128"]}
/>
```

## Supported Barcode Types

- QR Code
- Aztec
- EAN-13
- EAN-8
- UPC-E
- Data Matrix
- Code 128
- Code 39
- Code 93
- Codabar
- PDF417
- ITF-14

## Features

### ✅ Current Features

- **Mock Scanner Mode** - Berfungsi tanpa dependencies untuk testing
- **Full Screen Modal** - Antarmuka scanner fullscreen
- **Permission Handling** - Otomatis request camera permission
- **Customizable UI** - Dapat dikustomisasi sesuai kebutuhan
- **Multiple Barcode Support** - Mendukung berbagai format barcode
- **Flash/Torch Control** - Toggle flash untuk kondisi gelap
- **Camera Flip** - Switch antara front/back camera
- **Scan Area Indicator** - Visual guide untuk area scanning
- **Error Handling** - Comprehensive error handling

### 🔄 Ready When Dependencies Installed

- **Real Camera Scanning** - Scan menggunakan kamera device
- **Barcode Detection** - Deteksi real-time berbagai format
- **Auto Focus** - Auto focus kamera untuk hasil optimal

## Mock Mode

Jika dependencies `expo-camera` dan `expo-barcode-scanner` belum terinstall, komponen akan berjalan dalam mock mode dengan:

- Placeholder camera interface
- Simulate scan button untuk testing
- Instruksi instalasi dependencies
- Semua callback dan prop tetap berfungsi

## Installation Guide

1. **Install Dependencies:**

   ```bash
   npm install expo-camera expo-barcode-scanner
   ```

2. **Update NexaScanqr.js:**

   - Uncomment import statements
   - Uncomment Camera component usage
   - Comment out mock CameraView

3. **Permissions (iOS):**
   Tambahkan ke `Info.plist`:

   ```xml
   <key>NSCameraUsageDescription</key>
   <string>App needs camera access to scan QR codes</string>
   ```

4. **Permissions (Android):**
   Permissions sudah included dalam expo-camera

## Troubleshooting

### Common Issues

1. **"expo-camera could not be found"**

   - Install: `npm install expo-camera expo-barcode-scanner`
   - Restart Metro bundler

2. **Permission Denied**

   - Check device camera permissions
   - Re-request permissions via component

3. **Scanner tidak muncul**
   - Pastikan `showScanner={true}`
   - Check console untuk error messages

### Mock Mode Testing

Untuk testing tanpa dependencies:

```javascript
// Component akan otomatis masuk mock mode
// Gunakan "Simulate QR Scan" button untuk testing callback
```

## Example Files

Lihat file example di:

- `public/exsampel/ScannerExample.js` - Complete usage example
- Mencakup scan history, error handling, dan UI best practices

## Support

Komponen ini mendukung:

- ✅ React Native
- ✅ Expo managed workflow
- ✅ Expo bare workflow
- ✅ iOS & Android
- ⚠️ Web (mock mode only)

## License

Part of NexaUI package
