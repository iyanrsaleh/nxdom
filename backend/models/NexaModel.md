# NexaModel - Dokumentasi Lengkap

## Daftar Isi

1. [Pengantar](#pengantar)
2. [Instalasi dan Konfigurasi](#instalasi-dan-konfigurasi)
3. [Fitur Utama](#fitur-utama)
4. [Penggunaan Dasar](#penggunaan-dasar)
5. [Query Builder](#query-builder)
6. [Field Exclusion (noSelect)](#field-exclusion-noselect)
7. [Record Exclusion (noSelectFields)](#record-exclusion-noselectfields)
8. [Operasi CRUD](#operasi-crud)
9. [Aggregation dan Statistik](#aggregation-dan-statistik)
10. [Pagination](#pagination)
11. [Cache (kompatibilitas API)](#cache-kompatibilitas-api)
12. [Security Features](#security-features)
13. [Performance Monitoring](#performance-monitoring)
14. [Database Management](#database-management)
15. [Advanced Features](#advanced-features)
16. [Examples](#examples)
17. [Best Practices](#best-practices)
18. [API Reference](#api-reference)

## Pengantar

NexaModel adalah ORM (Object-Relational Mapping) untuk framework Nexa, dengan fokus keamanan, performa query, dan API yang mudah dipakai. Untuk metadata multi-tabel (misalnya menu dinamis), gunakan method seperti `showTablesRet()` pada instance `NexaModel` — mapping ke UI (label, icon, action) tetap di lapisan aplikasi atau JavaScript Anda.

### Fitur Utama

- **Query Builder** yang aman dan ekspresif
- **Security First** dengan validasi input dan proteksi SQL injection
- **Performance Monitoring** dan debugging tools (statistik query, health check)
- **Aggregation Functions** untuk analisis data
- **Pagination** yang fleksibel
- **Database Management** tools
- **Transaction Support**
- **Raw Query** dengan binding yang aman

## Instalasi dan Konfigurasi

### Konfigurasi Database

Pastikan file `.env` atau konfigurasi database sudah diatur:

```env
DB_HOST=localhost
DB_USERNAME=root
DB_PASSWORD=your_password
DB_DATABASE=your_database
DB_PORT=3306
DB_CHARSET=utf8mb4
```

### Membuat Model

```php
<?php
namespace App\Models;

use App\System\NexaModel;

class User extends NexaModel
{
    protected $table = 'users';
    protected $fillable = ['name', 'email', 'password'];

    // Custom methods here
}
```

## Fitur Utama

### 1. Security Features

- **Input Validation**: Semua input divalidasi untuk mencegah SQL injection
- **Column Name Validation**: Validasi nama kolom dan fungsi SQL
- **Function Whitelist**: Hanya fungsi SQL yang diizinkan yang dapat digunakan
- **Prepared Statements**: Semua query menggunakan prepared statements

### 2. Performance Features

- **Performance Monitoring**: Tracking waktu eksekusi query (`getPerformanceStats`, `benchmarkQuery`)
- **Koneksi database**: `NexaDb` menyediakan akses koneksi PDO terpusat (bukan cache query di level model)
- **Query yang efisien**: Pilih kolom (`select`), pagination, dan limit sesuai kebutuhan

### 3. Developer Experience

- **Fluent Interface**: Chainable methods untuk kemudahan penggunaan
- **Debug Tools**: Tools untuk debugging dan monitoring
- **Error Handling**: Error handling yang comprehensive
- **Logging**: Logging otomatis untuk audit dan debugging

## Penggunaan Dasar

### Inisialisasi

```php
$model = new NexaModel();
```

### Memilih Tabel

```php

$this->Storage('users');
```

### Query Sederhana

```php
// Mengambil semua data
$users = $this->Storage('users')->get();

// Mengambil data pertama
$user = $this->Storage('users')->first();

// Mengambil data berdasarkan ID
$user = $this->Storage('users')->where('id', 1)->first();
```

## Query Builder

### SELECT Operations

#### Basic Select

```php
// Select semua kolom
$users = $this->Storage('users')->get();

// Select kolom tertentu
$users = $this->Storage('users')
    ->select(['id', 'name', 'email'])
    ->get();

// Select dengan alias
$users = $this->Storage('users')
    ->select(['name AS full_name', 'email AS user_email'])
    ->get();
```

#### Select dengan Fungsi

```php
// Menggunakan fungsi SQL
$users = $this->Storage('users')
    ->select([
        'UPPER(name) AS name_upper',
        'COUNT(*) AS total_users',
        'DATE_FORMAT(created_at, "%Y-%m") AS month'
    ])
    ->get();
```

### WHERE Conditions

#### Basic Where

```php
// Where sederhana
$users = $this->Storage('users')
    ->where('status', 'active')
    ->get();

// Where dengan operator
$users = $this->Storage('users')
    ->where('age', '>', 18)
    ->get();

// Multiple where conditions
$users = $this->Storage('users')
    ->where('status', 'active')
    ->where('age', '>', 18)
    ->get();
```

#### Advanced Where

```php
// OR Where
$users = $this->Storage('users')
    ->where('status', 'active')
    ->orWhere('role', 'admin')
    ->get();

// Where In
$users = $this->Storage('users')
    ->whereIn('id', [1, 2, 3, 4, 5])
    ->get();

// Where Not In
$users = $this->Storage('users')
    ->whereNotIn('status', ['banned', 'deleted'])
    ->get();

// Where Between
$users = $this->Storage('users')
    ->whereBetween('age', [18, 65])
    ->get();

// Where Not Between
$users = $this->Storage('users')
    ->whereNotBetween('age', [13, 17])
    ->get();

// Where Null
$users = $this->Storage('users')
    ->whereNull('deleted_at')
    ->get();

// Where Not Null
$users = $this->Storage('users')
    ->whereNotNull('email_verified_at')
    ->get();
```

#### Date-based Where

```php
// Where Date
$users = $this->Storage('users')
    ->whereDate('created_at', '2024-01-01')
    ->get();

// Where Year
$users = $this->Storage('users')
    ->whereYear('created_at', 2024)
    ->get();

// Where Month
$users = $this->Storage('users')
    ->whereMonth('created_at', 1)
    ->get();

// Where Day
$users = $this->Storage('users')
    ->whereDay('created_at', 15)
    ->get();
```

### JOIN Operations

#### Inner Join

```php
$users = $this->Storage('users')
    ->join('profiles', 'users.id', '=', 'profiles.user_id')
    ->select(['users.*', 'profiles.bio'])
    ->get();
```

#### Left Join

```php
$users = $this->Storage('users')
    ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
    ->select(['users.*', 'COUNT(orders.id) AS order_count'])
    ->groupBy('users.id')
    ->get();
```

### ORDER BY, GROUP BY, HAVING

#### Order By

```php
// Order by sederhana
$users = $this->Storage('users')
    ->orderBy('name', 'ASC')
    ->get();

// Multiple order by
$users = $this->Storage('users')
    ->orderBy('status', 'DESC')
    ->orderBy('name', 'ASC')
    ->get();

// Random order
$users = $this->Storage('users')
    ->inRandomOrder()
    ->get();
```

#### Group By

```php
$stats = $this->Storage('users')
    ->select(['status', 'COUNT(*) AS count'])
    ->groupBy('status')
    ->get();
```

#### Having

```php
$stats = $this->Storage('users')
    ->select(['status', 'COUNT(*) AS count'])
    ->groupBy('status')
    ->having('count', '>', 5)
    ->get();
```

### LIMIT dan OFFSET

```php
// Limit
$users = $this->Storage('users')
    ->limit(10)
    ->get();

// Limit dengan offset
$users = $this->Storage('users')
    ->limit(10)
    ->offset(20)
    ->get();

// Take dan Skip (alias)
$users = $this->Storage('users')
    ->take(10)
    ->skip(20)
    ->get();
```

## Field Exclusion (noSelect)

NexaModel menyediakan method untuk mengecualikan kolom tertentu dari query SELECT, memberikan fleksibilitas dalam memilih data yang diambil dari database.

### Basic Field Exclusion

#### `noSelect()` Method

```php
// Mengecualikan kolom password dan remember_token
$users = $this->Storage('users')
    ->noSelect(['password', 'remember_token'])
    ->get();

// Menggunakan string (comma-separated)
$users = $this->Storage('users')
    ->noSelect('password,remember_token,api_key')
    ->get();
```

#### Method Aliases

```php
// Semua method ini berfungsi sama dengan noSelect()
$users = $this->Storage('users')->except(['password', 'remember_token'])->get();
$users = $this->Storage('users')->exclude(['password', 'remember_token'])->get();
$users = $this->Storage('users')->without(['password', 'remember_token'])->get();
$users = $this->Storage('users')->ignore(['password', 'remember_token'])->get();
$users = $this->Storage('users')->skipFields(['password', 'remember_token'])->get();
```

### Manual Column List Methods

#### `exceptFrom()`, `noSelectFrom()`, `withoutFrom()`

Untuk situasi ketika Anda sudah mengetahui daftar kolom yang tersedia:

```php
// Daftar kolom manual
$allColumns = ['id', 'name', 'email', 'password', 'remember_token', 'created_at', 'updated_at'];

// Mengecualikan kolom tertentu dari daftar manual
$users = $this->Storage('users')
    ->exceptFrom(['password', 'remember_token'], $allColumns)
    ->get();

// Method alias lainnya
$users = $this->Storage('users')->noSelectFrom(['password'], $allColumns)->get();
$users = $this->Storage('users')->withoutFrom(['password'], $allColumns)->get();
```

### Shortcut Methods

#### Predefined Exclusions

```php
// Tidak memilih field password
$users = $this->Storage('users')->noPassword()->get();

// Tidak memilih field timestamps (created_at, updated_at, deleted_at)
$users = $this->Storage('users')->noTimestamps()->get();

// Tidak memilih field sensitif (password, tokens, keys, dll.)
$users = $this->Storage('users')->noSensitive()->get();

// Tidak memilih field token (remember_token, api_token, dll.)
$users = $this->Storage('users')->noTokens()->get();

// Tidak memilih field ID
$users = $this->Storage('users')->noId()->get();

// Tidak memilih field sistem (id + timestamps)
$users = $this->Storage('users')->noSystem()->get();
```

#### Sensitive Fields Categories

```php
// noSensitive() mengecualikan field-field ini:
$sensitiveFields = [
    'password', 'password_hash', 'remember_token', 'api_token', 'api_key',
    'secret', 'secret_key', 'access_token', 'refresh_token', 'private_key', 'salt'
];

// noTimestamps() mengecualikan field-field ini:
$timestampFields = ['created_at', 'updated_at', 'deleted_at'];

// noTokens() mengecualikan field-field ini:
$tokenFields = [
    'remember_token', 'api_token', 'access_token', 'refresh_token',
    'api_key', 'secret_key'
];

// noSystem() mengecualikan field-field ini:
$systemFields = ['id', 'created_at', 'updated_at', 'deleted_at'];
```

#### Advanced Sensitive Fields

```php
// Mengecualikan field sensitif dengan tambahan field custom
$users = $this->Storage('users')
    ->exceptSensitive(['phone', 'address'])
    ->get();

// Mengecualikan field timestamp dengan tambahan field custom
$users = $this->Storage('users')
    ->exceptTimestamps(['slug', 'meta_data'])
    ->get();

// Mengecualikan field sistem dengan tambahan field custom
$users = $this->Storage('users')
    ->exceptSystemFields(['internal_notes'])
    ->get();
```

### Advanced Field Selection

#### `selectPublicFields()`

Memilih hanya field publik yang aman untuk ditampilkan:

```php
// Memilih field publik dengan tambahan field
$users = $this->Storage('users')
    ->selectPublicFields(['status', 'role'])
    ->get();

// Field publik default: id, name, email, status, created_at
```

#### `selectMinimal()`

Memilih field minimal untuk listing atau API:

```php
// Field minimal default (id, name, email)
$users = $this->Storage('users')
    ->selectMinimal()
    ->get();

// Field minimal custom dengan field tambahan
$users = $this->Storage('users')
    ->selectMinimal(['id', 'username', 'email'], ['status', 'last_login'])
    ->get();
```

### Chaining with Other Methods

```php
// Kombinasi dengan method lain
$users = $this->Storage('users')
    ->noSensitive()
    ->where('status', 'active')
    ->orderBy('name')
    ->limit(50)
    ->get();

// Dengan join
$users = $this->Storage('users')
    ->noPassword()
    ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
    ->select(['users.*', 'profiles.bio'])
    ->get();

// Dengan aggregation
$stats = $this->Storage('users')
    ->noSensitive()
    ->countByGroup('status');
```

### Real World Examples

#### API Endpoint (Safe Data)

```php
public function getUsersForAPI()
{
    return $this->Storage('users')
        ->noSensitive()
        ->where('status', 'active')
        ->selectMinimal(['id', 'name', 'email'], ['avatar', 'status'])
        ->paginate(1, 20);
}
```

#### Admin Dashboard (No Sensitive Data)

```php
public function getUsersForAdmin()
{
    return $this->Storage('users')
        ->noPassword()
        ->noTokens()
        ->orderBy('created_at', 'DESC')
        ->get();
}
```

#### Public Profile Display

```php
public function getPublicProfile($userId)
{
    return $this->Storage('users')
        ->selectPublicFields(['bio', 'website'])
        ->where('id', $userId)
        ->first();
}
```

## Record Exclusion (noSelectFields)

NexaModel menyediakan method untuk mengecualikan record/baris tertentu dari hasil query berdasarkan kondisi field, memberikan kontrol yang lebih granular dalam filtering data.

### Basic Record Exclusion

#### `noSelectFields()` Method

```php
// Tidak menampilkan record dengan account_name = 'Biaya Gaji'
$accounts = $this->Storage('accounts')
    ->noSelectFields(['account_name' => 'Biaya Gaji'])
    ->get();

// Multiple conditions (AND logic)
$accounts = $this->Storage('accounts')
    ->noSelectFields([
        'account_name' => 'Biaya Gaji',
        'status' => 'inactive'
    ])
    ->get();

// Menggunakan array values (NOT IN)
$accounts = $this->Storage('accounts')
    ->noSelectFields([
        'account_name' => ['Biaya Gaji', 'Biaya Internet', 'Biaya Air']
    ])
    ->get();

// ❌ SALAH - Key yang sama akan ditimpa
$users = $this->Storage('users')
    ->noSelectFields([
        'id' => 10,
        'id' => 40  // Ini akan menimpa nilai 10
    ])
    ->get();

// ✅ BENAR - Gunakan array untuk multiple values
$users = $this->Storage('users')
    ->noSelectFields([
        'id' => [10, 40]  // NOT IN (10, 40)
    ])
    ->get();

// ✅ BENAR - Chain multiple calls
$users = $this->Storage('users')
    ->noSelectFields(['id' => 10])
    ->noSelectFields(['id' => 40])
    ->get();

// Null value handling
$accounts = $this->Storage('accounts')
    ->noSelectFields(['deleted_at' => null]) // WHERE deleted_at IS NOT NULL
    ->get();
```

### Method Aliases

```php
// Semua method ini berfungsi sama dengan noSelectFields()

// Menyembunyikan record
$accounts = $this->Storage('accounts')
    ->hideRecords(['account_name' => 'Biaya Gaji'])
    ->get();

// Mengecualikan record
$accounts = $this->Storage('accounts')
    ->excludeRecords(['status' => 'banned'])
    ->get();

// Melewati record
$accounts = $this->Storage('accounts')
    ->skipRecords(['account_name' => 'Biaya Gaji'])
    ->get();

// Tanpa record tertentu
$accounts = $this->Storage('accounts')
    ->withoutRecords(['status' => 'deleted'])
    ->get();

// Filter keluar record
$accounts = $this->Storage('accounts')
    ->filterOut(['account_name' => 'Biaya Gaji'])
    ->get();
```

### Shortcut Methods for Common Scenarios

#### Status-based Filtering

```php
// Hanya record aktif (is_active = 1)
$users = $this->Storage('users')
    ->onlyActive()
    ->get();

// Custom status field
$products = $this->Storage('products')
    ->onlyActive('published')
    ->get();

// Hanya record tidak aktif
$users = $this->Storage('users')
    ->onlyInactive()
    ->get();
```

#### Soft Delete Handling

```php
// Tidak termasuk yang dihapus (soft delete)
$posts = $this->Storage('posts')
    ->notDeleted()
    ->get();

// Custom deleted field
$documents = $this->Storage('documents')
    ->notDeleted('archived_at')
    ->get();

// Hanya yang dihapus
$deletedPosts = $this->Storage('posts')
    ->onlyDeleted()
    ->get();
```

### Advanced Filtering Combinations

#### Complex Conditions

```php
// Kombinasi multiple exclusions
$transactions = $this->Storage('transactions')
    ->noSelectFields([
        'status' => ['cancelled', 'failed'],
        'amount' => 0,
        'type' => 'test'
    ])
    ->onlyActive()
    ->notDeleted()
    ->get();
```

#### Chaining with Regular Where Clauses

```php
// Kombinasi noSelectFields dengan where biasa
$orders = $this->Storage('orders')
    ->noSelectFields(['status' => 'cancelled'])  // Exclude cancelled orders
    ->where('created_at', '>=', '2024-01-01')    // From this year
    ->where('total_amount', '>', 0)              // With amount > 0
    ->orderBy('created_at', 'DESC')
    ->get();
```

### Real World Examples

#### Financial Dashboard (Hide Sensitive Accounts)

```php
public function getAccountsForDashboard()
{
    $sensitiveAccounts = ['Biaya Gaji', 'Biaya Rahasia', 'Dana Darurat'];

    return $this->Storage('accounts')
        ->noSelectFields(['account_name' => $sensitiveAccounts])
        ->onlyActive()
        ->orderBy('budget', 'DESC')
        ->get();
}
```

#### Public Blog Posts (Hide Drafts and Private)

```php
public function getPublicPosts()
{
    return $this->Storage('posts')
        ->noSelectFields([
            'status' => ['draft', 'private'],
            'published' => 0
        ])
        ->notDeleted()
        ->orderBy('published_at', 'DESC')
        ->get();
}
```

#### User Management (Hide System and Banned Users)

```php
public function getRegularUsers()
{
    return $this->Storage('users')
        ->noSelectFields([
            'role' => ['system', 'bot'],
            'status' => ['banned', 'suspended']
        ])
        ->onlyActive()
        ->noSensitive()
        ->orderBy('name')
        ->get();
}
```

#### Product Catalog (Hide Out of Stock and Discontinued)

```php
public function getAvailableProducts()
{
    return $this->Storage('products')
        ->noSelectFields([
            'status' => ['discontinued', 'out_of_stock'],
            'stock_quantity' => 0,
            'is_hidden' => 1
        ])
        ->onlyActive()
        ->orderBy('name')
        ->get();
}
```

### SQL Output Examples

```php
// noSelectFields example
$this->Storage('accounts')
    ->noSelectFields(['account_name' => 'Biaya Gaji'])
    ->toSql();
// Result: "SELECT * FROM accounts WHERE account_name != ?"

// Multiple conditions
$this->Storage('accounts')
    ->noSelectFields([
        'account_name' => 'Biaya Gaji',
        'status' => 'inactive'
    ])
    ->toSql();
// Result: "SELECT * FROM accounts WHERE account_name != ? AND status != ?"

// Array values (NOT IN)
$this->Storage('accounts')
    ->noSelectFields(['account_name' => ['Biaya Gaji', 'Biaya Internet']])
    ->toSql();
// Result: "SELECT * FROM accounts WHERE account_name NOT IN (?, ?)"

// ✅ BENAR - Multiple IDs menggunakan array
$this->Storage('users')
    ->noSelectFields(['id' => [10, 40, 50]])
    ->toSql();
// Result: "SELECT * FROM users WHERE id NOT IN (?, ?, ?)"

// ✅ BENAR - Chain multiple calls
$this->Storage('users')
    ->noSelectFields(['id' => 10])
    ->noSelectFields(['status' => 'banned'])
    ->toSql();
// Result: "SELECT * FROM users WHERE id != ? AND status != ?"

// Null handling
$this->Storage('accounts')
    ->noSelectFields(['deleted_at' => null])
    ->toSql();
// Result: "SELECT * FROM accounts WHERE deleted_at IS NOT NULL"

// ❌ PERHATIAN - Key yang sama tidak bisa digunakan berulang
$this->Storage('users')
    ->noSelectFields([
        'id' => 10,
        'id' => 40  // Nilai 10 akan ditimpa oleh 40
    ])
    ->toSql();
// Result: "SELECT * FROM users WHERE id != ?" (hanya mengecualikan id = 40)
```

### Combination Examples

#### Field Exclusion + Record Exclusion

```php
// Kombinasi noSelect (exclude columns) dan noSelectFields (exclude records)
$safeAccounts = $this->Storage('accounts')
    ->noSensitive()                                    // Hide sensitive columns
    ->noSelectFields(['account_name' => 'Biaya Gaji']) // Hide specific records
    ->onlyActive()                                     // Only active records
    ->orderBy('account_name')
    ->get();
```

#### Complex Business Logic

```php
public function getFinancialReport($excludeSalary = true, $includeInactive = false)
{
    $query = $this->Storage('accounts')
        ->noTimestamps(); // Hide timestamp columns

    if ($excludeSalary) {
        $query->noSelectFields([
            'account_name' => ['Biaya Gaji', 'Biaya Operasional', 'Tunjangan']
        ]);
    }

    if (!$includeInactive) {
        $query->onlyActive();
    }

    return $query->orderBy('budget', 'DESC')->get();
}
```

#### API Response Filtering

```php
public function getFilteredAPIResponse($filters = [])
{
    $query = $this->Storage('users')
        ->noSensitive()  // Hide sensitive columns
        ->notDeleted();  // Hide deleted records

    // Dynamic field exclusions based on user permissions
    if (isset($filters['hide_records'])) {
        $query->noSelectFields($filters['hide_records']);
    }

    // Dynamic column exclusions
    if (isset($filters['hide_columns'])) {
        $query->noSelect($filters['hide_columns']);
    }

    return $query->paginate($filters['page'] ?? 1, $filters['limit'] ?? 20);
}

// Usage:
$response = $this->getFilteredAPIResponse([
    'hide_records' => ['status' => ['banned', 'inactive']],
    'hide_columns' => ['email', 'phone'],
    'page' => 1,
    'limit' => 50
]);
```

### Performance Considerations

```php
// Good: Index pada kolom yang sering digunakan untuk exclusion
// CREATE INDEX idx_accounts_name ON accounts(account_name);
// CREATE INDEX idx_users_status ON users(status);

// Efficient exclusion dengan indexed columns
$accounts = $this->Storage('accounts')
    ->noSelectFields(['status' => 'inactive'])  // Uses index
    ->orderBy('created_at', 'DESC')
    ->get();

// Combine with other optimizations
$users = $this->Storage('users')
    ->selectMinimal()                           // Less data transfer
    ->noSelectFields(['role' => 'system'])      // Indexed exclusion
    ->onlyActive()                             // Indexed filtering
    ->limit(100)                               // Limit results
    ->get();
```

## Operasi CRUD

### Create (Insert)

#### Insert Single Record

```php
$userId = $this->Storage('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT),
    'created_at' => date('Y-m-d H:i:s')
]);

echo "User created with ID: " . $userId;
```

#### Insert Multiple Records

```php
$result = $this->Storage('users')->insertMany([
    [
        'name' => 'User 1',
        'email' => 'user1@example.com',
        'password' => password_hash('secret1', PASSWORD_DEFAULT)
    ],
    [
        'name' => 'User 2',
        'email' => 'user2@example.com',
        'password' => password_hash('secret2', PASSWORD_DEFAULT)
    ]
]);

echo "Inserted " . $result . " records";
```

#### Upsert (Insert or Update)

```php
$result = $this->Storage('users')->upsert([
    'email' => 'john@example.com',
    'name' => 'John Doe Updated',
    'updated_at' => date('Y-m-d H:i:s')
], ['name', 'updated_at']); // Kolom yang akan diupdate jika record sudah ada
```

### Read (Select)

#### Get All Records

```php
$users = $this->Storage('users')->get();
```

#### Get First Record

```php
$user = $this->Storage('users')
    ->where('email', 'john@example.com')
    ->first();
```

#### Get Last Record

```php
$user = $this->Storage('users')
    ->orderBy('id', 'DESC')
    ->first();
// atau
$user = $this->Storage('users')->last();
```

#### Get Specific Value

```php
$name = $this->Storage('users')
    ->where('id', 1)
    ->value('name');
```

#### Pluck Values

```php
// Get array of names
$names = $this->Storage('users')->pluck('name');

// Get associative array (key => value)
$users = $this->Storage('users')->pluck('name', 'id');
```

### Update

#### Update Records

```php
$affected = $this->Storage('users')
    ->where('id', 1)
    ->update([
        'name' => 'Updated Name',
        'updated_at' => date('Y-m-d H:i:s')
    ]);

echo "Updated " . $affected . " records";
```

#### Update Multiple Records

```php
$affected = $this->Storage('users')
    ->where('status', 'inactive')
    ->update([
        'status' => 'active',
        'updated_at' => date('Y-m-d H:i:s')
    ]);
```

#### Increment/Decrement

```php
// Increment
$this->Storage('users')
    ->where('id', 1)
    ->increment('login_count');

// Increment dengan nilai custom
$this->Storage('users')
    ->where('id', 1)
    ->increment('points', 50);

// Increment dengan data tambahan
$this->Storage('users')
    ->where('id', 1)
    ->increment('login_count', 1, [
        'last_login' => date('Y-m-d H:i:s')
    ]);

// Decrement
$this->Storage('users')
    ->where('id', 1)
    ->decrement('points', 10);
```

### Delete

#### Delete Records

```php
$affected = $this->Storage('users')
    ->where('status', 'banned')
    ->delete();

echo "Deleted " . $affected . " records";
```

#### Force Delete

```php
$affected = $this->Storage('users')
    ->where('id', 1)
    ->forceDelete();
```

## Aggregation dan Statistik

### Basic Aggregation

#### Count

```php
// Count semua records
$total = $this->Storage('users')->count();

// Count dengan kondisi
$activeUsers = $this->Storage('users')
    ->where('status', 'active')
    ->count();

// Count kolom tertentu
$emailCount = $this->Storage('users')->count('email');
```

#### Sum

```php
// Sum kolom
$totalPoints = $this->Storage('users')->sum('points');

// Sum dengan kondisi
$activeUserPoints = $this->Storage('users')
    ->where('status', 'active')
    ->sum('points');
```

#### Average

```php
$averageAge = $this->Storage('users')->avg('age');
```

#### Min/Max

```php
$minAge = $this->Storage('users')->min('age');
$maxAge = $this->Storage('users')->max('age');
```

### Advanced Aggregation

#### Count by Conditions

```php
$stats = $this->Storage('users')
    ->countByConditions('status', [
        'active' => ['status', '=', 'active'],
        'inactive' => ['status', '=', 'inactive'],
        'banned' => ['status', '=', 'banned']
    ]);

// Result: ['active' => 150, 'inactive' => 25, 'banned' => 5]
```

#### Count with Percentage

```php
$stats = $this->Storage('users')
    ->countWithPercentage('status', ['active', 'inactive']);

// Result:
// [
//     'active' => ['count' => 150, 'percentage' => 85.7],
//     'inactive' => ['count' => 25, 'percentage' => 14.3]
// ]
```

#### Count by Group

```php
$stats = $this->Storage('users')
    ->countByGroup('department');

// Result: ['IT' => 50, 'HR' => 20, 'Finance' => 30]
```

#### Multiple Column Aggregation

```php
$stats = $this->Storage('orders')
    ->countColumns(['status', 'payment_method']);

// Result:
// [
//     'status' => ['completed' => 100, 'pending' => 20],
//     'payment_method' => ['credit_card' => 80, 'paypal' => 40]
// ]
```

#### Sum with Percentage

```php
$stats = $this->Storage('orders')
    ->sumColumnsWithPercentage(['total_amount', 'tax_amount']);

// Result:
// [
//     'total_amount' => ['sum' => 50000, 'percentage' => 90.9],
//     'tax_amount' => ['sum' => 5000, 'percentage' => 9.1]
// ]
```

## Pagination

### Basic Pagination

```php
// Halaman 1, 10 items per halaman
$users = $this->Storage('users')->paginate(1, 10);

// Halaman 2, 15 items per halaman
$users = $this->Storage('users')->paginate(2, 15);
```

### Pagination dengan Kondisi

```php
$activeUsers = $this->Storage('users')
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->paginate(1, 20);
```

### Pagination Info

```php
$paginationInfo = $this->Storage('users')
    ->paginateInfo(1, 10);

// Result:
// [
//     'current_page' => 1,
//     'per_page' => 10,
//     'total' => 150,
//     'total_pages' => 15,
//     'has_next' => true,
//     'has_prev' => false,
//     'next_page' => 2,
//     'prev_page' => null
// ]
```

### Count Pagination

```php
$countInfo = $this->Storage('users')
    ->where('status', 'active')
    ->countPaginate(1, 10);

// Result: ['count' => 150, 'page' => 1, 'per_page' => 10]
```

## Cache (kompatibilitas API)

Query-level cache **tidak aktif** di implementasi saat ini: eksekusi selalu langsung ke database. Method berikut tetap ada agar kode lama tidak error; semuanya no-op atau mengembalikan status “cache dinonaktifkan”.

### Method yang tersedia

```php
// Tidak mengubah perilaku — tetap dieksekusi tanpa cache
$users = $this->Storage('users')->withoutCache()->get();
$users = $this->Storage('users')->withCache()->get();

// Instance NexaModel (tanpa Storage di controller)
$model = new \App\System\NexaModel();
$model->clearCache(); // mengembalikan $this, tidak ada data yang dihapus dari cache query
```

### Konfigurasi

```php
$this->Storage('users')->configure([
    'max_limit' => 5000,
    // 'cache_enabled' — diabaikan; cache query tidak digunakan
]);

$config = $this->Storage('users')->getConfig();
// 'cache_enabled' => false
```

### Statistik cache

```php
$stats = $this->Storage('users')->getCacheStats();
// Contoh isi: total_items 0, status menjelaskan bahwa cache dinonaktifkan
```

## Security Features

### Input Validation

NexaModel secara otomatis memvalidasi semua input untuk mencegah SQL injection:

```php
// Aman - input akan divalidasi
$users = $this->Storage('users')
    ->where('name', 'LIKE', '%' . $userInput . '%')
    ->get();
```

### Column Validation

Method ini ada di **NexaModel**, bukan di `NexaController`. Pakai instance model (subclass Anda, `(new NexaModel())`, atau rantai `$this->Storage('tabel')` yang mengembalikan `NexaModel`).

```php
$validationResult = (new \App\System\NexaModel())->testColumnValidation([
    'name',
    'email',
    'UPPER(name) AS name_upper',
    'COUNT(*) AS total'
]);

(new \App\System\NexaModel())->testColumnValidationAndDisplay([
    'name',
    'invalid_function()',
    'CASE WHEN status = "active" THEN 1 ELSE 0 END AS is_active'
]);
```

### Allowed Functions

NexaModel hanya mengizinkan fungsi SQL yang aman:

```php
// Fungsi yang diizinkan
$allowedFunctions = [
    'UPPER', 'LOWER', 'DATE', 'YEAR', 'MONTH', 'DAY',
    'COUNT', 'SUM', 'AVG', 'MAX', 'MIN', 'RAND',
    'CONCAT', 'SUBSTRING', 'TRIM', 'LENGTH',
    'DATE_FORMAT', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END',
    // ... dan banyak lagi
];
```

### Secret Key untuk Encoding

```php
$decoder = (new \App\System\NexaModel())->secretKey('your_secret_key_2025');
// Digunakan untuk encoding/decoding data sensitif (NexaDecode)
```

## Performance Monitoring

### Performance Stats

```php
$stats = $this->Storage('users')->getPerformanceStats();

// Isi sesuai implementasi: query_log_count, cache_stats (dari getCacheStats),
// memory_usage, peak_memory (byte)
```

### Query Benchmark

```php
// Benchmark query dengan 10 iterasi (waktu dalam ms)
$benchmark = $this->Storage('users')
    ->where('status', 'active')
    ->benchmarkQuery(10);

// Contoh kunci: iterations, avg_time_ms, min_time_ms, max_time_ms, cache_hits, cache_hit_ratio
```

### Health Check

```php
$health = $this->Storage('users')->healthCheck();

// Array berisi antara lain: database_connection (bool), cache_status (false — cache tidak dipakai),
// memory_usage, peak_memory, errors

// Display health check
$this->Storage('users')->showHealth(true); // dengan <pre> tag
```

## Database Management

### Show Tables

```php
// Dari controller: butuh instance NexaModel (nama tabel di Storage hanya untuk instance)
$tables = $this->Storage('users')->showTables();

$this->Storage('users')->showTablesFormatted(true);
```

### Table Information

```php
$tableInfo = $this->Storage('users')->getTablesInfo();

$columns = $this->Storage('users')->getTableColumns('users');

$exists = $this->Storage('users')->tableExists('users');
```

### Database Information

```php
$dbInfo = $this->getDatabaseInfo();

// Result:
// [
//     'database_name' => 'your_database',
//     'total_tables' => 15,
//     'total_size' => '50.5 MB',
//     'charset' => 'utf8mb4',
//     'collation' => 'utf8mb4_unicode_ci'
// ]

// Display formatted
$this->showDatabaseInfoFormatted(true);
```

### Database Exploration

```php
// Explore database structure
$this->exploreDatabase(true, true); // dengan <pre> dan show columns
```

## Working Days Helper Methods

NexaModel menyediakan helper methods yang powerful untuk menangani hari kerja (working days) dan rentang tanggal:

### Basic Working Days Operations

#### Get Working Days Range

```php
// Mendapatkan rentang 5 hari kerja dari tanggal tertentu
$range = $this->getWorkingDaysRange('2024-01-15', 5);
// Result: ['start' => '2024-01-15', 'end' => '2024-01-19'] (skip weekends)

// Default 5 hari kerja dari hari ini
$range = $this->getWorkingDaysRange(date('Y-m-d'));
```

#### Get Current Work Week

```php
// Mendapatkan rentang minggu kerja saat ini (Senin-Jumat)
$workWeek = $this->getCurrentWorkWeek();
// Result: ['start' => '2024-01-15', 'end' => '2024-01-19'] (Monday to Friday)

// Berdasarkan tanggal referensi tertentu
$workWeek = $this->getCurrentWorkWeek('2024-01-17');
```

#### Check Working Day

```php
// Cek apakah tanggal adalah hari kerja
$isWorkingDay = $this->isWorkingDay('2024-01-15'); // true (Monday)
$isWeekend = $this->isWorkingDay('2024-01-14'); // false (Sunday)
```

### Query Filtering with Working Days

#### Filter by Working Days Range

```php
// Filter query berdasarkan rentang hari kerja
$tasks = $this->Storage('tasks')
    ->whereWorkingDays('due_date', '2024-01-15', 5) // 5 working days from Jan 15
    ->get();

// Filter hanya untuk hari itu saja (single date)
$todayTasks = $this->Storage('tasks')
    ->whereWorkingDays('due_date', '2024-01-15', 0) // Only that specific date
    ->get();

// Default dari hari ini dengan 5 hari kerja
$weekTasks = $this->Storage('tasks')
    ->whereWorkingDays('created_at') // Default 5 days
    ->get();
```

#### Filter by Current Work Week

```php
// Filter berdasarkan minggu kerja saat ini
$weeklyTasks = $this->Storage('tasks')
    ->whereCurrentWorkWeek('created_at')
    ->get();

// Berdasarkan minggu kerja dari tanggal referensi
$specificWeekTasks = $this->Storage('tasks')
    ->whereCurrentWorkWeek('due_date', '2024-01-17')
    ->get();
```

### Working Days List and Count

#### Get Working Days List

```php
// Mendapatkan daftar hari kerja antara dua tanggal
$workingDays = $this->getWorkingDaysList('2024-01-15', '2024-01-25');
// Result: ['2024-01-15', '2024-01-16', '2024-01-17', '2024-01-18', '2024-01-19', '2024-01-22', '2024-01-23', '2024-01-24', '2024-01-25']
```

#### Count Working Days

```php
// Menghitung jumlah hari kerja antara dua tanggal
$count = $this->countWorkingDays('2024-01-15', '2024-01-25');
// Result: 9 (excluding weekends)
```

### Practical Examples

#### Project Tasks Management

```php
<?php
namespace App\Models;

use App\System\NexaModel;

class ProjectTask extends NexaModel
{
    protected $table = 'project_tasks';

    /**
     * Get tasks for current work week
     */
    public function getCurrentWeekTasks($projectId)
    {
        return $this->Storage($this->table)
            ->where('project_id', $projectId)
            ->whereCurrentWorkWeek('due_date')
            ->orderBy('due_date', 'ASC')
            ->get();
    }

    /**
     * Get tasks for next 5 working days
     */
    public function getUpcomingTasks($projectId)
    {
        return $this->Storage($this->table)
            ->where('project_id', $projectId)
            ->whereWorkingDays('due_date', date('Y-m-d'), 5)
            ->where('status', '!=', 'completed')
            ->get();
    }

    /**
     * Get overdue working day tasks
     */
    public function getOverdueTasks($projectId)
    {
        $today = date('Y-m-d');

        return $this->Storage($this->table)
            ->where('project_id', $projectId)
            ->where('due_date', '<', $today)
            ->where('status', '!=', 'completed')
            ->get();
    }

    /**
     * Schedule task for next working day
     */
    public function scheduleForNextWorkingDay($taskData)
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        // If tomorrow is weekend, find next Monday
        while (!$this->isWorkingDay($tomorrow)) {
            $tomorrow = date('Y-m-d', strtotime($tomorrow . ' +1 day'));
        }

        $taskData['due_date'] = $tomorrow;

        return $this->Storage($this->table)->insert($taskData);
    }
}
```

#### Report Generation Example

`$this->Storage()` disediakan oleh `NexaController`. Method seperti `activeTasksLogs`, `avatar`, `validatorTasksLogs`, dan `processTaskResults` **tidak** ada di base class — tambahkan sendiri di controller/trait aplikasi Anda.

```php
<?php
namespace App\Controllers\Admin\Planning;

use App\System\NexaController;

class Report extends NexaController
{
         /**
      * Get tasks for specific date or working days range
      */
     public function Tasks($id, $tanggal = null, $workingDays = 0)
     {
         // Set default date to today if not provided
         if ($tanggal === null) {
             $tanggal = date('Y-m-d');
         }

         // Get tasks from database
         // $workingDays = 0: hanya tanggal tersebut
         // $workingDays = 1+: rentang hari kerja
         $result = $this->Storage('plg_tasks')
             ->select([
                 'id',
                 'assigned_to AS userid',
                 'project_id',
                 'title',
                 'description',
                 'date',
                 'status'
             ])
             ->where('project_id', $id)
             ->whereWorkingDays('date', $tanggal, $workingDays)
             ->orderBy("id", "DESC")
             ->get();

        $result3 = array();

        // Process each task and add user information
        foreach ($result as $key => $value) {
            if ($value['status'] !== 'todo') {
                $logID = self::activeTasksLogs($value['id'], $value['status']);
                $result3[$key]['id'] = $value['id'];
                $result3[$key]['name'] = $this->avatar($value['userid'], 'nama');
                $result3[$key]['date'] = $value['date'];
                $result3[$key]['data'] = $logID;
                $result3[$key]['validator'] = self::validatorTasksLogs($logID);
            }
        }

        return $result3 ?? [];
    }

    /**
     * Get tasks for current work week (Monday to Friday)
     */
    public function TasksCurrentWeek($id, $tanggal = null)
    {
        if ($tanggal === null) {
            $tanggal = date('Y-m-d');
        }

        $result = $this->Storage('plg_tasks')
            ->select([
                'id',
                'assigned_to AS userid',
                'project_id',
                'title',
                'description',
                'date',
                'status'
            ])
            ->where('project_id', $id)
            ->whereCurrentWorkWeek('date', $tanggal)
            ->orderBy("id", "DESC")
            ->get();

        // Process results...
        return $this->processTaskResults($result);
    }
}
```

#### Employee Attendance System

```php
<?php
namespace App\Models;

use App\System\NexaModel;

class Attendance extends NexaModel
{
    protected $table = 'attendance';

    /**
     * Get attendance for current work week
     */
    public function getWeeklyAttendance($employeeId)
    {
        return $this->Storage($this->table)
            ->where('employee_id', $employeeId)
            ->whereCurrentWorkWeek('attendance_date')
            ->orderBy('attendance_date', 'ASC')
            ->get();
    }

    /**
     * Calculate working days attendance percentage
     */
    public function getAttendancePercentage($employeeId, $startDate, $endDate)
    {
        $totalWorkingDays = $this->countWorkingDays($startDate, $endDate);

        $attendedDays = $this->Storage($this->table)
            ->where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->where('status', 'present')
            ->count();

        return $totalWorkingDays > 0 ? ($attendedDays / $totalWorkingDays) * 100 : 0;
    }

    /**
     * Get missing attendance for working days
     */
    public function getMissingAttendance($employeeId, $startDate, $endDate)
    {
        $workingDays = $this->getWorkingDaysList($startDate, $endDate);

        $recordedDates = $this->Storage($this->table)
            ->where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->pluck('attendance_date');

        return array_diff($workingDays, $recordedDates);
    }
}
```

### Advanced Working Days Queries

#### Complex Reporting

```php
// Get tasks grouped by working days
$tasksByWorkingDays = $this->Storage('tasks')
    ->whereWorkingDays('created_at', '2024-01-01', 10)
    ->select([
        'DATE(created_at) as task_date',
        'COUNT(*) as task_count',
        'status'
    ])
    ->groupBy(['DATE(created_at)', 'status'])
    ->orderBy('task_date', 'ASC')
    ->get();

// Get productivity metrics for working days
$productivity = $this->Storage('tasks')
    ->whereCurrentWorkWeek('completed_at')
    ->where('status', 'completed')
    ->select([
        'assigned_to',
        'COUNT(*) as completed_tasks',
        'AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_completion_hours'
    ])
    ->groupBy('assigned_to')
    ->get();
```

#### Date Range Analysis

```php
// Analyze task distribution across working days
$analysis = $this->Storage('tasks')
    ->select([
        'DAYNAME(due_date) as day_name',
        'COUNT(*) as task_count',
        'AVG(priority_score) as avg_priority'
    ])
    ->whereWorkingDays('due_date', date('Y-m-d'), 15)
    ->groupBy('DAYNAME(due_date)')
    ->orderBy('FIELD(DAYNAME(due_date), "Monday", "Tuesday", "Wednesday", "Thursday", "Friday")')
    ->get();
```

### Migration Usage Examples

#### Planning System Migration

```php
// Update existing queries to use working days
// Before:
$tasks = $this->Storage('plg_tasks')
    ->where('project_id', $id)
    ->where('date', $tanggal)
    ->get();

// After:
$tasks = $this->Storage('plg_tasks')
    ->where('project_id', $id)
    ->whereWorkingDays('date', $tanggal, 5)
    ->get();
```

#### Bulk Operations for Working Days

```php
// Mark all tasks for current work week as reviewed
$this->Storage('tasks')
    ->whereCurrentWorkWeek('created_at')
    ->where('status', 'pending')
    ->update([
        'status' => 'reviewed',
        'reviewed_at' => date('Y-m-d H:i:s')
    ]);

// Get statistics for working days
$stats = $this->Storage('tasks')
    ->whereWorkingDays('due_date')
    ->countByConditions('status', ['pending', 'in_progress', 'completed']);
```

### Best Practices for Working Days

#### 1. **Consistent Date Handling**

```php
// Always use Y-m-d format for date parameters
$date = date('Y-m-d'); // Good
$date = '2024-01-15';  // Good
$date = '15/01/2024';  // Avoid - may cause issues
```

#### 2. **Performance Optimization**

```php
// Use indexes on date columns for better performance
// CREATE INDEX idx_tasks_date ON tasks(date);

// Prefer working days methods over manual date calculations
$tasks = $this->Storage('tasks')
    ->whereWorkingDays('date', $startDate, 5) // Good

// Instead of:
// ->where('date', '>=', $startDate)
// ->where('date', '<=', $endDate)
// ->whereNotIn('DAYOFWEEK(date)', [1, 7]) // Manual weekend exclusion
```

#### 3. **Error Handling**

```php
public function getWorkingDayTasks($date, $days = 5)
{
    try {
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('Invalid date format. Use Y-m-d');
        }

        return $this->Storage('tasks')
            ->whereWorkingDays('due_date', $date, $days)
            ->get();

    } catch (\Exception $e) {
        error_log('Working days query failed: ' . $e->getMessage());
        return [];
    }
}
```

## Advanced Features

### Generic Record Operations

NexaModel menyediakan method-method generic untuk operasi CRUD yang lebih sederhana:

#### Insert Record

```php
// Insert record dengan auto timestamps
$userId = $this->insertRecord('users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT)
]);

// created_at dan updated_at akan otomatis ditambahkan
echo "User created with ID: " . $userId;
```

#### Update Record

```php
// Update record berdasarkan ID
$success = $this->updateRecord('users', [
    'name' => 'John Doe Updated',
    'email' => 'john.updated@example.com'
], 1);

// updated_at akan otomatis diperbarui
if ($success) {
    echo "User updated successfully";
}
```

#### Delete Record

```php
// Delete record berdasarkan ID
$success = $this->deleteRecord('users', 1);

if ($success) {
    echo "User deleted successfully";
}
```

#### Find Record

```php
// Find record berdasarkan ID
$user = $this->findRecord('users', 1);

if ($user) {
    echo "User found: " . $user['name'];
} else {
    echo "User not found";
}
```

### Raw Queries

```php
// Raw query dengan binding
$users = $this->raw(
    "SELECT * FROM users WHERE status = ? AND created_at > ?",
    ['active', '2024-01-01']
);
```

### Transactions

```php
$result = $this->transaction(function() use ($model) {
    // Insert user
    $userId = $this->Storage('users')->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);

    // Insert profile
    $this->Storage('profiles')->insert([
        'user_id' => $userId,
        'bio' => 'User biography'
    ]);

    return $userId;
});
```

### Union Queries

```php
$activeUsers = $this->Storage('users')
    ->where('status', 'active')
    ->select(['id', 'name', 'email']);

$inactiveUsers = $this->Storage('users')
    ->where('status', 'inactive')
    ->select(['id', 'name', 'email']);

$allUsers = $activeUsers->union($inactiveUsers)->get();
```

### Distinct

```php
$uniqueEmails = $this->Storage('users')
    ->distinct('email')
    ->get();
```

### Exists

```php
$hasActiveUsers = $this->Storage('users')
    ->where('status', 'active')
    ->exists();
```

### Helper Methods

#### Null Value Handling

```php
// Handle null values dengan default
$users = $this->Storage('users')
    ->get();

$processedUsers = $this->handleNullValues($users, [
    'name' => 'Unknown',
    'email' => 'no-email@example.com',
    'status' => 'inactive'
]);
```

#### Slug Generation

```php
$slug = $this->addSlug('This is a Title');
// Result: 'this-is-a-title'
```

#### JSON Handling

```php
$json = $this->toJson($data, true); // pretty print
```

#### Select with Defaults

```php
// Select dengan default values menggunakan COALESCE
$users = $this->Storage('users')
    ->selectWithDefaults([
        'id' => null,
        'name' => null,
        'status' => 'inactive',
        'email' => null,
        'avatar' => '/assets/images/default-avatar.png'
    ])
    ->get();
```

### Query Shortcuts

#### Latest/Oldest

```php
// Get latest records (berdasarkan created_at)
$latestUsers = $this->Storage('users')
    ->latest()
    ->limit(10)
    ->get();

// Get oldest records
$oldestUsers = $this->Storage('users')
    ->oldest()
    ->limit(10)
    ->get();

// Latest berdasarkan kolom tertentu
$latestPosts = $this->Storage('posts')
    ->latest('published_at')
    ->get();
```

#### First or Fail

```php
// Get first record atau throw exception jika tidak ada
try {
    $user = $this->Storage('users')
        ->where('email', 'john@example.com')
        ->firstOrFail();
} catch (\Exception $e) {
    echo "User not found!";
}
```

### Advanced Aggregation Methods

#### Count Multiple Where

```php
// Count dengan multiple where conditions
$stats = $this->Storage('users')
    ->countMultipleWhere([
        'active_users' => [
            ['status', '=', 'active'],
            ['verified', '=', 1]
        ],
        'inactive_users' => [
            ['status', '=', 'inactive']
        ],
        'unverified_users' => [
            ['verified', '=', 0]
        ]
    ]);

// Result: ['active_users' => 120, 'inactive_users' => 30, 'unverified_users' => 15]
```

#### Quick Status Count

```php
// Quick count berdasarkan status
$statusCount = $this->Storage('users')
    ->quickStatusCount('status', ['active', 'inactive', 'banned']);

// Result: ['active' => 150, 'inactive' => 25, 'banned' => 5]
```

#### Get Percentages

```php
// Get percentages untuk kondisi tertentu
$percentages = $this->Storage('users')
    ->getPercentages('status', [
        'active' => ['status', '=', 'active'],
        'inactive' => ['status', '=', 'inactive']
    ], 2); // 2 decimal places

// Result: ['active' => 85.71, 'inactive' => 14.29]
```

### Display Methods

NexaModel menyediakan method untuk menampilkan hasil query dengan formatting:

#### Get and Display

```php
// Display hasil query dengan format yang rapi
$this->Storage('users')
    ->where('status', 'active')
    ->limit(5)
    ->getAndDisplay(true); // dengan <pre> tag
```

#### First and Display

```php
// Display record pertama
$this->Storage('users')
    ->where('id', 1)
    ->firstAndDisplay(true);
```

#### Count and Display

```php
// Display count dengan format
$this->Storage('users')
    ->where('status', 'active')
    ->countAndDisplay(true);
```

#### Count by Conditions and Display

```php
// Display count by conditions
$this->Storage('users')
    ->countByConditionsAndDisplay('status', [
        'active' => ['status', '=', 'active'],
        'inactive' => ['status', '=', 'inactive']
    ], true);
```

#### Count with Percentage and Display

```php
// Display count dengan percentage
$this->Storage('users')
    ->countWithPercentageAndDisplay('status', ['active', 'inactive'], true);
```

#### Aggregate and Display

```php
// Display aggregation results
$this->Storage('orders')
    ->aggregateAndDisplay(['total_amount', 'tax_amount'], 'sum', true);
```

## Examples

### Complete CRUD Example

```php
<?php
namespace App\Models;

use App\System\NexaModel;

class Article extends NexaModel
{
    protected $table = 'articles';

    public function createArticle(array $data): array
    {
        try {
            $articleId = $this->Storage($this->table)->insert([
                'title' => $data['title'],
                'content' => $data['content'],
                'author_id' => $data['author_id'],
                'slug' => $this->addSlug($data['title']),
                'status' => $data['status'] ?? 'draft',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return [
                'success' => true,
                'message' => 'Article created successfully',
                'id' => $articleId
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function getPublishedArticles(int $page = 1, int $perPage = 10): array
    {
        return $this->Storage($this->table)
            ->where('status', 'published')
            ->orderBy('created_at', 'DESC')
            ->paginate($page, $perPage);
    }

    public function searchArticles(string $keyword): array
    {
        return $this->Storage($this->table)
            ->where('title', 'LIKE', "%{$keyword}%")
            ->orWhere('content', 'LIKE', "%{$keyword}%")
            ->where('status', 'published')
            ->get();
    }

    public function getArticleStats(): array
    {
        return $this->Storage($this->table)
            ->countWithPercentage('status', ['published', 'draft', 'archived']);
    }
}
```

### Usage in Controller

```php
<?php
namespace App\Controllers;

use App\Models\Article;

class ArticleController
{
    private $articleModel;

    public function __construct()
    {
        $this->articleModel = new Article();
    }

    public function index()
    {
        $page = $_GET['page'] ?? 1;
        $articles = $this->articleModel->getPublishedArticles($page, 15);

        // Return view with articles
        return view('articles.index', compact('articles'));
    }

    public function store()
    {
        $result = $this->articleModel->createArticle($_POST);

        if ($result['success']) {
            redirect('/articles?success=1');
        } else {
            redirect('/articles/create?error=' . urlencode($result['message']));
        }
    }

    public function search()
    {
        $keyword = $_GET['q'] ?? '';
        $articles = $this->articleModel->searchArticles($keyword);

        return json_encode($articles);
    }

    public function stats()
    {
        $stats = $this->articleModel->getArticleStats();

        return json_encode($stats);
    }
}
```

### Generic Record Operations Example

```php
<?php
namespace App\Controllers;

use App\System\NexaModel;

class UserController
{
    private $model;

    public function __construct()
    {
        $this->model = new NexaModel();
    }

    public function create()
    {
        try {
            $userId = $this->model->insertRecord('users', [
                'name' => $_POST['name'],
                'email' => $_POST['email'],
                'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                'status' => 'active'
            ]);

            return json_encode([
                'success' => true,
                'message' => 'User created successfully',
                'id' => $userId
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function update($id)
    {
        try {
            $success = $this->model->updateRecord('users', [
                'name' => $_POST['name'],
                'email' => $_POST['email']
            ], $id);

            return json_encode([
                'success' => $success,
                'message' => $success ? 'User updated' : 'User not found'
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function delete($id)
    {
        try {
            $success = $this->model->deleteRecord('users', $id);

            return json_encode([
                'success' => $success,
                'message' => $success ? 'User deleted' : 'User not found'
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function show($id)
    {
        try {
            $user = $this->model->findRecord('users', $id);

            if ($user) {
                return json_encode([
                    'success' => true,
                    'data' => $user
                ]);
            } else {
                return json_encode([
                    'success' => false,
                    'message' => 'User not found'
                ]);
            }
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
```

### Advanced Statistics Example

```php
<?php
namespace App\Models;

use App\System\NexaModel;

class Dashboard extends NexaModel
{
    public function getUserStatistics(): array
    {
        // Multiple statistics dalam satu call
        $stats = $this->Storage('users')
            ->countMultipleWhere([
                'active_users' => [
                    ['status', '=', 'active'],
                    ['verified', '=', 1]
                ],
                'inactive_users' => [
                    ['status', '=', 'inactive']
                ],
                'unverified_users' => [
                    ['verified', '=', 0]
                ],
                'admin_users' => [
                    ['role', '=', 'admin']
                ]
            ]);

        return $stats;
    }

    public function getOrderStatistics(): array
    {
        // Get order statistics dengan percentage
        $orderStats = $this->Storage('orders')
            ->countWithPercentage('status', ['completed', 'pending', 'cancelled']);

        // Get revenue statistics
        $revenueStats = $this->Storage('orders')
            ->where('status', 'completed')
            ->sumColumnsWithPercentage(['total_amount', 'tax_amount', 'shipping_cost']);

        return array_merge($orderStats, $revenueStats);
    }

    public function getQuickStats(): array
    {
        // Quick status count
        $userStatus = $this->Storage('users')
            ->quickStatusCount('status', ['active', 'inactive', 'banned']);

        $orderStatus = $this->Storage('orders')
            ->quickStatusCount('status', ['completed', 'pending', 'cancelled']);

        return [
            'users' => $userStatus,
            'orders' => $orderStatus
        ];
    }

    public function displayDashboard(): void
    {
        // Display statistics dengan format yang rapi
        echo "<h2>User Statistics</h2>";
        $this->Storage('users')
            ->countByConditionsAndDisplay('status', [
                'active' => ['status', '=', 'active'],
                'inactive' => ['status', '=', 'inactive']
            ], true);

        echo "<h2>Order Statistics</h2>";
        $this->Storage('orders')
            ->countWithPercentageAndDisplay('status', ['completed', 'pending'], true);

        echo "<h2>Revenue Statistics</h2>";
        $this->Storage('orders')
            ->where('status', 'completed')
            ->aggregateAndDisplay(['total_amount', 'tax_amount'], 'sum', true);
    }

    /**
     * Get safe user data for API (no sensitive fields)
     */
    public function getSafeUserData()
    {
        return $this->Storage('users')
            ->noSensitive()
            ->onlyActive()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get financial accounts excluding salary data
     */
    public function getPublicAccounts()
    {
        return $this->Storage('accounts')
            ->noSelectFields(['account_name' => ['Biaya Gaji', 'Biaya Rahasia']])
            ->onlyActive()
            ->orderBy('budget', 'DESC')
            ->get();
    }
}
```

### Debugging dan Development Tools

#### Query Debugging

```php
// Lihat SQL query yang akan dieksekusi
$sql = $this->Storage('users')
    ->where('status', 'active')
    ->toSql();
echo $sql;

// Lihat bindings
$bindings = $this->Storage('users')
    ->where('status', 'active')
    ->getBindings();
print_r($bindings);

// Debug dump query dan stop execution
$this->Storage('users')
    ->where('status', 'active')
    ->dd();

// Dump query tanpa stop execution
$this->Storage('users')
    ->where('status', 'active')
    ->dump()
    ->get();
```

#### Performance Testing

```php
// Test performa query
$benchmark = $this->Storage('users')
    ->where('status', 'active')
    ->benchmarkQuery(100); // 100 iterations

print_r($benchmark);
// Output: ['iterations' => 100, 'total_time' => 0.5, 'avg_time' => 0.005, ...]
```

#### Database Health Check

```php
// Check database health
$health = $this->healthCheck();
print_r($health);

// Display health dengan format
$this->showHealth(true);
```

#### Column Validation Testing

```php
// Test validasi kolom
$columns = [
    'name',
    'email',
    'UPPER(name) AS name_upper',
    'invalid_function()' // akan error
];

$validation = $this->testColumnValidation($columns);
print_r($validation);

// Display hasil validasi
$this->testColumnValidationAndDisplay($columns, true);
```

## Best Practices

### 1. Model Organization

```php
<?php
namespace App\Models;

use App\System\NexaModel;

class User extends NexaModel
{
    protected $table = 'users';
    protected $fillable = ['name', 'email', 'password'];

    // Definisikan relasi dan method custom
    public function getActiveUsers(): array
    {
        return $this->Storage($this->table)
            ->where('status', 'active')
            ->get();
    }

    public function findByEmail(string $email): ?array
    {
        return $this->Storage($this->table)
            ->where('email', $email)
            ->first();
    }
}
```

### 2. Error Handling

```php
public function createUser(array $data): array
{
    try {
        $userId = $this->Storage('users')->insert($data);

        return [
            'success' => true,
            'message' => 'User created successfully',
            'id' => $userId
        ];
    } catch (\Exception $e) {
        // Log error
        error_log('User creation failed: ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Failed to create user'
        ];
    }
}
```

### 3. Input Validation

```php
public function updateUser(int $id, array $data): array
{
    // Validate input
    $allowedFields = ['name', 'email', 'phone'];
    $updateData = array_intersect_key($data, array_flip($allowedFields));

    if (empty($updateData)) {
        return ['success' => false, 'message' => 'No valid fields to update'];
    }

    try {
        $affected = $this->Storage('users')
            ->where('id', $id)
            ->update($updateData);

        return [
            'success' => $affected > 0,
            'message' => $affected > 0 ? 'User updated' : 'User not found'
        ];
    } catch (\Exception $e) {
        return ['success' => false, 'message' => 'Update failed'];
    }
}
```

### 4. Performance Optimization

```php
// Gunakan select untuk kolom yang diperlukan saja
$users = $this->Storage('users')
    ->select(['id', 'name', 'email'])
    ->where('status', 'active')
    ->get();

// Gunakan pagination untuk data besar
$users = $this->Storage('users')
    ->paginate(1, 50);

// Untuk data “sering dibaca”, kurangi beban dengan select kolom terbatas + index DB di sisi MySQL
$popularArticles = $this->Storage('articles')
    ->select(['id', 'title', 'views'])
    ->where('views', '>', 1000)
    ->limit(100)
    ->get();
```

### 5. Security Best Practices

```php
// Selalu gunakan prepared statements (otomatis di NexaModel)
$user = $this->Storage('users')
    ->where('email', $userInput) // Aman dari SQL injection
    ->first();

// Validasi input sebelum digunakan
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new InvalidArgumentException('Invalid email format');
}

// Gunakan whitelist untuk kolom yang diizinkan
$allowedColumns = ['name', 'email', 'phone'];
$selectColumns = array_intersect($requestedColumns, $allowedColumns);
```

## API Reference

### Core Methods

#### `table(string $table)`

Menentukan tabel yang akan digunakan.

#### `Storage(string $table)`

Alias untuk method `table()`.

#### `select(array $columns)`

Menentukan kolom yang akan dipilih.

#### `where(string $column, string $operator, mixed $value, string $boolean = 'AND')`

Menambahkan kondisi WHERE.

#### `get()`

Mengeksekusi query dan mengembalikan semua hasil.

#### `first()`

Mengeksekusi query dan mengembalikan hasil pertama.

#### `insert(array $data)`

Menambahkan record baru.

#### `update(array $data)`

Mengupdate record yang cocok dengan kondisi.

#### `delete()`

Menghapus record yang cocok dengan kondisi.

### Aggregation Methods

#### `count(string $column = '*')`

Menghitung jumlah record.

#### `sum(string $column)`

Menjumlahkan nilai kolom.

#### `avg(string $column)`

Menghitung rata-rata nilai kolom.

#### `min(string $column)`

Mencari nilai minimum.

#### `max(string $column)`

Mencari nilai maksimum.

### Pagination Methods

#### `paginate(int $page, int $perPage)`

Pagination dengan data.

#### `paginateInfo(int $page, int $perPage)`

Informasi pagination.

### Utility Methods

#### `raw(string $sql, array $bindings = [])`

Eksekusi raw SQL query.

#### `transaction(callable $callback)`

Eksekusi dalam transaction.

#### `exists()`

Cek apakah record ada.

#### `toSql()`

Mendapatkan SQL query string.

#### `dd()`

Debug dump query.

### Generic Record Methods

#### `insertRecord(string $table, array $data)`

Insert record dengan auto timestamps.

#### `updateRecord(string $table, array $data, int $id)`

Update record berdasarkan ID dengan auto timestamp.

#### `deleteRecord(string $table, int $id)`

Delete record berdasarkan ID.

#### `findRecord(string $table, int $id)`

Find record berdasarkan ID.

### Field Exclusion Methods

#### `noSelect(array|string $excludeColumns)`

Mengecualikan kolom tertentu dari SELECT statement.

#### `except(array|string $excludeColumns)`

Alias untuk noSelect().

#### `exclude(array|string $excludeColumns)`

Alias untuk noSelect().

#### `without(array|string $excludeColumns)`

Alias untuk noSelect().

#### `ignore(array|string $excludeColumns)`

Alias untuk noSelect().

#### `skipFields(array|string $excludeColumns)`

Alias untuk noSelect().

#### `exceptFrom(array|string $excludeColumns, array $allColumns)`

Mengecualikan kolom dari daftar kolom manual.

#### `noSelectFrom(array|string $excludeColumns, array $allColumns)`

Alias untuk exceptFrom().

#### `withoutFrom(array|string $excludeColumns, array $allColumns)`

Alias untuk exceptFrom().

#### `noPassword()`

Tidak memilih field password.

#### `noTimestamps()`

Tidak memilih field timestamps.

#### `noSensitive(array $additionalExcludes = [])`

Tidak memilih field sensitif.

#### `noTokens()`

Tidak memilih field token.

#### `noId()`

Tidak memilih field ID.

#### `noSystem()`

Tidak memilih field sistem.

#### `exceptSensitive(array $additionalExcludes = [])`

Alias untuk noSensitive().

#### `exceptTimestamps(array $additionalExcludes = [])`

Mengecualikan field timestamp dengan field tambahan.

#### `exceptSystemFields(array $additionalExcludes = [])`

Mengecualikan field sistem dengan field tambahan.

#### `selectPublicFields(array $additionalIncludes = [])`

Memilih field publik yang aman.

#### `selectMinimal(array $baseFields = ['id', 'name', 'email'], array $additionalFields = [])`

Memilih field minimal.

### Record Exclusion Methods

#### `noSelectFields(array $conditions)`

Mengecualikan record berdasarkan kondisi field.

#### `hideRecords(array $conditions)`

Alias untuk noSelectFields().

#### `excludeRecords(array $conditions)`

Alias untuk noSelectFields().

#### `skipRecords(array $conditions)`

Alias untuk noSelectFields().

#### `withoutRecords(array $conditions)`

Alias untuk noSelectFields().

#### `filterOut(array $conditions)`

Alias untuk noSelectFields().

#### `onlyActive(string $statusField = 'is_active')`

Hanya menampilkan record aktif.

#### `onlyInactive(string $statusField = 'is_active')`

Hanya menampilkan record tidak aktif.

#### `notDeleted(string $deletedField = 'deleted_at')`

Tidak termasuk record yang dihapus (soft delete).

#### `onlyDeleted(string $deletedField = 'deleted_at')`

Hanya menampilkan record yang dihapus.

### Query Shortcut Methods

#### `latest(string $column = 'created_at')`

Order by kolom secara descending.

#### `oldest(string $column = 'created_at')`

Order by kolom secara ascending.

#### `firstOrFail()`

Get first record atau throw exception.

#### `take(int $value)`

Alias untuk limit().

#### `skip(int $value)`

Alias untuk offset().

#### `distinct(array $columns = null)`

Select distinct values.

### Advanced Aggregation Methods

#### `countMultipleWhere(array $whereConditions, string $countColumn = '*')`

Count dengan multiple where conditions.

#### `quickStatusCount(string $statusColumn = 'status', array $statusValues = ['active', 'inactive'])`

Quick count berdasarkan status.

#### `getPercentages(string $column, array $conditions, int $decimals = 1)`

Get percentages untuk kondisi tertentu.

#### `countColumns(array $columns)`

Count multiple columns.

#### `sumColumns(array $columns)`

Sum multiple columns.

#### `avgColumns(array $columns)`

Average multiple columns.

#### `sumColumnsWithPercentage(array $columns)`

Sum dengan percentage.

#### `getColumnPercentages(array $columns)`

Get column percentages.

### Display Methods

#### `getAndDisplay(bool $withPre = false)`

Display hasil query dengan format.

#### `firstAndDisplay(bool $withPre = false)`

Display record pertama dengan format.

#### `countAndDisplay(bool $withPre = false, string $column = '*')`

Display count dengan format.

#### `countByConditionsAndDisplay(string $column, array $conditions, bool $withPre = false)`

Display count by conditions.

#### `countWithPercentageAndDisplay(string $column, array $conditions, bool $withPre = false)`

Display count dengan percentage.

#### `aggregateAndDisplay(array $columns, string $operation = 'sum', bool $withPre = false)`

Display aggregation results.

### Union Methods

#### `union($query, bool $all = false)`

Union dengan query lain.

#### `unionAll($query)`

Union all dengan query lain.

### Utility Methods Extended

#### `toJson(mixed $data, bool $prettyPrint = false)`

Convert data ke JSON string.

#### `addSlug(string $value)`

Generate slug dari string.

#### `handleNullValues(array $results, array $defaults = [])`

Handle null values dengan default.

#### `handleNullValue(array $record, array $defaults = [])`

Handle null value untuk single record.

#### `selectWithDefaults(array $columns)`

Select dengan default values menggunakan COALESCE.

#### `render(bool $withPre = false)`

Render hasil query.

#### `dump()`

Dump query untuk debugging.

### Validation Methods

#### `testColumnValidation(array $columns)`

Test validasi kolom.

#### `testColumnValidationAndDisplay(array $columns, bool $withPre = false)`

Test dan display validasi kolom.

### Database Info Methods

#### `showTablesFormatted(bool $withPre = false)`

Show tables dengan format.

#### `showTablesListFormatted(bool $withPre = false)`

Show tables list dengan format.

#### `showTableColumnsFormatted(string $tableName, bool $withPre = false)`

Show table columns dengan format.

#### `showDatabaseInfoFormatted(bool $withPre = false)`

Show database info dengan format.

#### `exploreDatabase(bool $withPre = false, bool $showColumns = false)`

Explore database structure.

### Working Days Methods

#### `getWorkingDaysRange(string $startDate, int $workingDays = 5)`

Mendapatkan rentang hari kerja dari tanggal tertentu.

#### `getCurrentWorkWeek(string $referenceDate = null)`

Mendapatkan rentang minggu kerja saat ini (Senin-Jumat).

#### `whereWorkingDays(string $dateColumn, string $startDate = null, int $workingDays = 5)`

Filter query berdasarkan rentang hari kerja. Jika workingDays = 0, filter hanya tanggal tersebut.

#### `whereCurrentWorkWeek(string $dateColumn, string $referenceDate = null)`

Filter query berdasarkan minggu kerja saat ini.

#### `isWorkingDay(string $date)`

Cek apakah tanggal adalah hari kerja.

#### `getWorkingDaysList(string $startDate, string $endDate)`

Mendapatkan daftar hari kerja antara dua tanggal.

#### `countWorkingDays(string $startDate, string $endDate)`

Menghitung jumlah hari kerja antara dua tanggal.

---

## Kesimpulan

NexaModel menyediakan interface yang powerful dan aman untuk berinteraksi dengan database. Dengan fitur-fitur seperti query builder yang ekspresif, sistem caching, monitoring performa, dan keamanan yang ketat, NexaModel membantu developer membangun aplikasi yang robust dan scalable.

Untuk informasi lebih lanjut dan update terbaru, silakan kunjungi dokumentasi resmi atau repository GitHub.

---

_Dokumentasi ini dibuat untuk NexaModel versi terbaru. Pastikan untuk selalu menggunakan versi terbaru untuk mendapatkan fitur dan keamanan terkini._
