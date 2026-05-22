# NexaStores - React Native API Store Manager

## 📚 Daftar Isi

- [Pengenalan](#pengenalan)
- [Instalasi](#instalasi)
- [Quick Start](#quick-start)
- [Auto User ID Injection](#auto-user-id-injection)
- [API Methods](#api-methods)
- [Package Controllers](#package-controllers)
- [Models Integration](#models-integration)
- [Events Communication](#events-communication)
- [Security & Encryption](#security--encryption)
- [API Reference](#api-reference)
- [Best Practices](#best-practices)
- [Examples](#examples)

---

## 🎯 Pengenalan

**NexaStores** adalah API Store Manager untuk React Native yang menyediakan unified API methods dengan **automatic userid injection** dari session storage. NexaStores dirancang khusus untuk:

- ✅ **Auto User ID Injection** - Otomatis ambil `userid` dari `NexaDBLite` session
- ✅ **Method Chaining** - Fluent API dengan Proxy
- ✅ **Encrypted Communication** - Auto encryption untuk sensitive data
- ✅ **React Native Compatible** - Works on iOS & Android
- ✅ **No Global Dependencies** - Tidak bergantung pada `NEXA` global object

### 🏗️ Arsitektur

```
NexaStores.js
├── NexaFetch (HTTP Client)
├── NexaEncrypt (Auto Encryption)
├── NexaDBLite (Session Storage)
└── Server.API_URL (Endpoint: /api/user)
    └── ApiController.php
        └── UserController.php
            └── Auto decrypt & execute
```

### 📋 Session Data Structure

NexaStores mengambil `userid` dari session storage:

**Store:** `"userSessions"`  
**Key:** `"userSession"`  
**Field:** `"userid"`

```json
{
  "id": "userSession",
  "success": true,
  "userid": 1,  // <-- Field ini yang digunakan
  "user_name": "iandantrik",
  "email": "admin@gmail.com",
  "status": "admin",
  "role": "admin",
  "isLoggedIn": true,
  ...
}
```

---

## 📦 Instalasi

```javascript
import { NexaStores } from 'NexaUI';
// atau
import { NexaStores } from './package/Storage/NexaStores.js';
```

---

## 🚀 Quick Start

### Basic Usage

```javascript
import { NexaStores } from 'NexaUI';

// Inisialisasi
const store = NexaStores();

// API call dengan auto userid injection
const result = await store.api("endpoint").post({ 
  data: "value" 
});

// userid otomatis ditambahkan:
// { data: "value", userid: 1 }
```

### Package Controller

```javascript
const store = NexaStores();

// Call package controller method
const result = await store.package("Applications").accessUser({
  data: "value"
});

// Response:
// {
//   status: "success",
//   data: {...},
//   controller: "ApplicationsControllers",
//   method: "accessUser"
// }
```

---

## 🔐 Auto User ID Injection

NexaStores **otomatis** mengambil `userid` dari session dan menambahkannya ke semua request.

### Cara Kerja

1. **Ambil dari NexaDBLite:**
   ```javascript
   const userid = await NexaDBLite.get("userSessions", "userSession");
   // userid = userData.userid
   ```

2. **Fallback ke NEXA.userId (jika ada):**
   ```javascript
   // Jika session tidak ada, cek global NEXA
   if (typeof NEXA !== 'undefined' && NEXA.userId) {
     return NEXA.userId;
   }
   ```

3. **Auto Inject ke Request:**
   ```javascript
   // Sebelum
   { data: "value" }
   
   // Sesudah (otomatis)
   { data: "value", userid: 1 }
   ```

### Contoh

```javascript
const store = NexaStores();

// Request tanpa userid
await store.api("endpoint").post({ name: "John" });

// Payload yang dikirim (otomatis):
// { name: "John", userid: 1 }  // userid dari session
```

---

## 🌐 API Methods

### `api(endpoint).method(data)`

Method chaining untuk API calls dengan auto userid injection.

```javascript
const store = NexaStores();

// GET request
const data = await store.api("users").get();

// POST request
const result = await store.api("users").post({ 
  name: "John" 
});

// PUT request
const updated = await store.api("users").put({ 
  id: 1, 
  name: "Jane" 
});

// DELETE request
const deleted = await store.api("users").delete();
```

**Endpoint:** `Server.API_URL + "/" + endpoint + "/" + method`  
**Base URL:** `http://192.168.1.17/dev/api`

### `url(endpoint).method(data)`

Sama seperti `api()`, tapi menggunakan base URL yang berbeda.

```javascript
const store = NexaStores();

const result = await store.url("custom").post({ data: "value" });
```

### `get(endpoint, options)`

GET request method.

```javascript
const store = NexaStores();

const users = await store.get("users", { 
  status: "active" 
});

// userid otomatis ditambahkan ke options
// { status: "active", userid: 1 }
```

**Endpoint:** `Server.API_URL + "/user/" + endpoint`

### `post(endpoint, data, options)`

POST request method.

```javascript
const store = NexaStores();

const result = await store.post("users", {
  name: "John",
  email: "john@example.com"
});

// userid otomatis ditambahkan ke data
// { name: "John", email: "john@example.com", userid: 1 }
```

**Endpoint:** `Server.API_URL + "/user/" + endpoint`

### `put(endpoint, data, options)`

PUT request method.

```javascript
const store = NexaStores();

const updated = await store.put("users", {
  id: 1,
  name: "Jane"
});

// userid otomatis ditambahkan
```

### `delete(endpoint, options)`

DELETE request method.

```javascript
const store = NexaStores();

const deleted = await store.delete("users", {
  id: 1
});

// userid otomatis ditambahkan ke options
```

---

## 📦 Package Controllers

### `package(packageName).methodName(data)`

Call package controller method dengan auto encryption dan userid injection.

```javascript
const store = NexaStores();

// Call ApplicationsControllers::accessUser()
const result = await store.package("Applications").accessUser({
  data: "value"
});

// Payload yang dikirim:
// {
//   controllers: "ApplicationsControllers",
//   method: "accessUser",
//   params: "encrypted_json_string"  // { data: "value", userid: 1 }
// }
```

### Dynamic Package

```javascript
// Applications package
await store.package("Applications").methodName(data);

// Users package
await store.package("Users").methodName(data);

// Custom package
await store.package("MyPackage").methodName(data);
```

### Default Package

```javascript
const store = NexaStores();

// Default package: "packages"
await store.packages().methodName(data);

// Custom package name
const store = NexaStores({ packages: "myPackages" });
await store.myPackages().methodName(data);
```

---

## 🗄️ Models Integration

### `models(modelName).methodName(...params)`

Call model method dengan auto encryption dan userid injection.

```javascript
const store = NexaStores();

// Call model method
const result = await store.models("Office").tablesShow();

// Payload yang dikirim:
// {
//   model: "Office",
//   method: "tablesShow",
//   params: "encrypted_json_array"  // [{ userid: 1 }]
// }
```

### With Parameters

```javascript
// Single parameter
await store.models("Office").tablesShow({ table: "users" });

// Multiple parameters
await store.models("Office").methodName(param1, param2, { userid: 1 });
// userid otomatis ditambahkan ke params
```

---

## 📡 Events Communication

### `events(path).methodName(data)`

Event-based communication dengan auto encryption.

```javascript
const store = NexaStores();

// Call event method
const result = await store.events("User").login({
  email: "admin@example.com",
  password: "password123"
});

// Payload yang dikirim:
// {
//   path: "User/login",
//   data: "encrypted_json_string"  // { email: "...", password: "...", userid: 1 }
// }
```

### Event Path Conversion

```javascript
// CamelCase → Path format
"UserLogin" → "User/login"
"UserProfile" → "User/profile"
```

---

## 🔒 Security & Encryption

### Auto Encryption

NexaStores otomatis mengenkripsi data sensitive sebelum dikirim:

```javascript
// Package Controllers
await store.package("Applications").accessUser({ data: "value" });
// params: "encrypted_json_string"

// Models
await store.models("Office").tablesShow({ table: "users" });
// params: "encrypted_json_array"

// Events
await store.events("User").login({ email: "...", password: "..." });
// data: "encrypted_json_string"
```

### Encryption Details

- **Algorithm:** XOR + Base64
- **Secret Key:** `"nexa-default-secret-key-2025"` (default)
- **Method:** `NexaEncrypt.encryptJson()`
- **Backend:** Auto decrypt menggunakan `Encrypt->decryptJson()`

---

## 📖 API Reference

### Constructor

```javascript
NexaStores(options?)
```

**Parameters:**
- `options` (optional): Configuration object
  - `packages` (string): Package name (default: `"packages"`)

**Example:**
```javascript
// Default
const store = NexaStores();

// Custom package name
const store = NexaStores({ packages: "myPackages" });
```

### Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `api(endpoint).method(data)` | Method chaining API call | `Promise` |
| `url(endpoint).method(data)` | Method chaining dengan custom URL | `Promise` |
| `get(endpoint, options)` | GET request | `Promise` |
| `post(endpoint, data, options)` | POST request | `Promise` |
| `put(endpoint, data, options)` | PUT request | `Promise` |
| `delete(endpoint, options)` | DELETE request | `Promise` |
| `package(name).method(data)` | Package controller call | `Promise` |
| `packages().method(data)` | Default package call | `Promise` |
| `models(name).method(...params)` | Model method call | `Promise` |
| `events(path).method(data)` | Event-based call | `Promise` |
| `helper()` | Get controller config | `Object` |

---

## 💡 Best Practices

### 1. Reuse Store Instance

```javascript
// ❌ BAD: Create new instance setiap kali
const result1 = await NexaStores().api("endpoint").post(data1);
const result2 = await NexaStores().api("endpoint").post(data2);

// ✅ GOOD: Reuse instance
const store = NexaStores();
const result1 = await store.api("endpoint").post(data1);
const result2 = await store.api("endpoint").post(data2);
```

### 2. Handle Errors

```javascript
const store = NexaStores();

try {
  const result = await store.api("endpoint").post({ data: "value" });
  console.log("Success:", result);
} catch (error) {
  console.error("Error:", error.message);
  // Handle error
}
```

### 3. Check Session Before Call

```javascript
import { NexaDBLite } from 'NexaUI';

// Check if user is logged in
const session = await NexaDBLite.get("userSessions", "userSession");
if (session && session.isLoggedIn) {
  const store = NexaStores();
  const result = await store.api("endpoint").post({ data: "value" });
}
```

### 4. Use Package Controllers untuk Business Logic

```javascript
// ✅ GOOD: Use package controllers
const store = NexaStores();
const result = await store.package("Applications").accessUser({
  data: "value"
});

// ❌ BAD: Direct API call untuk business logic
const result = await store.api("applications/accessUser").post({
  data: "value"
});
```

---

## 📚 Examples

### Example 1: User Management

```javascript
import { NexaStores } from 'NexaUI';

const store = NexaStores();

// Get user profile
const profile = await store.package("Users").getProfile();

// Update profile
const updated = await store.package("Users").updateProfile({
  name: "John Doe",
  email: "john@example.com"
});

// Change password
const changed = await store.package("Users").changePassword({
  old_password: "old123",
  new_password: "new123"
});
```

### Example 2: Application Features

```javascript
const store = NexaStores();

// Access application feature
const access = await store.package("Applications").accessUser({
  feature: "dashboard"
});

// Get application data
const data = await store.package("Applications").getData({
  type: "settings"
});

// Update application
const updated = await store.package("Applications").updateSettings({
  theme: "dark",
  language: "en"
});
```

### Example 3: Model Operations

```javascript
const store = NexaStores();

// Get tables list
const tables = await store.models("Office").tablesShow();

// Get table data
const data = await store.models("Office").tableData({
  table: "users",
  limit: 10
});

// Execute custom model method
const result = await store.models("Office").customMethod({
  param1: "value1",
  param2: "value2"
});
```

### Example 4: Event Communication

```javascript
const store = NexaStores();

// User login event
const login = await store.events("User").login({
  email: "admin@example.com",
  password: "password123"
});

// User logout event
const logout = await store.events("User").logout();

// Notification event
const notify = await store.events("Notification").send({
  title: "New Message",
  message: "You have a new message"
});
```

### Example 5: CRUD Operations

```javascript
const store = NexaStores();

// CREATE
const created = await store.post("users", {
  name: "John Doe",
  email: "john@example.com"
});

// READ
const users = await store.get("users", { status: "active" });

// UPDATE
const updated = await store.put("users", {
  id: 1,
  name: "Jane Doe"
});

// DELETE
const deleted = await store.delete("users", { id: 1 });
```

### Example 6: Method Chaining

```javascript
const store = NexaStores();

// Chain multiple API calls
const [users, posts, comments] = await Promise.all([
  store.api("users").get(),
  store.api("posts").get(),
  store.api("comments").get()
]);
```

---

## 🔍 Perbandingan dengan NexaModels

| Feature | NexaStores | NexaModels |
|---------|------------|------------|
| **Purpose** | User endpoints API | SQL Query Builder + API |
| **Auto User ID** | ✅ Yes (from session) | ❌ No |
| **SQL Builder** | ❌ No | ✅ Yes |
| **Endpoint** | `/api/user` | `/api/models` |
| **Encryption** | ✅ Package/Model/Event | ✅ SQL & Table |
| **Method Chaining** | ✅ Yes (Proxy) | ✅ Yes (Fluent) |
| **Package Controllers** | ✅ Yes | ❌ No |
| **Models Integration** | ✅ Yes | ❌ No |
| **Events** | ✅ Yes | ❌ No |

### Kapan Menggunakan NexaStores?

✅ **Gunakan NexaStores untuk:**
- User-specific endpoints
- Package controller calls
- Model method calls
- Event-based communication
- Business logic operations

### Kapan Menggunakan NexaModels?

✅ **Gunakan NexaModels untuk:**
- Database queries (SELECT, INSERT, UPDATE, DELETE)
- Complex SQL queries
- Data aggregation
- Pagination
- SQL generation only

---

## 🐛 Troubleshooting

### Error: "userid is null"

```javascript
// Check session
const session = await NexaDBLite.get("userSessions", "userSession");
if (!session || !session.userid) {
  // User belum login
  // Redirect ke login page
}
```

### Error: HTTP 500

```javascript
// Check:
// 1. Backend endpoint exists
// 2. Controller method exists
// 3. Encryption/decryption working
// 4. Network connection
```

### Error: "Controller not found"

```javascript
// Check package name
const store = NexaStores({ packages: "Applications" });
// Pastikan backend punya ApplicationsControllers
```

---

## 📝 Configuration

### config.js

```javascript
const hosts = "http://192.168.1.17/dev";
const Server = {
  API_URL: hosts + "/api",  // Used by NexaStores
  FILE_URL: hosts,
};
```

### Custom Package Name

```javascript
// Default: "packages"
const store = NexaStores();

// Custom: "myPackages"
const store = NexaStores({ packages: "myPackages" });
// Calls: MyPackagesControllers
```

---

## 🔗 Related Documentation

- [NexaModels Documentation](./NexaModels.md) - SQL Query Builder
- [NexaFetch Documentation](./NexaFetch.md) - HTTP Client
- [NexaEncrypt Documentation](./NexaEncrypt.md) - Encryption utility
- [NexaDBLite Documentation](./NexaDBLite.md) - Session storage
- [Config Documentation](../config.js) - API configuration

---

## 📝 Changelog

### Version 2.0.0 (Current)
- ✅ React Native compatible
- ✅ Auto userid injection from NexaDBLite
- ✅ No dependency on global NEXA object
- ✅ Package controllers support
- ✅ Models integration
- ✅ Events communication
- ✅ Auto encryption

### Version 1.0.0
- ✅ Initial release
- ✅ Basic API methods
- ✅ Method chaining

---

## 📞 Support

Untuk pertanyaan atau issue, silakan buat issue di repository atau hubungi tim development.

---

**Made with ❤️ by Nexa Framework**

