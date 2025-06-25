# Mysqli2 Class Documentation

## Overview

Mysqli2 is an enhanced wrapper around PHP's native MySQLi extension that provides simplified prepared statement execution, better error handling, and development/production mode switching. The class extends mysqli, inheriting all native MySQLi methods while adding streamlined functionality.

## Key Features

- **Singleton Pattern**: Single database connection instance
- **Development/Production Modes**: Configurable error reporting
- **Simplified Prepared Statements**: Streamlined syntax for common operations
- **Smart Return Values**: Context-aware return types based on SQL operation
- **Exception Handling**: Optional exception throwing with detailed error information
- **Database Utilities**: Built-in methods for checking tables, collations, and charset configuration

## Installation & Setup

### Basic Initialization

```php
// Enable development mode (verbose errors)
Mysqli2::isDev(true);

// Get singleton instance with connection parameters
$mysqli = Mysqli2::getInstance($mysql_host, $mysql_port, $mysql_user, $mysql_password, $mysql_database);

// Set character encoding
$mysqli->set_charset("utf8");

// Check connection
if ($mysqli->connect_errno) {
    echo 'Failed to connect to MySQL: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error; 
    exit();
}
```

### Mode Configuration

```php
// Development mode - shows detailed errors
Mysqli2::isDev(true);

// Production mode - suppresses detailed errors  
Mysqli2::isProd(true);
// or
Mysqli2::isDev(false);
```

## Core Methods

### execute($sql, $types, $params)

Main method for executing prepared statements. Returns different values based on SQL operation type. All mysqli_sql_exception errors are automatically converted to DatabaseException.

**Parameters:**
- `$sql`: SQL query string OR array format `[$sql, $types, $params]`
- `$types`: Type definition string (`i`=integer, `s`=string, `d`=double, `b`=blob)
- `$params`: Array of parameters to bind

**Return Values:**
- **INSERT**: Returns `last_insert_id` or `affected_rows` if no auto-increment
- **UPDATE/DELETE**: Returns `affected_rows`
- **SELECT**: Returns array of associative arrays (result set)
- **Other**: Returns `affected_rows`
- **Error**: Returns `false`

### Examples

#### INSERT Operations

```php
// Insert single record
$user_id = $mysqli->execute(
    "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
    'ssi',
    ['John Doe', 'john@example.com', 30]
);
echo "New user ID: " . $user_id;

// Insert with array syntax
$user_id = $mysqli->execute([
    "INSERT INTO users (name, email) VALUES (?, ?)",
    'ss',
    ['Jane Smith', 'jane@example.com']
]);
```

#### SELECT Operations

```php
// Select multiple records
$users = $mysqli->execute(
    "SELECT id, name, email FROM users WHERE age > ?",
    'i',
    [25]
);

foreach ($users as $user) {
    echo $user['name'] . ' - ' . $user['email'] . "\n";
}

// Select with multiple parameters
$active_users = $mysqli->execute(
    "SELECT * FROM users WHERE status = ? AND created_date > ?",
    'ss',
    ['active', '2024-01-01']
);
```

#### UPDATE Operations

```php
// Update records
$affected = $mysqli->execute(
    "UPDATE users SET email = ? WHERE id = ?",
    'si',
    ['newemail@example.com', 123]
);
echo "Updated {$affected} records";

// Update multiple fields
$affected = $mysqli->execute(
    "UPDATE users SET name = ?, email = ?, age = ? WHERE id = ?",
    'ssii',
    ['Updated Name', 'updated@email.com', 35, 123]
);
```

#### DELETE Operations

```php
// Delete records
$deleted = $mysqli->execute(
    "DELETE FROM users WHERE age < ?",
    'i',
    [18]
);
echo "Deleted {$deleted} records";

// Delete with multiple conditions
$deleted = $mysqli->execute(
    "DELETE FROM users WHERE status = ? AND last_login < ?",
    'ss',
    ['inactive', '2023-01-01']
);
```

### execute1($sql, $types, $params, $return)

Convenience method for queries that should return a single row or value.

**Parameters:**
- `$sql`, `$types`, `$params`: Same as execute()
- `$return`: Return mode
  - `0`: First column value only
  - `true`: Full row or `null` if empty
  - `'default'`: Always return first row (throws error if empty)

### Examples

#### Single Value Retrieval

