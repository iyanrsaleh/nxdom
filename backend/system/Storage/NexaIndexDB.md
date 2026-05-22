# NexaIndexDB Documentation

NexaIndexDB is a PHP implementation inspired by IndexedDB, providing a database interface with object stores, transactions, and indexes using SQLite as the underlying storage engine.

## Overview

NexaIndexDB provides a modern, object-oriented approach to data storage in PHP applications. It mimics the IndexedDB API familiar to JavaScript developers while leveraging SQLite's reliability and performance.

## Features

- **Object Stores**: Create and manage collections of data similar to tables
- **Transactions**: Ensure data consistency with transaction support
- **Indexes**: Create indexes for efficient data querying
- **Auto-increment Keys**: Support for automatic key generation
- **JSON Data Storage**: Store complex data structures as JSON
- **Simple API**: Easy-to-use interface inspired by IndexedDB

## Installation & Setup

The class is located at `dev/system/Storage/NexaIndexDB.php`. Database files are automatically created in the `dev/system/Storage/tabel/` directory.

```php
use App\System\Storage\NexaIndexDB;

// Create a new database instance
$db = new NexaIndexDB('myDatabase', 1);
```

## Basic Usage

### Creating a Database

```php
// Basic database creation
$db = new NexaIndexDB('userDatabase', 1);

// Custom database path
$db = new NexaIndexDB('userDatabase', 1, '/custom/path');
```

### Creating Object Stores

```php
// Create a simple object store with auto-increment ID
$userStore = $db->createObjectStore('users', [
    'keyPath' => 'id',
    'autoIncrement' => true
]);

// Create an object store with custom key
$productStore = $db->createObjectStore('products', [
    'keyPath' => 'productId',
    'autoIncrement' => false
]);
```

### Adding Data

```php
// Add data with auto-increment
$userId = $userStore->add([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// Add data with specific key
$productStore->add([
    'productId' => 'PROD001',
    'name' => 'Laptop',
    'price' => 999.99,
    'category' => 'Electronics'
]);
```

### Retrieving Data

```php
// Get data by key
$user = $userStore->get(1);
$product = $productStore->get('PROD001');

// Get all data
$allUsers = $userStore->getAll();

// Get limited results
$firstTenUsers = $userStore->getAll(null, 10);

// Get data with simple query
$searchResults = $userStore->getAll('john');
```

### Updating Data

```php
// Update existing data
$userStore->put([
    'id' => 1,
    'name' => 'John Smith',
    'email' => 'johnsmith@example.com',
    'age' => 31
]);
```

### Deleting Data

```php
// Delete specific record
$userStore->delete(1);

// Clear all records
$userStore->clear();
```

## Working with Indexes

### Creating Indexes

```php
// Create a unique index
$userStore->createIndex('emailIndex', 'email', ['unique' => true]);

// Create a non-unique index
$userStore->createIndex('ageIndex', 'age', ['unique' => false]);
```

### Querying by Index

```php
// Find users by email
$usersByEmail = $userStore->getByIndex('emailIndex', 'john@example.com');

// Find users by age
$usersByAge = $userStore->getByIndex('ageIndex', 30);
```

## Transactions

### Basic Transaction Usage

```php
// Start a readonly transaction
$transaction = $db->transaction(['users'], 'readonly');
$userStore = $transaction->objectStore('users');
$user = $userStore->get(1);

// Start a readwrite transaction
$transaction = $db->transaction(['users', 'products'], 'readwrite');
$userStore = $transaction->objectStore('users');
$productStore = $transaction->objectStore('products');

try {
    $userStore->add(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $productStore->add(['productId' => 'PROD002', 'name' => 'Mouse', 'price' => 25.99]);

    $transaction->commit();
} catch (Exception $e) {
    $transaction->rollback();
    throw $e;
}
```

## API Reference

### NexaIndexDB Class

#### Constructor

```php
public function __construct($dbName, $version = 1, $dbPath = null)
```

- `$dbName`: Name of the database
- `$version`: Database version (default: 1)
- `$dbPath`: Custom path for database files (optional)

#### Methods

##### createObjectStore($storeName, $options = [])

Creates a new object store.

**Parameters:**

- `$storeName`: Name of the object store
- `$options`: Configuration options
  - `keyPath`: Primary key field name (default: 'id')
  - `autoIncrement`: Whether to auto-increment keys (default: false)

**Returns:** `NexaObjectStore` instance

##### getObjectStore($storeName)

Retrieves an existing object store.

**Parameters:**

- `$storeName`: Name of the object store

**Returns:** `NexaObjectStore` instance

##### deleteObjectStore($storeName)

Deletes an object store and all its data.

**Parameters:**

- `$storeName`: Name of the object store to delete

##### transaction($storeNames, $mode = 'readonly')

Creates a new transaction.

