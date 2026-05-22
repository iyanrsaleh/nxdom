# 📊 Analisis Pola CRUD di IndexDB.js

## 🎯 Ringkasan Pola

File `IndexDB.js` menggunakan **AsyncStorage** sebagai backend storage untuk React Native, dengan pola enkripsi otomatis dan observer pattern.

---

## 🔑 Pola Kunci yang Digunakan

### 1. **Format Storage Key**
```javascript
// Format: {dbName}_{storeName}_{id}
_getStorageKey(storeName, key) {
  return `${this.dbName || 'default'}_${storeName}_${key}`;
}
```

**Contoh:**
- Database: `MyAppDB`
- Store: `users`
- ID: `user_123`
- **Key yang dihasilkan:** `MyAppDB_users_user_123`

---

## 📝 Pola SET (Menyimpan Data)

### **Manual Mode: `set(storeName, data)`**
```javascript
set(storeName, data) {
  // 1. Validasi database initialized
  // 2. Validasi data.id harus ada
  // 3. Tambah timestamp (createdAt, updatedAt)
  // 4. Track store ke Set
  // 5. 🔐 Encrypt data (OTOMATIS)
  // 6. Generate storage key: {dbName}_{storeName}_{id}
  // 7. Save ke AsyncStorage
  // 8. Decrypt untuk notify observers
  // 9. Return data.id
}
```

### **Auto Mode: `setAuto(storeName, data)`**
```javascript
setAuto(storeName, data) {
  // SAMA dengan set(), TAPI:
  // - Auto-create store jika belum ada
  // - Tidak perlu track store manual
}
```

### **Alur SET:**
```
Data Input
  ↓
Validasi (id harus ada)
  ↓
Tambahkan Timestamp
  ↓
Encrypt Data Fields (OTOMATIS)
  ↓
Generate Key: {dbName}_{storeName}_{id}
  ↓
AsyncStorage.setItem(key, JSON.stringify(data))
  ↓
Decrypt untuk Observer
  ↓
Notify Observers
  ↓
Return data.id
```

---

## 📖 Pola GET (Mengambil Data)

### **Manual Mode: `get(storeName, key)`**
```javascript
get(storeName, key) {
  // 1. Validasi database initialized
  // 2. Validasi key (jika null/undefined, return null)
  // 3. Generate storage key: {dbName}_{storeName}_{key}
  // 4. AsyncStorage.getItem(key)
  // 5. Parse JSON
  // 6. 🔐 Decrypt data (OTOMATIS)
  // 7. Return decrypted data atau null
}
```

### **Auto Mode: `getAuto(storeName, key)`**
```javascript
getAuto(storeName, key) {
  // SAMA dengan get(), TAPI:
  // - Auto-create store jika belum ada
}
```

### **Alur GET:**
```
Key Input
  ↓
Validasi Key (null/undefined → return null)
  ↓
Generate Key: {dbName}_{storeName}_{key}
  ↓
AsyncStorage.getItem(key)
  ↓
Parse JSON
  ↓
Decrypt Data Fields (OTOMATIS)
  ↓
Return Decrypted Data atau null
```

---

## 📚 Pola GETALL (Mengambil Semua Data)

### **Manual Mode: `getAll(storeName)`**
```javascript
getAll(storeName) {
  // 1. Validasi database initialized
  // 2. Get all keys dengan prefix: {dbName}_{storeName}_
  // 3. AsyncStorage.multiGet(allKeys)
  // 4. Loop setiap item:
  //    - Parse JSON
  //    - 🔐 Decrypt data
  //    - Push ke array
  // 5. Return { data: [...] }
}
```

### **Auto Mode: `getAllAuto(storeName)`**
```javascript
getAllAuto(storeName) {
  // SAMA dengan getAll(), TAPI:
  // - Auto-create store jika belum ada
}
```

### **Alur GETALL:**
```
Store Name Input
  ↓
Get All Keys dengan Prefix: {dbName}_{storeName}_
  ↓
AsyncStorage.multiGet(allKeys)
  ↓
Loop setiap [key, value]:
  ├─ Parse JSON
  ├─ Decrypt Data
  └─ Push ke Array
  ↓
Return { data: [decryptedItems] }
```

---

## 🗑️ Pola DELETE (Menghapus Data)

### **Manual Mode: `delete(storeName, key)`**
```javascript
delete(storeName, key) {
  // 1. Validasi database initialized
  // 2. Validasi key (jika null/undefined, return false)
  // 3. Generate storage key: {dbName}_{storeName}_{key}
  // 4. AsyncStorage.removeItem(key)
  // 5. Notify observers dengan changeType: "delete"
  // 6. Return true/false
}
```