```php
// Get count
$count = $mysqli->execute1(
    "SELECT COUNT(*) FROM users WHERE status = ?",
    's',
    ['active'],
    0  // Return first column value only
);
echo "Active users: " . $count;

// Get single field value
$email = $mysqli->execute1(
    "SELECT email FROM users WHERE id = ?",
    'i',
    [123],
    0
);
echo "User email: " . $email;
```

#### Single Row Retrieval

```php
// Get full row, return null if not found
$user = $mysqli->execute1(
    "SELECT * FROM users WHERE id = ?",
    'i',
    [123],
    true  // Return full row or null
);

if ($user) {
    echo "Found: " . $user['name'];
} else {
    echo "User not found";
}

// Get first row, error if empty (default behavior)
$user = $mysqli->execute1(
    "SELECT * FROM users WHERE status = ?",
    's',
    ['active']
    // 'default' is implied
);
echo "First active user: " . $user['name'];
```

### query($query, $resultmode)

Direct query execution that overrides mysqli::query to provide consistent error handling through DatabaseException.

**Parameters:**
- `$query`: SQL query string to execute
- `$resultmode`: Result mode (MYSQLI_STORE_RESULT or MYSQLI_USE_RESULT)

**Return Values:**
- **SELECT**: Returns mysqli_result object
- **Other operations**: Returns true on success, false on error

**Example:**
```php
try {
    $result = $mysqli->query("SELECT * FROM users");
    while ($row = $result->fetch_assoc()) {
        echo $row['name'] . "\n";
    }
} catch (DatabaseException $e) {
    echo "Query failed: " . $e->getMessage();
}
```

## Database Utility Methods

### return_charset_and_collate($c)

Returns optimal charset and collation pairs based on MySQL server capabilities, with priority for regional variations.

**Parameters:**
- `$c`: Optional array to override default collations (e.g., `['utf8' => 'utf8_custom_ci']`)

**Return Value:**
- Array with charset => collation mappings

**Priority Order:**
1. Swedish collations (`utf8_swedish_ci`, `utf8mb4_swedish_ci`)
2. Danish collations (`utf8_danish_ci`, `utf8mb4_danish_ci`) 
3. General collations (`utf8_general_ci`, `utf8mb4_general_ci`)
4. Custom overrides from `$c` parameter (if collation exists)

**Example:**
```php
// Get default charsets
$charsets = $mysqli->return_charset_and_collate();
// Returns: ['utf8' => 'utf8_swedish_ci', 'utf8mb4' => 'utf8mb4_swedish_ci']

// Override specific charset
$charsets = $mysqli->return_charset_and_collate(['utf8' => 'utf8_unicode_ci']);
// Uses utf8_unicode_ci if it exists, falls back to detected optimal otherwise
```

### doesCollationExist($collation)

Checks if a specific collation exists in the MySQL server.

**Parameters:**
- `$collation`: Name of collation to check

**Return Value:**
- `true` if collation exists, `false` otherwise

**Example:**
```php
if ($mysqli->doesCollationExist('utf8mb4_unicode_ci')) {
    echo "Unicode collation available";
} else {
    echo "Unicode collation not supported";
}
```

### table_exist($table_name)

Checks if a table exists in the current database.

**Parameters:**
- `$table_name`: Name of the table to check

**Return Value:**
- `true` if table exists, `false` otherwise

**Example:**
```php
if ($mysqli->table_exist('users')) {
    echo "Users table exists";
} else {
    echo "Users table not found";
}

// Use in conditional table creation
if (!$mysqli->table_exist('sessions')) {
    $mysqli->query("CREATE TABLE sessions (...)");
}
```

## Error Handling

### Exception Mode (Default)

```php
// Exceptions are enabled by default
try {
    $result = $mysqli->execute("SELECT * FROM nonexistent_table");
} catch (DatabaseException $e) {
    echo "Database Error: " . $e->getMessage();
    echo "Query: " . $e->getSqlQuery();
    echo "Error Number: " . $e->getSqlErrno();
}
```

### Return Value Mode

```php
// Disable exceptions
Mysqli2::setUseExceptions(false);

$result = $mysqli->execute("SELECT * FROM users WHERE id = ?", 'i', [999]);
if ($result === false) {
    $error = $mysqli->getLastError();
    echo "Error: " . $error['error'];
    echo "Query: " . $error['query'];
}
```

## Advanced Usage

### Parameter Convenience

```php
// Single parameter - can pass directly instead of array
$user = $mysqli->execute1(
    "SELECT * FROM users WHERE id = ?",
    'i',
    123,  // Single value, not array
    true
);
```

### Complex Queries