**Parameters:**

- `$storeNames`: Array of store names or single store name
- `$mode`: Transaction mode ('readonly' or 'readwrite')

**Returns:** `NexaTransaction` instance

##### close()

Closes the database connection.

##### getInfo()

Returns database information.

**Returns:** Array with database details

### NexaObjectStore Class

#### Methods

##### add($data, $key = null)

Adds new data to the store.

**Parameters:**

- `$data`: Data to store
- `$key`: Optional key (used if not in data)

**Returns:** The key of the added record

##### get($key)

Retrieves data by key.

**Parameters:**

- `$key`: The key to search for

**Returns:** The stored data or null if not found

##### put($data, $key = null)

Updates or inserts data.

**Parameters:**

- `$data`: Data to store
- `$key`: Optional key

**Returns:** The key of the record

##### delete($key)

Deletes data by key.

**Parameters:**

- `$key`: The key to delete

**Returns:** Boolean indicating success

##### clear()

Removes all data from the store.

**Returns:** Number of deleted records

##### getAll($query = null, $count = null)

Retrieves multiple records.

**Parameters:**

- `$query`: Optional search query
- `$count`: Optional limit on results

**Returns:** Array of records

##### count()

Returns the number of records in the store.

**Returns:** Integer count

##### createIndex($indexName, $keyPath, $options = [])

Creates an index on the specified field.

**Parameters:**

- `$indexName`: Name of the index
- `$keyPath`: Field to index
- `$options`: Index options
  - `unique`: Whether the index should be unique

##### getByIndex($indexName, $value)

Retrieves records using an index.

**Parameters:**

- `$indexName`: Name of the index to use
- `$value`: Value to search for

**Returns:** Array of matching records

### NexaTransaction Class

#### Methods

##### objectStore($storeName)

Gets an object store within the transaction context.

**Parameters:**

- `$storeName`: Name of the store

**Returns:** `NexaObjectStore` instance

##### commit()

Commits the transaction.

**Returns:** Boolean indicating success

##### rollback()

Rolls back the transaction.

**Returns:** Boolean indicating success

## Examples

### Complete User Management Example

```php
<?php
use App\System\Storage\NexaIndexDB;

// Initialize database
$db = new NexaIndexDB('userApp', 1);

// Create users store
$userStore = $db->createObjectStore('users', [
    'keyPath' => 'id',
    'autoIncrement' => true
]);

// Create email index for unique constraint
$userStore->createIndex('emailIndex', 'email', ['unique' => true]);

// Add users
$user1Id = $userStore->add([
    'name' => 'Alice Johnson',
    'email' => 'alice@example.com',
    'department' => 'Engineering',
    'salary' => 85000
]);

$user2Id = $userStore->add([
    'name' => 'Bob Wilson',
    'email' => 'bob@example.com',
    'department' => 'Marketing',
    'salary' => 65000
]);

// Find user by email
$alice = $userStore->getByIndex('emailIndex', 'alice@example.com')[0];

// Update user
$alice['salary'] = 90000;
$userStore->put($alice);

// Get all users
$allUsers = $userStore->getAll();

// Transaction example
$transaction = $db->transaction(['users'], 'readwrite');
$txUserStore = $transaction->objectStore('users');

try {
    $txUserStore->add([
        'name' => 'Charlie Brown',
        'email' => 'charlie@example.com',
        'department' => 'Sales',
        'salary' => 55000
    ]);

    $transaction->commit();
    echo "Transaction completed successfully\n";
} catch (Exception $e) {
    $transaction->rollback();
    echo "Transaction failed: " . $e->getMessage() . "\n";
}

// Close database
$db->close();
?>
```

## Error Handling

The library throws exceptions for various error conditions:

- Database connection failures
- Missing object stores
- Invalid keys or data
- Transaction conflicts

Always wrap database operations in try-catch blocks:

```php
try {
    $userStore->add($userData);
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    // Handle error appropriately
}
```

## Best Practices

1. **Use Transactions**: For operations that modify multiple stores or require consistency
2. **Create Indexes**: For fields you'll frequently query
3. **Handle Exceptions**: Always implement proper error handling
4. **Close Connections**: Call `close()` when done with the database
5. **Use Meaningful Keys**: Choose appropriate key paths for your data structure
6. **Validate Data**: Ensure data integrity before storing

## File Structure

Database files are stored in:

```
dev/system/Storage/tabel/
├── myDatabase.db
├── userDatabase.db
└── ...
```

Each database is a single SQLite file containing all object stores and metadata.

## Integration with NexaController

NexaIndexDB is fully integrated into the NexaController class, providing seamless database operations within your controllers. All controllers that extend NexaController automatically have access to these database methods.

### Quick Start with NexaController

