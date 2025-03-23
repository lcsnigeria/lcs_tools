# LCS_DBManager Class Documentation

## Overview
The `LCS_DBManager` class is a robust and flexible database management utility designed to simplify database operations. It supports both PDO and MySQLi as SQL managers, providing compatibility, error handling, and a wide range of features for querying, fetching, and managing database connections.

---

## Class: `LCS_DBManager`

### Namespace
`LCSNG_EXT\Database`

### Properties

#### Public Properties
- **`$credentials`** *(string|null)*: Database credentials in DSN format.
- **`$options`** *(array)*: Connection options for PDO.
- **`$auto_charset`** *(bool)*: Enables automatic charset configuration.
- **`$insert_id`** *(int|null)*: ID of the last inserted record.
- **`$record_id`** *(int|null)*: ID of the last modified or fetched record.
- **`$prefix`** *(string|null)*: Optional table prefix for dynamic query generation.
- **`$sql_manager`** *(string|null)*: Selected SQL manager (`PDO` or `MySQLi`).
- **`$timezone`** *(string)*: Timezone for database queries (default: `+01:00`).
- **`$FETCH_MODE`** *(string)*: Fetch mode for query results (`OBJECT` or `ARRAY`).
- **`$last_error`** *(string|null)*: Stores the last error message for debugging.

#### Private Properties
- **`$host`** *(string|null)*: Database host.
- **`$username`** *(string|null)*: Database username.
- **`$password`** *(string|null)*: Database password.
- **`$dbname`** *(string|null)*: Database name.
- **`$port`** *(int|null)*: Database port.
- **`$socket`** *(string|null)*: Database socket path.
- **`$charset`** *(string)*: Character set for the connection (default: `utf8mb4`).
- **`$collate`** *(string)*: Collation for the connection (default: `utf8mb4_general_ci`).
- **`$dsn`** *(string|null)*: Constructed DSN string for PDO.
- **`$connection`** *(PDO|mysqli|null)*: Active database connection instance.
- **`$connection_error`** *(string|null)*: Stores any connection errors encountered.
- **`$inTransaction`** *(bool)*: Tracks active transactions.

---

### Constants
- **`SQL_MANAGERS`** *(array)*: Supported SQL managers (`PDO`, `MySQLi`).

---

### Constructor

#### `__construct(string $credentials, array $options = [], string $sql_manager = "PDO")`
Initializes the database manager with credentials, options, and the SQL manager.

**Parameters:**
- `$credentials`: DSN string (e.g., `mysql:host=localhost;dbname=testdb;charset=utf8mb4`).
- `$options`: Optional configuration settings for the connection.
- `$sql_manager`: SQL manager to use (`PDO` or `MySQLi`).

**Throws:**
- `Exception` if credentials are invalid or the SQL manager is unsupported.

---

### Public Methods

#### Connection Management
- **`is_connected(): bool`**  
  Checks if the database connection is active.

- **`switch_sql_manager(string $sql_manager): void`**  
  Switches the SQL manager and reconnects.

- **`revert_sql_manager(): void`**  
  Reverts to the previous SQL manager.

- **`default_sql_manager(): void`**  
  Resets the SQL manager to the default (`PDO`).

- **`connection_error(): string`**  
  Retrieves the connection error message.

---

#### Query Execution
- **`query(string $sql, ...$values): mixed`**  
  Executes a SQL query with optional parameters.

- **`get_results(string $sql, ...$values): array|object|false`**  
  Executes a query and fetches the results.

- **`get_row(string $sql, ...$params): object|array|null`**  
  Fetches a single row from the result set.

- **`get_col(string $sql, ...$params): array|null`**  
  Fetches a column of data from the result set.

- **`get_var(string $sql, ...$params): mixed|null`**  
  Fetches a single value from the result set.

---

#### CRUD Operations
- **`insert(string $table, array $data): int|null`**  
  Inserts a new row into a table.

- **`update(string $table, array $data, array $where): void`**  
  Updates rows in a table.

- **`delete(string $table, array $where): bool`**  
  Deletes rows from a table.

- **`replace(string $table, array $data): void`**  
  Inserts or updates a row in a table.

---

#### Schema Management
- **`is_table_exist(string $table_name): bool`**  
  Checks if a table exists in the database.