### **Auto Mode: `deleteAuto(storeName, key)`**
```javascript
deleteAuto(storeName, key) {
  // SAMA dengan delete(), TAPI:
  // - Auto-create store jika belum ada
}
```

### **Alur DELETE:**
```
Key Input
  ↓
Validasi Key (null/undefined → return false)
  ↓
Generate Key: {dbName}_{storeName}_{key}
  ↓
AsyncStorage.removeItem(key)
  ↓
Notify Observers (changeType: "delete")
  ↓
Return true/false
```

---

## 🔐 Pola Enkripsi (OTOMATIS)

### **Encrypt saat SET:**
```javascript
encryptDataFields(data) {
  // 1. Auto-initialize encryption jika belum
  // 2. Loop setiap field (kecuali id, createdAt, updatedAt)
  // 3. Encrypt value dengan encryptor.obfuscateJson()
  // 4. Tambahkan flag _encrypted: true
  // 5. Return encrypted data
}
```

### **Decrypt saat GET/GETALL:**
```javascript
decryptDataFields(encryptedData) {
  // 1. Cek flag _encrypted
  // 2. Loop setiap field
  // 3. Decrypt dengan encryptor.deobfuscateJson()
  // 4. Parse JSON jika memungkinkan
  // 5. Return decrypted data
}
```

---

## 🔔 Pola Observer (Real-time Updates)

### **Notify Observers:**
```javascript
notifyObservers(storeName, data, changeType) {
  // changeType: "update", "delete", "refresh"
  // - Trigger semua callback yang watch store ini
  // - Broadcast ke tabs lain (jika supported)
}
```

### **Watch Store:**
```javascript
watch(storeName, callback, options) {
  // 1. Generate watchId unik
  // 2. Register callback ke observers Map
  // 3. Return unwatch function
}
```

---

## 📊 Perbandingan Manual vs Auto Mode

| Fitur | Manual Mode | Auto Mode |
|-------|------------|-----------|
| **Store Creation** | Harus dibuat manual | Auto-create saat digunakan |
| **Method Name** | `set()`, `get()`, `getAll()`, `delete()` | `setAuto()`, `getAuto()`, `getAllAuto()`, `deleteAuto()` |
| **Error Handling** | Reject jika store tidak ada | Auto-create store, lalu proceed |
| **Use Case** | Kontrol penuh, struktur jelas | Development cepat, fleksibel |

---

## 🎯 Kesimpulan Pola

### **1. Key Generation Pattern:**
```
{dbName}_{storeName}_{id}
```

### **2. Data Flow Pattern:**
```
Input → Validasi → Encrypt → Save → Decrypt → Notify → Return
```

### **3. Error Handling Pattern:**
- **SET/DELETE:** Reject dengan error message
- **GET/GETALL:** Return null/[] (graceful degradation)

### **4. Encryption Pattern:**
- **Auto-enabled** by default
- **Encrypt** saat SET
- **Decrypt** saat GET/GETALL
- **Skip fields:** id, createdAt, updatedAt

### **5. Observer Pattern:**
- **Notify** setelah SET/DELETE
- **Watch** untuk real-time updates
- **Broadcast** ke tabs lain (jika supported)

---

## 💡 Tips Penggunaan

1. **Gunakan Auto Mode** untuk development cepat
2. **Gunakan Manual Mode** untuk production (lebih predictable)
3. **Selalu include `id`** di data saat SET
4. **Encryption otomatis**, tidak perlu setup manual
5. **Observer pattern** untuk real-time UI updates

---

## 📝 Contoh Penggunaan

### **Auto Mode (Recommended):**
```javascript
// Initialize
await IndexedDBManager.init('MyAppDB', 1);

// Set (auto-create store)
await IndexedDBManager.setAuto('users', {
  id: 'user_123',
  name: 'John Doe',
  email: 'john@example.com'
});

// Get
const user = await IndexedDBManager.getAuto('users', 'user_123');

// GetAll
const allUsers = await IndexedDBManager.getAllAuto('users');

// Delete
await IndexedDBManager.deleteAuto('users', 'user_123');
```

### **Manual Mode:**
```javascript
// Initialize dengan stores
await IndexedDBManager.init('MyAppDB', 1, ['users', 'posts']);

// Set
await IndexedDBManager.set('users', {
  id: 'user_123',
  name: 'John Doe'
});

// Get
const user = await IndexedDBManager.get('users', 'user_123');

// GetAll
const allUsers = await IndexedDBManager.getAll('users');

// Delete
await IndexedDBManager.delete('users', 'user_123');
```

---

**✅ Analisis selesai. Siap untuk perintah selanjutnya!**