```php
<?php
namespace App\Controllers;
use App\System\NexaController;

class UserController extends NexaController
{
    public function index()
    {
        // Initialize common stores if needed
        $this->initializeCommonStores();

        // Store user data
        $userId = $this->storeData([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'admin'
        ]);

        // Retrieve all users
        $users = $this->getAllData('data');

        // Assign to template
        $this->setData('users', $users);
        $this->setData('current_user_id', $userId);
    }

    public function profile()
    {
        $userId = $this->getSlug(0); // Get user ID from URL
        $user = $this->getData($userId);

        if (!$user) {
            $this->redirect('/users');
            return;
        }

        $this->setData('user', $user);
    }
}
```

### Available NexaController Database Methods

#### Basic Database Operations

```php
// Get database instances
$db = $this->db();                           // Default app database
$customDb = $this->database('my_app', 2);    // Custom database with version

// Store management
$store = $this->createStore('users', ['keyPath' => 'id', 'autoIncrement' => true]);
$userStore = $this->getStore('users');
$this->deleteStore('old_store');

// Quick data operations
$id = $this->storeData(['name' => 'John']);   // Store data
$data = $this->getData($id);                  // Get data by ID
$this->updateData(['id' => $id, 'name' => 'Jane']); // Update data
$this->deleteData($id);                       // Delete data
```

#### Advanced Operations

```php
// Get all data with filters
$users = $this->getAllData('users');         // All users
$recent = $this->getAllData('users', null, 10); // Last 10 users
$search = $this->getAllData('users', 'john'); // Search users

// Count and clear operations
$count = $this->countData('users');          // Count users
$deleted = $this->clearStore('users');       // Clear all users

// Index operations
$this->createIndex('users', 'emailIndex', 'email', ['unique' => true]);
$users = $this->queryByIndex('users', 'emailIndex', 'john@example.com');
```

#### Transaction Support

```php
// Simple transaction
$result = $this->withTransaction(['users', 'orders'], function($tx) {
    $userStore = $tx->objectStore('users');
    $orderStore = $tx->objectStore('orders');

    $userId = $userStore->add(['name' => 'John', 'email' => 'john@example.com']);
    $orderId = $orderStore->add(['user_id' => $userId, 'total' => 100.00]);

    return ['user_id' => $userId, 'order_id' => $orderId];
});

// Manual transaction
$transaction = $this->transaction(['users'], 'readwrite');
try {
    $store = $transaction->objectStore('users');
    $store->add(['name' => 'Jane']);
    $transaction->commit();
} catch (Exception $e) {
    $transaction->rollback();
    throw $e;
}
```

#### User-Specific Data

```php
// Store user-specific data (automatically uses current user ID)
$this->storeUserData(['theme' => 'dark', 'language' => 'en']);
$preferences = $this->getUserData(1);
$allUserData = $this->getAllUserData();
```

#### Batch Operations

```php
$operations = [
    ['method' => 'add', 'data' => ['name' => 'User 1']],
    ['method' => 'add', 'data' => ['name' => 'User 2']],
    ['method' => 'delete', 'key' => 'old_user_id']
];
$results = $this->batchOperations('users', $operations);
```

#### Controller State Management

```php
// Store controller-specific state
$this->storeControllerState('last_page', 5);
$this->storeControllerState('filters', ['status' => 'active']);

// Retrieve controller state
$lastPage = $this->getControllerState('last_page', 1);
$filters = $this->getControllerState('filters', []);
```

### Practical Examples

#### User Registration and Login

```php
class AuthController extends NexaController
{
    public function register()
    {
        if ($this->isPost()) {
            $userData = [
                'name' => $this->getPost('name'),
                'email' => $this->getPost('email'),
                'password' => password_hash($this->getPost('password'), PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Create users store with email index if not exists
            try {
                $userStore = $this->getStore('users');
            } catch (Exception $e) {
                $userStore = $this->createStore('users', [
                    'keyPath' => 'id',
                    'autoIncrement' => true
                ]);
                $this->createIndex('users', 'emailIndex', 'email', ['unique' => true]);
            }

            // Check if email already exists
            $existingUsers = $this->queryByIndex('users', 'emailIndex', $userData['email']);
            if (!empty($existingUsers)) {
                $this->setFlash('error', 'Email already registered');
                return;
            }

            // Register user
            $userId = $this->storeData($userData);
            $this->setFlash('success', 'Registration successful');
            $this->redirect('/login');
        }
    }

    public function login()
    {
        if ($this->isPost()) {
            $email = $this->getPost('email');
            $password = $this->getPost('password');

            $users = $this->queryByIndex('users', 'emailIndex', $email);
            if (!empty($users) && password_verify($password, $users[0]['password'])) {
                $this->setUser($users[0]);
                $this->redirect('/dashboard');
            } else {
                $this->setFlash('error', 'Invalid credentials');
            }
        }
    }
}
```