- **`is_table_column_exist(string $table_name, string $column_name): bool`**  
  Checks if a column exists in a table.

- **`get_table_constraints(string $tableName): array`**  
  Retrieves constraints for a table.

- **`is_constraint_name_exist(string $tableName, string $constraintName): bool`**  
  Checks if a constraint exists in a table.

---

#### Transactions
- **`startTransaction(): void`**  
  Starts a new database transaction.

- **`commit(): void`**  
  Commits the current transaction.

- **`rollBack(): void`**  
  Rolls back the current transaction.

---

#### Utility Methods
- **`sanitize_data(mixed $data): mixed`**  
  Sanitizes input data for database operations.

- **`get_charset_collate(): string`**  
  Retrieves the charset and collation settings.

- **`get_database_type(): string`**  
  Retrieves the type of the database server.

---

### Private Methods
- **`build_credentials(): void`**  
  Parses and builds the database credentials.

- **`validate_options(array $options): array`**  
  Validates and merges connection options.

- **`connect_db(): void`**  
  Establishes a connection to the database.

- **`disconnect_db(): void`**  
  Disconnects from the database.

- **`prepare(string $sql, array $values, string|null $specifiers): mixed`**  
  Prepares a SQL statement for execution.

- **`fetch_all(string $sql, bool $prepare, array|null $values, string|null $specifiers): array`**  
  Fetches all rows from a query result.

- **`contains_ph(string $sql_string): bool`**  
  Checks if a SQL string contains placeholders.

- **`validate_sql(string $sql, array $params): string`**  
  Validates the SQL query and placeholders.

- **`build_specifiers(...$params): string`**  
  Builds type specifiers for MySQLi prepared statements.

- **`flatten_array(array $array): array`**  
  Flattens a multi-dimensional array.

- **`set_last_error(string $message): void`**  
  Sets the last error message.

---

### Example Usage

```php
use LCSNG_EXT\Database\LCS_DBManager;

$credentials = "mysql:host=localhost;dbname=testdb;charset=utf8mb4;username=root;password=secret";
$options = ['FETCH_MODE' => 'OBJECT'];
$sql_manager = 'PDO';

$db = new LCS_DBManager($credentials, $options, $sql_manager);

if ($db->is_connected()) {
    echo "Connected to the database!";
} else {
    echo "Connection failed: " . $db->connection_error();
}
```

---

This documentation provides an overview of the `LCS_DBManager` class, its properties, methods, and usage examples.

TemporalJustice: provide full docs on how to use for public consumption

GitHub Copilot: Here is a comprehensive documentation for the `LCS_DBManager` class, designed for public consumption:

---

# LCS_DBManager Class Documentation

## Overview
The `LCS_DBManager` class is a flexible and robust database management utility that simplifies database operations. It supports both **PDO** and **MySQLi** as SQL managers, making it compatible with a wide range of database systems. The class provides features for querying, fetching, managing transactions, and handling database schema operations.

---

## Features
- **Dual SQL Manager Support**: Choose between `PDO` and `MySQLi`.
- **Dynamic Query Execution**: Execute queries with or without prepared statements.
- **CRUD Operations**: Simplified methods for `INSERT`, `UPDATE`, `DELETE`, and `REPLACE`.
- **Transaction Management**: Start, commit, and roll back transactions.
- **Schema Management**: Check table/column existence, retrieve constraints, and restore tables.
- **Error Handling**: Detailed error messages for debugging.
- **Customizable Fetch Modes**: Fetch results as objects or associative arrays.

---

## Installation
1. Include the LCS_DBManager.php file in your project.
2. Ensure your project has access to either the PDO or MySQLi PHP extensions.

---

## Usage

### 1. Initialization
To use the `LCS_DBManager` class, you need to provide database credentials in DSN format, optional connection options, and the SQL manager type.

```php
use LCSNG_EXT\Database\LCS_DBManager;

// Example credentials
$credentials = "mysql:host=localhost;dbname=testdb;charset=utf8mb4;username=root;password=secret";

// Optional connection options
$options = [
    'FETCH_MODE' => 'OBJECT', // Fetch results as objects (default: OBJECT)
];

// Initialize the database manager
$db = new LCS_DBManager($credentials, $options, 'PDO');
```

---

### 2. Checking Connection
You can check if the database connection is active using the `is_connected()` method.

