# NexaModels - React Native SQL Query Builder & API Integration

## 📚 Daftar Isi

- [Pengenalan](#pengenalan)
- [Instalasi](#instalasi)
- [Quick Start](#quick-start)
- [SQL Query Builder](#sql-query-builder)
- [API Integration](#api-integration)
- [CRUD Operations](#crud-operations)
- [Advanced Queries](#advanced-queries)
- [Pagination](#pagination)
- [Security](#security)
- [API Reference](#api-reference)

---

## 🎯 Pengenalan

**NexaModels** adalah SQL Query Builder yang powerful dan standalone untuk React Native dengan integrasi API otomatis. NexaModels menyediakan:

- ✅ **SQL Query Builder** - Generate SQL queries dengan method chaining
- ✅ **API Integration** - Execute queries langsung ke `/api/models` endpoint
- ✅ **Auto Encryption** - SQL & table name otomatis dienkripsi
- ✅ **Standalone** - Tidak bergantung pada NexaStores
- ✅ **Type Safe** - Validasi otomatis untuk table & column names
- ✅ **RESTful** - Menggunakan HTTP methods yang sesuai (GET, POST, PUT, PATCH, DELETE)

### 🏗️ Arsitektur

```
NexaModels.js
├── SQL Query Builder (Generate SQL)
├── NexaEncrypt (Auto Encryption)
├── NexaFetch (HTTP Client)
└── Server.API_Models (Endpoint: /api/models)
    └── ApiController.php
        └── ModelsController.php
            └── NexaBig() (Auto Decryption & Execute)
```

---

## 📦 Instalasi

```javascript
import { NexaModels } from 'NexaUI';
// atau
import NexaModels from './package/Storage/NexaModels.js';
```

---

## 🚀 Quick Start

### 1. Generate SQL (Tanpa API Call)

```javascript
import { NexaModels } from 'NexaUI';

const query = new NexaModels();
const sql = query
  .table('users')
  .where('status', 'active')
  .toSql();

console.log(sql);
// Output: "SELECT * FROM users WHERE status = 'active'"
```

### 2. Get Data dari API

```javascript
const query = new NexaModels();
const users = await query
  .table('users')
  .where('status', 'active')
  .limit(10)
  .get();

console.log(users);
// Response: { success: true, data: [...], count: 10 }
```

---

## 🔨 SQL Query Builder

### Basic Methods

#### `table(tableName)`
Set nama tabel untuk query.

```javascript
const query = new NexaModels();
query.table('users');
```

#### `select(columns)`
Pilih kolom yang akan diambil.

```javascript
// Single column
query.select('id');

// Multiple columns (string)
query.select('id, name, email');

// Multiple columns (array)
query.select(['id', 'name', 'email']);

// All columns (default)
query.select('*');
```

#### `where(column, operator, value)`
Tambahkan kondisi WHERE.

```javascript
// Simple where
query.where('status', 'active');
// WHERE status = 'active'

// With operator
query.where('age', '>', 18);
// WHERE age > 18

// Multiple where (AND)
query
  .where('status', 'active')
  .where('age', '>', 18);
// WHERE status = 'active' AND age > 18

// Object where
query.where({ status: 'active', role: 'admin' });
// WHERE status = 'active' AND role = 'admin'
```

#### `orWhere(column, operator, value)`
Tambahkan kondisi OR WHERE.

```javascript
query
  .where('status', 'active')
  .orWhere('status', 'pending');
// WHERE status = 'active' OR status = 'pending'
```

#### `whereIn(column, values)`
Kondisi WHERE IN.

```javascript
query.whereIn('id', [1, 2, 3, 4, 5]);
// WHERE id IN (1, 2, 3, 4, 5)
```

#### `whereNotIn(column, values)`
Kondisi WHERE NOT IN.

```javascript
query.whereNotIn('role', ['banned', 'deleted']);
// WHERE role NOT IN ('banned', 'deleted')
```

#### `whereNull(column)`
Kondisi WHERE IS NULL.

```javascript
query.whereNull('deleted_at');
// WHERE deleted_at IS NULL
```

#### `whereNotNull(column)`
Kondisi WHERE IS NOT NULL.

```javascript
query.whereNotNull('email');
// WHERE email IS NOT NULL
```

#### `whereLike(column, value)`
Kondisi WHERE LIKE.

```javascript
query.whereLike('name', 'john');
// WHERE name LIKE '%john%'
```

#### `whereBetween(column, [min, max])`
Kondisi WHERE BETWEEN.

```javascript
query.whereBetween('age', [18, 65]);
// WHERE age BETWEEN 18 AND 65
```

#### `orderBy(column, direction)`
Urutkan hasil query.

```javascript
// ASC (default)
query.orderBy('created_at');
// ORDER BY created_at ASC

// DESC
query.orderBy('created_at', 'DESC');
// ORDER BY created_at DESC

// Multiple order
query
  .orderBy('status', 'ASC')
  .orderBy('created_at', 'DESC');
// ORDER BY status ASC, created_at DESC
```

#### `limit(number)`
Batasi jumlah hasil.

```javascript
query.limit(10);
// LIMIT 10
```

#### `offset(number)`
Set offset untuk pagination.

```javascript
query.offset(20);
// OFFSET 20
```

#### `groupBy(columns)`
Group hasil berdasarkan kolom.

```javascript
query.groupBy('status');
// GROUP BY status

query.groupBy(['status', 'role']);
// GROUP BY status, role
```

#### `having(column, operator, value)`
Kondisi HAVING (setelah GROUP BY).

```javascript
query
  .groupBy('status')
  .having('count', '>', 5);
// GROUP BY status HAVING count > 5
```

---

## 🌐 API Integration

### Method API

Semua method API mengirim request ke `/api/models` dengan enkripsi otomatis.

#### `get(options)`
Execute SELECT query (POST method → `created()`).

```javascript
const users = await query
  .table('users')
  .where('status', 'active')
  .get();

// Response:
// {
//   success: true,
//   message: "Query executed successfully",
//   data: [...],
//   count: 10
// }
```

#### `post(options)`
Alias untuk `get()` (POST method → `created()`).

```javascript
const users = await query
  .table('users')
  .post();
```

#### `first(options)`
Get first record (POST method → `created()`).

```javascript
const user = await query
  .table('users')
  .where('id', 1)
  .first();

// Response: { success: true, data: {...} }
```

#### `count(column, options)`
Count records (POST method → `created()`).

```javascript
const total = await query
  .table('users')
  .where('status', 'active')
  .count();

// Response: { success: true, data: 100 }
```

#### `exists(options)`
Check if record exists (POST method → `created()`).

```javascript
const exists = await query
  .table('users')
  .where('email', 'john@example.com')
  .exists();

// Response: { success: true, data: true/false }
```

#### `paginate(page, perPage, options)`
Pagination dengan count + data (POST method → `created()`).

```javascript
const result = await query
  .table('users')
  .where('status', 'active')
  .paginate(1, 20);

// Response:
// {
//   success: true,
//   data: [...],
//   total: 100,
//   current_page: 1,
//   per_page: 20,
//   last_page: 5,
//   has_next: true,
//   has_previous: false
// }
```

---

## 📝 CRUD Operations

### CREATE - Insert Data

#### `insert(data, options)`
Insert single record (POST method → `created()`).

```javascript
const newUser = await query
  .table('users')
  .insert({
    name: 'John Doe',
    email: 'john@example.com',
    status: 'active'
  });

// Response: { success: true, message: "Insert executed successfully" }
```

### READ - Get Data

```javascript
// Get all
const users = await query.table('users').get();

// Get with conditions
const activeUsers = await query
  .table('users')
  .where('status', 'active')
  .get();

// Get first
const user = await query
  .table('users')
  .where('id', 1)
  .first();
```

### UPDATE - Update Data

#### `update(data, options)`
Update records (PUT method → `red()`).

```javascript
const updated = await query
  .table('users')
  .where('id', 1)
  .update({
    name: 'Jane Doe',
    status: 'inactive'
  });

// Response: { success: true, message: "Update executed successfully" }
```

#### `updated(data, options)`
Alias untuk update (PATCH method → `updated()`).

```javascript
const updated = await query
  .table('users')
  .where('id', 1)
  .updated({
    name: 'Jane Doe'
  });
```

#### `red(data, options)`
Alias untuk update (PUT method → `red()`).

```javascript
const updated = await query
  .table('users')
  .where('id', 1)
  .red({
    name: 'Jane Doe'
  });
```

### DELETE - Delete Data

#### `delete(options)`
Delete records (DELETE method → `deleted()`).

```javascript
const deleted = await query
  .table('users')
  .where('id', 1)
  .delete();

// Response: { success: true, message: "Delete executed successfully" }
```

#### `deleted(options)`
Alias untuk delete (DELETE method → `deleted()`).

```javascript
const deleted = await query
  .table('users')
  .where('id', 1)
  .deleted();
```

---

## 🔥 Advanced Queries

### Joins

```javascript
// INNER JOIN
query
  .table('users')
  .select('users.*, orders.total')
  .innerJoin('orders', 'users.id', '=', 'orders.user_id');

// LEFT JOIN
query
  .table('users')
  .leftJoin('orders', 'users.id', '=', 'orders.user_id');

// RIGHT JOIN
query
  .table('users')
  .rightJoin('orders', 'users.id', '=', 'orders.user_id');
```

### Complex WHERE Conditions

```javascript
query
  .table('users')
  .where('status', 'active')
  .whereIn('role', ['admin', 'user'])
  .whereNotIn('id', [1, 2, 3])
  .whereNotNull('email')
  .whereBetween('age', [18, 65])
  .whereLike('name', 'john')
  .orWhere('status', 'pending');
```

### Aggregations

```javascript
// Count
const total = await query
  .table('users')
  .where('status', 'active')
  .count();

// Count dengan GROUP BY
const stats = await query
  .table('orders')
  .select('user_id, COUNT(*) as total_orders, SUM(amount) as total_amount')
  .groupBy('user_id')
  .having('total_orders', '>', 5)
  .get();
```

### Date Queries

```javascript
// Where date
query.whereDate('created_at', '2024-01-01');

// Where year
query.whereYear('created_at', 2024);

// Where month
query.whereMonth('created_at', 12);

// Where day
query.whereDay('created_at', 25);
```

### Subqueries

```javascript
// Using raw SQL in where
query.where('id', 'IN', (subQuery) => {
  return subQuery
    .table('orders')
    .select('user_id')
    .where('status', 'completed')
    .toSql();
});
```

---

## 📄 Pagination

### Basic Pagination

```javascript
const result = await query
  .table('users')
  .where('status', 'active')
  .paginate(1, 20);

// Response:
// {
//   success: true,
//   data: [...],
//   total: 100,
//   current_page: 1,
//   per_page: 20,
//   last_page: 5,
//   from: 1,
//   to: 20,
//   has_pages: true,
//   has_next: true,
//   has_previous: false
// }
```

### Pagination dengan Custom Query

```javascript
const result = await query
  .table('products')
  .where('published', 1)
  .where('price', '>', 100)
  .orderBy('created_at', 'DESC')
  .paginate(2, 15);
```

---

## 🔒 Security

### Auto Encryption

NexaModels otomatis mengenkripsi SQL dan table name sebelum dikirim ke backend:

```javascript
// Frontend (NexaModels)
const data = await query.table('users').get();

// Payload yang dikirim:
{
  sql: "PSA0JG4wRUxBMz47YFMBBh8KVGEiKDB5EgEC",  // Encrypted
  table: "CgAVDg==",                              // Encrypted
  bindings: [],
  type: "select"
}

// Backend (ModelsController) otomatis decrypt:
// - SQL: "SELECT * FROM users"
// - Table: "users"
```

### SQL Injection Protection

NexaModels melindungi dari SQL injection dengan:

1. **Validasi Table & Column Names**
   ```javascript
   query.table('users; DROP'); // ❌ Error: Invalid table name
   ```

2. **Parameter Binding**
   ```javascript
   query.where('id', 1);
   // Menggunakan parameter binding, bukan string concatenation
   ```

3. **Auto Escaping**
   ```javascript
   query.where('name', "O'Brien");
   // Otomatis escape special characters
   ```

---

## 📖 API Reference

### Constructor

```javascript
new NexaModels(secretKey?)
```

**Parameters:**
- `secretKey` (optional): Secret key untuk enkripsi (default: "nexa-default-secret-key-2025")

**Example:**
```javascript
const query = new NexaModels();
// atau
const query = new NexaModels("custom-secret-key");
```

### Query Builder Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `table(name)` | Set table name | `NexaModels` |
| `select(columns)` | Set columns to select | `NexaModels` |
| `where(column, operator?, value?)` | Add WHERE condition | `NexaModels` |
| `orWhere(column, operator?, value?)` | Add OR WHERE condition | `NexaModels` |
| `whereIn(column, values)` | Add WHERE IN condition | `NexaModels` |
| `whereNotIn(column, values)` | Add WHERE NOT IN condition | `NexaModels` |
| `whereNull(column)` | Add WHERE IS NULL condition | `NexaModels` |
| `whereNotNull(column)` | Add WHERE IS NOT NULL condition | `NexaModels` |
| `whereLike(column, value)` | Add WHERE LIKE condition | `NexaModels` |
| `whereBetween(column, [min, max])` | Add WHERE BETWEEN condition | `NexaModels` |
| `orderBy(column, direction?)` | Add ORDER BY clause | `NexaModels` |
| `limit(number)` | Set LIMIT | `NexaModels` |
| `offset(number)` | Set OFFSET | `NexaModels` |
| `groupBy(columns)` | Add GROUP BY clause | `NexaModels` |
| `having(column, operator, value)` | Add HAVING clause | `NexaModels` |
| `join(table, first, operator, second, type?)` | Add JOIN clause | `NexaModels` |
| `leftJoin(table, first, operator, second)` | Add LEFT JOIN | `NexaModels` |
| `rightJoin(table, first, operator, second)` | Add RIGHT JOIN | `NexaModels` |
| `innerJoin(table, first, operator, second)` | Add INNER JOIN | `NexaModels` |

### SQL Generation Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `toSql()` | Generate SELECT SQL | `string` |
| `insertSql(data)` | Generate INSERT SQL | `string` |
| `updateSql(data)` | Generate UPDATE SQL | `string` |
| `deleteSql()` | Generate DELETE SQL | `string` |
| `countSql(column?)` | Generate COUNT SQL | `string` |
| `firstSql()` | Generate SELECT ... LIMIT 1 SQL | `string` |
| `existsSql()` | Generate EXISTS SQL | `string` |
| `paginateSql(page, perPage)` | Generate pagination SQL | `object` |

### API Integration Methods

| Method | HTTP Method | Backend Route | Description |
|--------|-------------|---------------|-------------|
| `get(options?)` | POST | `created()` | Execute SELECT query |
| `post(options?)` | POST | `created()` | Alias untuk get() |
| `insert(data, options?)` | POST | `created()` | Insert record |
| `update(data, options?)` | PUT | `red()` | Update records |
| `red(data, options?)` | PUT | `red()` | Alias untuk update() |
| `updated(data, options?)` | PATCH | `updated()` | Update records |
| `delete(options?)` | DELETE | `deleted()` | Delete records |
| `deleted(options?)` | DELETE | `deleted()` | Alias untuk delete() |
| `first(options?)` | POST | `created()` | Get first record |
| `count(column?, options?)` | POST | `created()` | Count records |
| `exists(options?)` | POST | `created()` | Check if exists |
| `paginate(page, perPage, options?)` | POST | `created()` | Paginated query |

### Utility Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `reset()` | Reset query builder | `NexaModels` |
| `clone()` | Clone query instance | `NexaModels` |
| `dump()` | Print SQL to console | `NexaModels` |
| `dd()` | Print SQL and stop execution | `NexaModels` |

---

## 💡 Best Practices

### 1. Reuse Query Instance

```javascript
// ❌ BAD: Create new instance setiap kali
const users1 = await new NexaModels().table('users').get();
const users2 = await new NexaModels().table('users').get();

// ✅ GOOD: Reuse instance
const query = new NexaModels().table('users');
const users1 = await query.clone().get();
const users2 = await query.clone().get();
```

### 2. Use Specific Columns

```javascript
// ❌ BAD: Select all columns
const users = await query.table('users').get();

// ✅ GOOD: Select only needed columns
const users = await query
  .table('users')
  .select('id, name, email')
  .get();
```

### 3. Use Indexes

```javascript
// ✅ GOOD: Query dengan indexed columns
query
  .where('id', 1)           // Primary key (indexed)
  .where('email', '...')    // Unique key (indexed)
  .orderBy('created_at');   // Indexed column
```

### 4. Limit Results

```javascript
// ✅ GOOD: Always limit results
query
  .table('users')
  .limit(100);  // Prevent large result sets
```

### 5. Use Transactions untuk Multiple Operations

```javascript
// Untuk multiple operations, gunakan transaction di backend
// atau execute secara sequential
const user = await query.table('users').insert({...});
const profile = await query.table('profiles').insert({...});
```

---

## 🐛 Troubleshooting

### Error: "Table name is required"

```javascript
// ❌ Error
const sql = query.toSql();

// ✅ Fix
const sql = query.table('users').toSql();
```

### Error: "Invalid table name"

```javascript
// ❌ Error: Invalid characters
query.table('users; DROP TABLE users');

// ✅ Fix: Use valid table name
query.table('users');
```

### Error: HTTP 500

```javascript
// Check:
// 1. Backend endpoint /api/models exists
// 2. ModelsController.php has method index(), created(), etc.
// 3. NexaBig() method available in NexaController
```

### Error: Decryption failed

```javascript
// Check:
// 1. Secret key sama antara frontend dan backend
// 2. NexaEncrypt menggunakan secret key yang sama
```

---

## 📚 Examples

### Example 1: User Management

```javascript
import { NexaModels } from 'NexaUI';

// Get active users
const activeUsers = await new NexaModels()
  .table('users')
  .select('id, name, email, created_at')
  .where('status', 'active')
  .whereNotNull('email')
  .orderBy('created_at', 'DESC')
  .limit(20)
  .get();

// Create new user
const newUser = await new NexaModels()
  .table('users')
  .insert({
    name: 'John Doe',
    email: 'john@example.com',
    status: 'active'
  });

// Update user
const updated = await new NexaModels()
  .table('users')
  .where('id', 1)
  .update({
    name: 'Jane Doe',
    status: 'inactive'
  });

// Delete user
const deleted = await new NexaModels()
  .table('users')
  .where('id', 1)
  .delete();
```

### Example 2: E-commerce Orders

```javascript
// Get orders dengan user info
const orders = await new NexaModels()
  .table('orders')
  .select('orders.*, users.name as user_name, users.email')
  .leftJoin('users', 'orders.user_id', '=', 'users.id')
  .where('orders.status', 'completed')
  .whereBetween('orders.created_at', ['2024-01-01', '2024-12-31'])
  .orderBy('orders.created_at', 'DESC')
  .limit(50)
  .get();

// Get order statistics
const stats = await new NexaModels()
  .table('orders')
  .select('status, COUNT(*) as total, SUM(amount) as total_amount')
  .where('created_at', '>=', '2024-01-01')
  .groupBy('status')
  .having('total', '>', 10)
  .get();
```

### Example 3: Search dengan Pagination

```javascript
const searchUsers = async (keyword, page = 1) => {
  const query = new NexaModels()
    .table('users')
    .select('id, name, email')
    .where('status', 'active');

  if (keyword) {
    query.whereLike('name', keyword);
  }

  return await query
    .orderBy('created_at', 'DESC')
    .paginate(page, 20);
};

// Usage
const result = await searchUsers('john', 1);
console.log(result.data);      // Users
console.log(result.total);     // Total count
console.log(result.has_next);  // Has next page
```

---

## 🔗 Related Documentation

- [NexaStores Documentation](./NexaStores.md) - User endpoints API client
- [NexaFetch Documentation](./NexaFetch.md) - HTTP client
- [NexaEncrypt Documentation](./NexaEncrypt.md) - Encryption utility
- [Config Documentation](../config.js) - API configuration

---

## 📝 Changelog

### Version 3.0.0 (Current)
- ✅ Standalone - No dependency on NexaStores
- ✅ Direct API integration to `/api/models`
- ✅ RESTful HTTP methods (GET, POST, PUT, PATCH, DELETE)
- ✅ Auto encryption/decryption
- ✅ Complete CRUD operations

### Version 2.0.0
- ✅ React Native compatible
- ✅ NexaStores integration

### Version 1.0.0
- ✅ Initial release
- ✅ SQL Query Builder only

---

## 📞 Support

Untuk pertanyaan atau issue, silakan buat issue di repository atau hubungi tim development.

---

**Made with ❤️ by Nexa Framework**