```php
// JOIN queries
$user_posts = $mysqli->execute(
    "SELECT u.name, p.title, p.created_date 
     FROM users u 
     JOIN posts p ON u.id = p.user_id 
     WHERE u.status = ? AND p.published = ?",
    'si',
    ['active', 1]
);

// Subqueries
$popular_users = $mysqli->execute(
    "SELECT * FROM users 
     WHERE id IN (
         SELECT user_id FROM posts 
         GROUP BY user_id 
         HAVING COUNT(*) > ?
     )",
    'i',
    [10]
);
```

### Dynamic Query Building

```php
// Array format for dynamic queries
$conditions = [];
$types = '';
$params = [];

if ($name_filter) {
    $conditions[] = "name LIKE ?";
    $types .= 's';
    $params[] = "%{$name_filter}%";
}

if ($min_age) {
    $conditions[] = "age >= ?";
    $types .= 'i';
    $params[] = $min_age;
}

$where_clause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$sql = "SELECT * FROM users {$where_clause}";

$users = $mysqli->execute($sql, $types, $params);
```

### Charset Configuration

```php
// Check optimal charset configuration
$charsets = $mysqli->return_charset_and_collate();
$optimal_charset = key($charsets); // 'utf8' or 'utf8mb4'
$optimal_collation = current($charsets);

// Set connection charset
$mysqli->set_charset($optimal_charset);

// Use in table creation
$mysqli->query("CREATE TABLE example (
    id INT PRIMARY KEY,
    name VARCHAR(255)
) CHARACTER SET {$optimal_charset} COLLATE {$optimal_collation}");
```

## Utility Methods

```php
// Check version
echo $mysqli->getVersion(); // Returns: 2.0.0

// Get connection instance (reuses existing)
$same_instance = Mysqli2::getInstance();

// Set additional options
Mysqli2::setOptions([
    'host' => 'new_host',
    'port' => 3307
]);

// Check mode
if (Mysqli2::isDev()) {
    echo "Development mode enabled";
}
```

## Migration from Standard MySQLi

```php
// Old mysqli way:
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// New Mysqli2 way:
$users = $mysqli->execute(
    "SELECT * FROM users WHERE id = ?",
    'i',
    [$user_id]
);
```

## Error Handling Best Practices

1. **Use try-catch in production** for graceful error handling
2. **Enable development mode during development** for detailed debugging
3. **Check return values** when exceptions are disabled
4. **Use execute1() with appropriate return modes** for single-value queries
5. **Validate table existence** before performing operations on dynamic tables

## Type Reference

- `i` - Integer
- `s` - String  
- `d` - Double/Float
- `b` - Blob (binary data)

## Common Patterns

### User Authentication

```php
$user = $mysqli->execute1(
    "SELECT id, password_hash FROM users WHERE email = ?",
    's',
    [$email],
    true
);

if ($user && password_verify($password, $user['password_hash'])) {
    // Login successful
    return $user['id'];
}
```

### Pagination

```php
$total = $mysqli->execute1(
    "SELECT COUNT(*) FROM posts WHERE status = ?",
    's',
    ['published'],
    0
);

$posts = $mysqli->execute(
    "SELECT * FROM posts WHERE status = ? ORDER BY created_date DESC LIMIT ? OFFSET ?",
    'sii',
    ['published', $limit, $offset]
);
```

### Transaction Example

```php
$mysqli->autocommit(false);
try {
    $user_id = $mysqli->execute(
        "INSERT INTO users (name, email) VALUES (?, ?)",
        'ss',
        ['John Doe', 'john@example.com']
    );
    
    $mysqli->execute(
        "INSERT INTO user_profiles (user_id, bio) VALUES (?, ?)",
        'is',
        [$user_id, 'User biography']
    );
    
    $mysqli->commit();
} catch (Exception $e) {
    $mysqli->rollback();
    throw $e;
}
$mysqli->autocommit(true);
```

### Database Setup Verification

```php
// Check charset support and table existence
$charsets = $mysqli->return_charset_and_collate();
echo "Using charset: " . key($charsets) . " with collation: " . current($charsets) . "\n";

$required_tables = ['users', 'posts', 'sessions'];
foreach ($required_tables as $table) {
    if (!$mysqli->table_exist($table)) {
        echo "Warning: Table '{$table}' does not exist\n";
    }
}

// Verify specific collation support
if ($mysqli->doesCollationExist('utf8mb4_unicode_520_ci')) {
    echo "Advanced Unicode collation available\n";
}
```