#### Product Management

```php
class ProductController extends NexaController
{
    public function index()
    {
        // Initialize product store
        try {
            $productStore = $this->getStore('products');
        } catch (Exception $e) {
            $productStore = $this->createStore('products', [
                'keyPath' => 'id',
                'autoIncrement' => true
            ]);
            $this->createIndex('products', 'categoryIndex', 'category');
            $this->createIndex('products', 'statusIndex', 'status');
        }

        // Get products with pagination
        $page = $this->getQuery('page', 1);
        $category = $this->getQuery('category');

        if ($category) {
            $products = $this->queryByIndex('products', 'categoryIndex', $category);
        } else {
            $products = $this->getAllData('products');
        }

        $this->setData('products', $products);
        $this->setData('current_page', $page);
    }

    public function create()
    {
        if ($this->isPost()) {
            $productData = [
                'name' => $this->getPost('name'),
                'description' => $this->getPost('description'),
                'price' => floatval($this->getPost('price')),
                'category' => $this->getPost('category'),
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $productId = $this->storeData($productData);
            $this->setFlash('success', 'Product created successfully');
            $this->redirect('/products/' . $productId);
        }
    }

    public function update()
    {
        $productId = $this->getSlug(0);
        $product = $this->getData($productId);

        if (!$product) {
            $this->setFlash('error', 'Product not found');
            $this->redirect('/products');
            return;
        }

        if ($this->isPost()) {
            $product['name'] = $this->getPost('name');
            $product['description'] = $this->getPost('description');
            $product['price'] = floatval($this->getPost('price'));
            $product['category'] = $this->getPost('category');
            $product['updated_at'] = date('Y-m-d H:i:s');

            $this->updateData($product, $productId);
            $this->setFlash('success', 'Product updated successfully');
        }

        $this->setData('product', $product);
    }
}
```

#### Shopping Cart with Transactions

```php
class CartController extends NexaController
{
    public function addToCart()
    {
        $productId = $this->getPost('product_id');
        $quantity = $this->getPost('quantity', 1);
        $userId = $this->getUserId();

        if (!$userId) {
            $this->json(['error' => 'Please login first'], 401);
            return;
        }

        try {
            $result = $this->withTransaction(['products', 'cart'], function($tx) use ($productId, $quantity, $userId) {
                $productStore = $tx->objectStore('products');
                $cartStore = $tx->objectStore('cart');

                // Get product details
                $product = $productStore->get($productId);
                if (!$product) {
                    throw new Exception('Product not found');
                }

                // Add to cart
                $cartItem = [
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'product_name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => $quantity,
                    'total' => $product['price'] * $quantity,
                    'added_at' => date('Y-m-d H:i:s')
                ];

                return $cartStore->add($cartItem);
            });

            $this->json(['success' => true, 'cart_id' => $result]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function getCart()
    {
        $userId = $this->getUserId();
        $cartItems = $this->queryByIndex('cart', 'userIndex', $userId);

        $total = array_sum(array_column($cartItems, 'total'));

        $this->json([
            'items' => $cartItems,
            'total' => $total,
            'count' => count($cartItems)
        ]);
    }
}
```

### Database Utilities and Debugging

```php
class AdminController extends NexaController
{
    public function databaseStatus()
    {
        // Debug database information
        $debug = $this->debugDatabase();
        $dbInfo = $this->getDatabaseInfo();

        $this->setData('debug_info', $debug);
        $this->setData('database_info', $dbInfo);
    }

    public function clearCache()
    {
        // Clear specific store
        $cleared = $this->clearStore('cache');
        $this->setFlash('success', "Cleared {$cleared} cache entries");

        // Or close database connections to free memory
        $this->closeDatabase();

        $this->redirectBack();
    }

    public function exportData()
    {
        // Get all data from multiple stores
        $data = [
            'users' => $this->getAllData('users'),
            'products' => $this->getAllData('products'),
            'orders' => $this->getAllData('orders')
        ];

        // Export as JSON
        $this->json($data);
    }
}
```

### Best Practices with NexaController

1. **Initialize Stores Early**: Use `initializeCommonStores()` in your constructor or early methods
2. **Use Transactions for Related Operations**: Always wrap related database operations in transactions
3. **Handle Errors Gracefully**: Use try-catch blocks and provide user feedback
4. **Leverage User-Specific Data**: Use `storeUserData()` for user preferences and settings
5. **Create Indexes for Frequently Queried Fields**: Email, status, category, etc.
6. **Close Connections When Done**: Call `closeDatabase()` in long-running operations

### Performance Tips

- Use indexes for frequently queried fields
- Batch operations when inserting multiple records
- Use transactions for consistency and performance
- Close database connections when not needed
- Use specific store names instead of the default 'data' store for better organization