```php
if ($db->is_connected()) {
    echo "Connected to the database!";
} else {
    echo "Connection failed: " . $db->connection_error();
}
```

---

### 3. Query Execution
#### Execute a Query
Use the `query()` method to execute a SQL query.

```php
$sql = "CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))";
$db->query($sql);
```

#### Fetch Results
Use the `get_results()` method to fetch multiple rows.

```php
$sql = "SELECT * FROM users";
$results = $db->get_results($sql);

foreach ($results as $user) {
    echo $user->name; // Assuming FETCH_MODE is OBJECT
}
```

#### Fetch a Single Row
Use the `get_row()` method to fetch a single row.

```php
$sql = "SELECT * FROM users WHERE id = ?";
$user = $db->get_row($sql, 1);
echo $user->name;
```

#### Fetch a Single Value
Use the `get_var()` method to fetch a single value.

```php
$sql = "SELECT COUNT(*) FROM users";
$userCount = $db->get_var($sql);
echo "Total users: $userCount";
```

---

### 4. CRUD Operations
#### Insert Data
Use the `insert()` method to insert a new row into a table.

```php
$data = ['name' => 'John Doe'];
$insertId = $db->insert('users', $data);
echo "Inserted user with ID: $insertId";
```

#### Update Data
Use the `update()` method to update existing rows.

```php
$data = ['name' => 'Jane Doe'];
$where = ['id' => 1];
$db->update('users', $data, $where);
```

#### Delete Data
Use the `delete()` method to delete rows.

```php
$where = ['id' => 1];
$db->delete('users', $where);
```

#### Replace Data
Use the `replace()` method to insert or update a row.

```php
$data = ['id' => 1, 'name' => 'John Smith'];
$db->replace('users', $data);
```

---

### 5. Transaction Management
#### Start a Transaction
```php
$db->startTransaction();
```

#### Commit a Transaction
```php
$db->commit();
```

#### Roll Back a Transaction
```php
$db->rollBack();
```

---

### 6. Schema Management
#### Check if a Table Exists
```php
$tableExists = $db->is_table_exist('users');
echo $tableExists ? "Table exists" : "Table does not exist";
```

#### Check if a Column Exists
```php
$columnExists = $db->is_table_column_exist('users', 'name');
echo $columnExists ? "Column exists" : "Column does not exist";
```

#### Retrieve Table Constraints
```php
$constraints = $db->get_table_constraints('users');
print_r($constraints);
```

#### Restore a Table
```php
$tableData = [
    'table_name' => 'users',
    'structure' => [
        ['Field' => 'id', 'Type' => 'INT AUTO_INCREMENT PRIMARY KEY'],
        ['Field' => 'name', 'Type' => 'VARCHAR(255)'],
    ],
    'rows' => [
        ['id' => 1, 'name' => 'John Doe'],
        ['id' => 2, 'name' => 'Jane Doe'],
    ],
];
$db->restore_table($tableData);
```

---

### 7. Utility Methods
#### Sanitize Data
```php
$sanitized = $db->sanitize_data("O'Reilly");
echo $sanitized; // Escaped string
```

#### Get Charset and Collation
```php
$charsetCollate = $db->get_charset_collate();
echo $charsetCollate; // e.g., "CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
```

#### Get Database Type
```php
$dbType = $db->get_database_type();
echo "Database type: $dbType";
```

---

### 8. Error Handling
#### Retrieve the Last Error
```php
if (!$db->is_connected()) {
    echo "Error: " . $db->last_error;
}
```

---

## Example Workflow
```php
use LCSNG_EXT\Database\LCS_DBManager;

$credentials = "mysql:host=localhost;dbname=testdb;charset=utf8mb4;username=root;password=secret";
$db = new LCS_DBManager($credentials);

if ($db->is_connected()) {
    // Insert a new user
    $db->insert('users', ['name' => 'Alice']);

    // Fetch all users
    $users = $db->get_results("SELECT * FROM users");
    foreach ($users as $user) {
        echo $user->name;
    }
} else {
    echo "Connection failed: " . $db->connection_error();
}
```

---

## Notes
- Ensure the database credentials are correct and the database server is running.
- Use prepared statements for queries with user input to prevent SQL injection.
- Always handle exceptions when performing database operations.

---

This documentation provides a complete guide to using the `LCS_DBManager` class for managing database operations efficiently.