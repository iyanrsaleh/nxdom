# Terminal QR Code Login - Documentation

## 🎯 Overview

Fitur QR Code Login di Nexa Terminal memungkinkan user untuk login ke dashboard dengan scan QR code dari mobile app, tanpa perlu mengetik username dan password.

## 🚀 Features

- ✅ **Real-time Firebase Integration** - Listen untuk login events
- ✅ **Auto-generated QR Code** - Dengan logo Nexa
- ✅ **Expo Go Style UI** - Modern dan professional
- ✅ **3-Layer Fallback System** - Selalu mendapatkan uniqueId
- ✅ **Terminal Feedback** - Status updates yang jelas
- ✅ **Auto Redirect** - Setelah login berhasil

## 📋 How It Works

### Flow Diagram

```
1. User run command: "z" (nexa start)
   ↓
2. System generate/fetch uniqueId
   ├─ Try 1: Get from NEXA.controllers.data.uniqueId
   ├─ Try 2: Fetch from API /api/session/visitor-key
   └─ Try 3: Generate manual (nexa_[timestamp]_[random])
   ↓
3. Initialize Firebase connection
   ↓
4. Generate QR Code with uniqueId
   ↓
5. Display QR Code in Expo Go style
   ↓
6. Real-time listener active
   ↓
7. User scan QR from mobile app
   ↓
8. Mobile app write to Firebase: { key: uniqueId, email, password, success: true }
   ↓
9. Browser detect Firebase update
   ↓
10. POST to /signin with credentials
   ↓
11. Cleanup Firebase data
   ↓
12. Redirect to dashboard
```

## 🔑 UniqueId Generation Strategy

### Layer 1: Existing NEXA Object (Priority)
```javascript
if (NEXA?.controllers?.data?.uniqueId) {
    uniqueId = NEXA.controllers.data.uniqueId;
}
```
**When available:** Di halaman OAuth (/signin, /signup)

### Layer 2: Session API (Optional)
```javascript
const response = await fetch('/api/session/visitor-key');
const data = await response.json();
uniqueId = data.visitorKey;
```
**When available:** Jika API endpoint sudah di-setup (SessionController.php)

### Layer 3: Manual Generation (Fallback)
```javascript
uniqueId = 'nexa_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
```
**Always works:** Generates unique string seperti `nexa_1763726769415_pc2m6eerb`

## 🎨 UI Components

### QR Wrapper
- Border: `rgba(255, 255, 255, 0.1)`
- Background: `rgba(0, 0, 0, 0.2)`
- Border radius: `8px`
- Padding: `20px`

### Header
```
📱 Scan QR Code to Connect
```

### QR Container
- Background: `rgba(255, 255, 255, 0.95)` (white)
- Box shadow: `0 4px 12px rgba(0, 0, 0, 0.3)`
- Border radius: `12px`
- QR Size: `220x220px`
- Logo: Nexa favicon (25% size)

### Instructions
```
Open the Nexa App on your device
Scan this code to connect and continue your session
```

## 🔥 Firebase Integration

### Configuration
```javascript
const nexaUI = NexaUI();
const render = await nexaUI.Storage().api("oauth").config({ token: true });
const crypto = nexaUI.Crypto("NexaQrV1");
const Config = crypto.decode(render.token);
const crud = nexaUI.Firebase("qrlogin", Config);
```

### Real-time Listener
```javascript
const unsubscribe = crud.red((allData) => {
    const loginData = allData.find((item) => item.key === uniqueId);
    
    if (loginData && loginData.success === true) {
        // Process login
    }
});
```

### Data Structure
```javascript
{
    key: "nexa_1763726769415_pc2m6eerb",
    email: "user@example.com",
    password: "hashedPassword",
    user_name: "username",
    success: true,
    timestamp: 1763726769415
}
```

## 📱 Mobile App Integration

### Step 1: Scan QR Code
Mobile app harus bisa scan QR code dan extract `uniqueId`

### Step 2: Get User Credentials
Ambil email dan password dari user yang login di mobile app

### Step 3: Write to Firebase
```javascript
firebase.database().ref('qrlogin/' + uniqueId).set({
    key: uniqueId,
    email: userEmail,
    password: userPassword,
    user_name: userName,
    success: true,
    timestamp: Date.now()
});
```

### Step 4: Wait for Cleanup
Browser akan hapus data setelah login berhasil

## 🎯 Command Usage

### Open Terminal
```
Ctrl + Shift + Z
```

### Run QR Login
```bash
z
# or
nexa start
```

### Commands Available
```bash
login      - Manual login with email/password
logout     - Logout from current session
z          - Start app with QR login (alias: nexa start)
start      - Start the application
stop       - Stop the application
ping       - Check application status
help       - Show available commands
```

## 🔧 Terminal Messages

### Success Messages
```
✓ Application started successfully!
🔄 Initializing Firebase connection...
✓ Firebase connected successfully
📱 QR Code generated with uniqueId: nexa_xxx
✓ QR Code ready - Waiting for device connection...
✓ Device connected successfully!
✓ Login successful! Redirecting...
```

