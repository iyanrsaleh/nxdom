# NexaFirebase Documentation

## Overview

NexaFirebase adalah class PHP untuk berinteraksi dengan Firebase Realtime Database menggunakan REST API. Class ini menyediakan operasi CRUD (Create, Read, Update, Delete) dan operasi batch untuk mengelola data Firebase.

## Installation & Setup

### Prerequisites

- PHP dengan ekstensi cURL
- Firebase Realtime Database URL
- Firebase API Key

### Basic Setup

```php
use App\System\Helpers\NexaFirebase;

// Method 1: Set config dalam constructor
$firebase = new NexaFirebase('https://your-project.firebaseio.com', 'your-api-key');

// Method 2: Set config setelah instantiate
$firebase = new NexaFirebase();
$firebase->setConfig('https://your-project.firebaseio.com', 'your-api-key');
```

## Configuration Methods

### setConfig($databaseURL, $apiKey)

Mengatur konfigurasi Firebase.

**Parameters:**

- `$databaseURL` (string): URL Firebase Realtime Database
- `$apiKey` (string): Firebase API Key

**Returns:** `$this` (untuk method chaining)

```php
$firebase->setConfig('https://your-project.firebaseio.com', 'your-api-key');
```

### getConfig()

Mendapatkan konfigurasi saat ini.

**Returns:** Array dengan `databaseURL` dan `apiKey`

```php
$config = $firebase->getConfig();
echo $config['databaseURL']; // https://your-project.firebaseio.com
echo $config['apiKey']; // your-api-key
```

## CRUD Operations

### Create Operations

#### create($path, $data)

Membuat data baru dengan auto-generated key.

**Parameters:**

- `$path` (string): Path di Firebase (contoh: 'users', 'posts/comments')
- `$data` (array): Data yang akan disimpan

**Returns:** Array dengan status dan data

```php
$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
];

$result = $firebase->create('users', $data);

if ($result['status'] === 'success') {
    echo "User created with key: " . $result['data']['name'];
} else {
    echo "Error: " . $result['error'];
}
```

#### createKey($path, $data, $customKey)

Membuat data baru dengan custom key.

**Parameters:**

- `$path` (string): Path di Firebase
- `$data` (array): Data yang akan disimpan
- `$customKey` (string): Custom key untuk data

**Returns:** Array dengan status, key, dan data

```php
$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com'
];

$result = $firebase->createKey('users', $data, 'user_001');

if ($result['status'] === 'success') {
    echo "User created with custom key: " . $result['key'];
}
```

### Read Operations

#### read($path, $filter = null)

Membaca data dari Firebase.

**Parameters:**

- `$path` (string): Path di Firebase
- `$filter` (array, optional): Filter data dengan format `['field' => 'field_name', 'value' => 'field_value']`

**Returns:** Array dengan status dan data

```php
// Membaca semua users
$result = $firebase->read('users');

// Membaca dengan filter
$filter = ['field' => 'age', 'value' => '30'];
$result = $firebase->read('users', $filter);

if ($result['status'] === 'success') {
    $users = $result['data'];
    foreach ($users as $key => $user) {
        echo $user['name'] . "\n";
    }
}
```

#### readAdvanced($path, $options = [])

Membaca data dengan opsi filtering yang lebih advanced.

**Parameters:**

- `$path` (string): Path di Firebase
- `$options` (array): Opsi filtering

**Available Options:**

- `orderBy`: Field untuk ordering
- `equalTo`: Nilai yang harus sama
- `startAt`: Nilai minimum
- `endAt`: Nilai maksimum
- `limitToFirst`: Batasi N hasil pertama
- `limitToLast`: Batasi N hasil terakhir
- `print`: 'pretty' untuk format yang rapi
- `shallow`: true untuk mendapat keys saja

```php
// Ambil 5 user pertama berdasarkan umur
$options = [
    'orderBy' => 'age',
    'limitToFirst' => 5
];
$result = $firebase->readAdvanced('users', $options);

// Ambil users dengan umur 25-35
$options = [
    'orderBy' => 'age',
    'startAt' => '25',
    'endAt' => '35'
];
$result = $firebase->readAdvanced('users', $options);

// Ambil keys saja
$options = ['shallow' => true];
$result = $firebase->readAdvanced('users', $options);
```

### Update Operations

#### update($path, $key, $data)

Mengupdate data yang sudah ada.

**Parameters:**

- `$path` (string): Path di Firebase
- `$key` (string): Key dari data yang akan diupdate
- `$data` (array): Data baru

**Returns:** Array dengan status dan data

```php
$updateData = [
    'name' => 'John Smith', // Update name
    'age' => 31             // Update age
];

$result = $firebase->update('users', 'user_001', $updateData);

if ($result['status'] === 'success') {
    echo "User updated successfully";
}
```

### Delete Operations

#### delete($path, $key)

Menghapus data.

**Parameters:**

- `$path` (string): Path di Firebase
- `$key` (string): Key dari data yang akan dihapus

**Returns:** Array dengan status dan message

```php
$result = $firebase->delete('users', 'user_001');

if ($result['status'] === 'success') {
    echo $result['message']; // Data berhasil dihapus
}
```

## Batch Operations

### batchCreate($path, $dataArray)

Membuat multiple data sekaligus.

**Parameters:**

- `$path` (string): Path di Firebase
- `$dataArray` (array): Array berisi data-data yang akan dibuat