### Warning Messages
```
⚠ Firebase not initialized - Missing: NexaUI
⚠ NexaQrcode module not available
```

### Error Messages
```
✗ Login failed. Please try again.
✗ Error: [error message]
Failed to generate QR code: [error message]
```

## 🐛 Troubleshooting

### Error: "NexaUI not available"
**Solution:** Pastikan NexaUI library sudah di-load:
```html
<script src="/assets/NexaUi/nexa-ui.min.js"></script>
```

### Error: "Firebase connection failed"
**Solution:** 
1. Check Firebase config di `/api/oauth` endpoint
2. Verify crypto key "NexaQrV1"
3. Check Firebase database rules

### Error: "QR Code not displaying"
**Solution:**
1. Check console untuk error messages
2. Verify NXUI.NexaQrcode tersedia
3. Check element ID "qr5" tidak duplicate

### QR Code generated but login not working
**Solution:**
1. Check mobile app Firebase write permission
2. Verify uniqueId match antara QR dan Firebase
3. Check `/signin` endpoint working
4. Verify user credentials dari mobile app

## 📊 Console Logs

### Debug Information
```javascript
console.log('🔑 Using uniqueId:', uniqueId);
console.log('🔥 Firebase data received:', allData);
console.log('📋 Login data found:', loginData);
console.log('📱 QR Code generated with uniqueId:', uniqueId);
```

### Expected Console Output
```
{credential: {…}, oauth: true, data: {…}}
🔑 Final uniqueId: nexa_1763726769415_pc2m6eerb
🔄 Initializing Firebase connection...
✓ Firebase connected successfully
🔥 Firebase data received: []
📱 QR Code generated with uniqueId: nexa_1763726769415_pc2m6eerb
✓ QR Code ready - Waiting for device connection...

--- After QR scan ---
🔥 Firebase data received: [{key: "nexa_xxx", email: "...", ...}]
📋 Login data found: {key: "nexa_xxx", success: true, ...}
✓ Device connected successfully!
✓ Login successful! Redirecting...
```

## 🔐 Security Considerations

### UniqueId
- Generated dengan timestamp + random string
- Tidak dapat diprediksi
- Berlaku satu kali penggunaan
- Dihapus setelah login berhasil

### Firebase Rules (Recommended)
```json
{
  "rules": {
    "qrlogin": {
      "$uid": {
        ".write": true,
        ".read": true
      }
    }
  }
}
```

### Session Security
- POST ke `/signin` dengan credentials
- Server validasi credentials
- Session di-set oleh server
- Auto redirect setelah sukses

## 📝 API Endpoints

### GET /api/session/visitor-key (Optional)
```json
Response:
{
    "success": true,
    "visitorKey": "abc123...",
    "uniqueId": "abc123...",
    "timestamp": 1763726769
}
```

### POST /signin (Required)
```
Body: 
email=user@example.com&password=xxx

Response:
- Redirect to user dashboard
- Or JSON with redirect URL
```

## 🎨 Customization

### Change QR Size
```javascript
width: 220,  // Change this
height: 220, // Change this
```

### Change Logo Size
```javascript
logoSize: 0.25,    // 25% of QR size
logoMargin: 10,    // Margin around logo
logoRadius: 12,    // Border radius
```

### Change UI Colors
```javascript
// Wrapper background
background: rgba(0, 0, 0, 0.2);

// QR container background
background: rgba(255, 255, 255, 0.95);

// Border color
border: 1px solid rgba(255, 255, 255, 0.1);
```

### Change Text
```javascript
headerText.innerHTML = '📱 Your Custom Text';
instructionsText.innerHTML = 'Your custom instructions';
```

## 📚 Related Files

### JavaScript
- `/assets/NexaUi/Terminal/app.min.js` - Main terminal logic
- `/assets/NexaUi/Terminal/bundle.min.js` - Command line library
- `/assets/NexaUi/Terminal/NexaTerminal.js` - Terminal wrapper
- `/templates/theme/oauth/app.js` - OAuth page QR code

### PHP
- `/controllers/OauthController.php` - OAuth controller
- `/controllers/Api/SessionController.php` - Session API (optional)

### HTML
- `/templates/theme/oauth/Qrsignin.html` - OAuth QR page

## 🚀 Future Enhancements

- [ ] Timeout untuk QR code (auto-refresh setelah 5 menit)
- [ ] Multiple device support
- [ ] QR code history
- [ ] Notification sound saat device connected
- [ ] Customizable QR design
- [ ] Export QR code as image
- [ ] Two-factor authentication integration

## 📞 Support

Jika ada masalah atau pertanyaan, check:
1. Console logs untuk error messages
2. Network tab untuk API calls
3. Firebase console untuk data updates
4. This documentation untuk troubleshooting

---

**Version:** 1.0.0  
**Last Updated:** 2024-11-21  
**Author:** Nexa Development Team