```php
$users = [
    ['name' => 'User 1', 'email' => 'user1@example.com'],
    ['name' => 'User 2', 'email' => 'user2@example.com'],
    ['name' => 'User 3', 'email' => 'user3@example.com']
];

$result = $firebase->batchCreate('users', $users);

echo "Total: " . $result['total'];
echo "Success: " . $result['success_count'];
echo "Errors: " . $result['error_count'];
```

### batchCreateWithKeys($path, $dataWithKeys)

Membuat multiple data dengan custom keys.

**Parameters:**

- `$path` (string): Path di Firebase
- `$dataWithKeys` (array): Array dengan format `[key => data]`

```php
$usersWithKeys = [
    'user_001' => ['name' => 'User 1', 'email' => 'user1@example.com'],
    'user_002' => ['name' => 'User 2', 'email' => 'user2@example.com'],
    'user_003' => ['name' => 'User 3', 'email' => 'user3@example.com']
];

$result = $firebase->batchCreateWithKeys('users', $usersWithKeys);
```

### batchUpdate($path, $updates)

Mengupdate multiple data sekaligus.

**Parameters:**

- `$path` (string): Path di Firebase
- `$updates` (array): Array dengan format `[key => data]`

```php
$updates = [
    'user_001' => ['age' => 31],
    'user_002' => ['age' => 25],
    'user_003' => ['age' => 28]
];

$result = $firebase->batchUpdate('users', $updates);
```

### batchDelete($path, $keys)

Menghapus multiple data sekaligus.

**Parameters:**

- `$path` (string): Path di Firebase
- `$keys` (array): Array berisi keys yang akan dihapus

```php
$keysToDelete = ['user_001', 'user_002', 'user_003'];

$result = $firebase->batchDelete('users', $keysToDelete);
```

### multiPathUpdate($updates)

Mengupdate multiple paths dalam satu operasi.

**Parameters:**

- `$updates` (array): Array dengan format `[path => data]`

```php
$updates = [
    'users/user_001/age' => 31,
    'users/user_001/last_login' => time(),
    'posts/post_001/views' => 150,
    'analytics/total_users' => 1000
];

$result = $firebase->multiPathUpdate($updates);
```

## Error Handling

Semua method mengembalikan array dengan struktur yang konsisten:

### Success Response

```php
[
    'status' => 'success',
    'data' => [...] // Data hasil operasi
]
```

### Error Response

```php
[
    'error' => 'Error message'
]
```

### Batch Operation Response

```php
[
    'status' => 'success',
    'total' => 10,
    'success_count' => 8,
    'error_count' => 2,
    'results' => [...], // Data yang berhasil
    'errors' => [...]   // Data yang error
]
```

## Best Practices

1. **Selalu cek konfigurasi** sebelum menggunakan operasi apapun
2. **Handle error** dengan mengecek `isset($result['error'])`
3. **Gunakan batch operations** untuk operasi multiple data
4. **Validasi input** sebelum mengirim ke Firebase
5. **Gunakan custom keys** yang meaningful untuk data penting

## Examples

### Complete CRUD Example

```php
$firebase = new NexaFirebase();
$firebase->setConfig('https://your-project.firebaseio.com', 'your-api-key');

// Create
$userData = ['name' => 'John Doe', 'email' => 'john@example.com'];
$createResult = $firebase->create('users', $userData);
$userKey = $createResult['data']['name'];

// Read
$readResult = $firebase->read('users/' . $userKey);
$user = $readResult['data'];

// Update
$updateData = ['age' => 30];
$updateResult = $firebase->update('users', $userKey, $updateData);

// Delete
$deleteResult = $firebase->delete('users', $userKey);
```

### Advanced Filtering Example

```php
// Ambil 10 user dengan umur >= 25, diurutkan berdasarkan nama
$options = [
    'orderBy' => 'name',
    'startAt' => 'A',
    'limitToFirst' => 10
];

$result = $firebase->readAdvanced('users', $options);

// Filter users berdasarkan status
$options = [
    'orderBy' => 'status',
    'equalTo' => 'active'
];

$activeUsers = $firebase->readAdvanced('users', $options);
```

## Error Types

1. **Configuration Error**: Firebase config tidak diset
2. **cURL Error**: Masalah koneksi network
3. **HTTP Error**: Error dari Firebase API (400+)
4. **Validation Error**: Input data tidak valid
5. **Exception**: Error PHP internal

## Notes

- Class ini menggunakan Firebase REST API
- Semua data dikirim dalam format JSON
- Timeout default untuk request adalah 30 detik
- Method `executeCurl` private menangani semua HTTP requests
- Validasi konfigurasi dilakukan sebelum setiap operasi
- SSL verification dinonaktifkan untuk development (CURLOPT_SSL_VERIFYPEER = false)

## SSL Configuration

Untuk environment development, class ini menggunakan pengaturan SSL yang permissive:

- `CURLOPT_SSL_VERIFYPEER = false`: Menonaktifkan verifikasi SSL peer
- `CURLOPT_SSL_VERIFYHOST = false`: Menonaktifkan verifikasi SSL host
- `CURLOPT_FOLLOWLOCATION = true`: Mengikuti redirect automatik
- `CURLOPT_MAXREDIRS = 10`: Maksimal 10 redirect

**⚠️ WARNING:** Pengaturan ini tidak aman untuk production. Untuk production environment, Anda harus:

1. Mengaktifkan SSL verification
2. Menggunakan CA certificate bundle yang valid
3. Memastikan server memiliki sertifikat SSL yang valid
