<?php
namespace LCSNG\Tools\Database;

use LCSNG\Tools\Debugging\Logs;

/**
 * Class LCS_DBManager
 *
 * A robust, flexible, and unified database management class for PHP applications.
 * Supports both PDO and MySQLi drivers, providing a consistent API for querying,
 * fetching, and manipulating database records. Designed for reliability, error handling,
 * and compatibility with multiple SQL dialects.
 *
 * ## Features
 * - **Dual SQL Manager Support:** Seamlessly switch between PDO and MySQLi.
 * - **Connection Management:** Handles DSN parsing, connection setup, and error reporting.
 * - **Query Execution:** Supports direct queries and prepared statements with automatic placeholder validation.
 * - **CRUD Operations:** Simplifies insert, update, delete, and replace operations.
 * - **Result Fetching:** Flexible fetch modes (OBJECT/ARRAY), single value, row, or column retrieval.
 * - **Transaction Control:** Start, commit, and rollback transactions with state tracking.
 * - **Schema Inspection:** Check for table/column existence, retrieve primary keys, constraints, and table structure.
 * - **Sanitization:** Built-in data sanitization for safe SQL operations.
 * - **Error Handling:** Configurable error reporting (throw or log).
 * - **Charset/Collation:** Automatic or manual charset/collation management.
 * - **Extensible:** Easily adaptable for other SQL dialects and advanced features.
 *
 * ## Usage Example
 * ```php
 * $creds = "mysql:host=localhost;dbname=testdb;charset=utf8mb4;username=demo;password=secret";
 * $db = new LCS_DBManager($creds, ['FETCH_MODE' => 'OBJECT'], 'PDO');
 * if ($db->is_connected()) {
 *     $results = $db->get_results("SELECT * FROM users WHERE status = ?", 'active');
 *     foreach ($results as $user) {
 *         echo $user->name;
 *     }
 * }
 * ```
 *
 * ## Constructor Parameters
 * @param string $credentials DSN string (e.g., "mysql:host=localhost;dbname=testdb;charset=utf8mb4;username=demo;password=secret").
 * @param array $options Connection options (PDO attributes, fetch mode, etc.).
 * @param string $sql_manager SQL manager: 'PDO' or 'MySQLi'.
 * @param bool $throwError Whether to throw exceptions or just log errors.
 *
 * ## Key Methods
 * - `get_results($sql, ...$values)`: Fetches multiple rows.
 * - `get_row($sql, ...$values)`: Fetches a single row.
 * - `get_var($sql, ...$values)`: Fetches a single value.
 * - `insert($table, $data)`: Inserts a row.
 * - `update($table, $data, $where)`: Updates rows.
 * - `delete($table, $where)`: Deletes rows.
 * - `replace($table, $data)`: Inserts or updates a row.
 * - `get_id($table, $where)`: Gets primary key(s).
 * - `is_table_exist($table_name)`: Checks if a table exists.
 * - `is_table_column_exist($table, $column)`: Checks if a column exists.
 * - `get_table_data($table)`: Gets table structure and data.
 * - `get_table_constraints($table)`: Gets table constraints.
 * - `is_constraint_name_exist($table, $constraint)`: Checks for constraint existence.
 * - `startTransaction()`, `commit()`, `rollBack()`: Transaction control.
 * - `sanitize_data($data)`: Sanitizes input for SQL.
 * - `connection_error()`: Returns last connection error.
 *
 * ## Error Handling
 * Errors are reported via the `Logs` class. Set `$throwError` to control whether exceptions are thrown or only logged.
 *
 * ## Extensibility
 * Designed for easy extension to other SQL dialects and advanced schema operations.
 *
 * @package LCSNG\Tools\Database
 */
class LCS_DBManager 
{
    /** @var array $DB_PLATFORMS Supported database platforms. */
    CONST DB_PLATFORMS = ['mysql', 'pgsql', 'sqlite', 'sqlsrv'];

    /** @var bool $throwError Whether to throw exceptions on errors or just log them. */
    public $throwError = true;

    /** @var string|null $credentials Database credentials string in DSN format. */
    public $credentials;

    /** @var array $options Connection options for PDO. */
    public $options = [];

    /** @var bool $auto_charset Whether to enable automatic charset configuration. */
    public $auto_charset = false;

    /** @var int|null $insert_id The ID of the last inserted record. */
    public $insert_id;

    /** @var int|null $record_id The ID of the last modified or fetched record. */
    public $record_id;

    /** @var string|null $prefix Optional table prefix for dynamic query generation. */
    public $prefix;

    /** @var string|null $sql_manager Selected SQL manager (PDO or MySQLi). */
    public $sql_manager;

    private $previous_sql_manager;

    /** @var string|null $host Database host. */
    private $host;

    /** @var string|null $username Database username. */
    private $username = null;

    /** @var string|null $password Database password. */
    private $password = null;

    /** @var string|null $dbname Database name. */
    private $dbname = "";

    /** @var int|null $port Database port. */
    private $port = null;

    /** @var string|null $socket Database socket path. */
    private $socket = null;

    /** @var string|null $mysql_engine MySQL engine type (default: InnoDB). */
    private $mysql_engine = 'InnoDB';

    /** @var string $charset Character set for the connection (default: utf8mb4). */
    private $charset = 'utf8mb4';

    /** @var string $collate Collation for the connection (default: utf8mb4_general_ci). */
    private $collate = 'utf8mb4_general_ci';

    /** @var string|null $dsn Constructed DSN string for PDO. */
    private $dsn;

    /** @var string $timezone Timezone for db queries (default: +01:00). */
    public $timezone = '+01:00';

    /** @var string $FETCH_MODE Fetch mode for query results (e.g., OBJECT or ARRAY). */
    public $FETCH_MODE = 'OBJECT';

    /** @var PDO|mysqli|null $connection Active database connection instance. */
    private $connection = null;

    /** @var string|null $connection_error Stores any connection errors encountered. */
    private $connection_error;

    /** @var string|null $last_error Stores the last error message for debugging. */
    public $last_error = null;

    /** @var bool $inTransaction Flag to track active transactions. */
    private $inTransaction = false;

    /**
     * Tracks nested transaction depth (for PDO savepoints)
     *
     * @var int
     */
    private $transactionDepth = 0;

    /** @var array $supported_placeholders Supported SQL placeholders for prepared statements. */
    const SQL_MANAGERS = ['PDO', 'MySQLi'];

    /**
     * LCS_DBManager constructor.
     * 
     * Initializes the database manager with required credentials, options, and SQL manager.
     *
     * This constructor establishes the necessary configuration for database connections,
     * including validating credentials, setting connection options, and initializing the
     * appropriate SQL manager (PDO or MySQLi). It also validates and sets the fetch mode for query results.
     *
     * @param string $credentials The database credentials in DSN format (e.g., "mysql:host=localhost;dbname=testdb;charset=utf8mb4").
     * @param array $options Optional configuration settings for the database connection. Acceptable options include:
     *                       - `PDO::ATTR_ERRMODE` (default: `PDO::ERRMODE_EXCEPTION`): Error reporting mode.
     *                       - `PDO::ATTR_DEFAULT_FETCH_MODE` (default: `PDO::FETCH_OBJ`): Fetch mode for results.
     *                       - `PDO::ATTR_EMULATE_PREPARES` (default: `false`): Use native or emulated prepared statements.
     *                       - `PDO::MYSQL_ATTR_INIT_COMMAND` (default: "SET NAMES utf8mb4"): Initialization command for MySQL.
     *                       - `FETCH_MODE` (custom option): Query result fetch mode (`OBJECT` or `ARRAY`).
     * @param string $sql_manager The SQL manager to use. Acceptable values are:
     *                            - `PDO` (default): PHP Data Objects.
     *                            - `MySQLi`: MySQL Improved.
     * 
     * @param bool $throwError Whether to throw exceptions on errors (default: true). If false, errors are logged but not thrown.
     *
     * @throws Exception If credentials are invalid, the SQL manager is unsupported, or an invalid fetch mode is provided.
     */
    public function __construct( string $credentials, array $options = [], string $sql_manager = "PDO", $throwError = true) 
    {
        // Set error handling preference
        $this->throwError = $throwError;

        // Validate credentials
        if (empty($credentials) || !is_string($credentials)) {
            $this->reportError("Config error: Database credentials are required and must be a valid string.", 1);
        }

        // Set credentials variable
        $this->credentials = $credentials;

        // Validates manager
        if (!in_array($sql_manager, self::SQL_MANAGERS)) {
            $this->reportError("Invalid SQL Manager '$sql_manager'. Use 'PDO' or 'MySQLi'.", 2);
        }

        // Set manager variable
        $this->sql_manager = $sql_manager;

        // Build credentials
        $this->build_credentials();

        // Validate and merge options
        $this->options = $this->validate_options($options);

        // Establish the database connection
        $this->connect();

        // Set timezone if connected
        if ($this->is_connected()) {
            $this->connection->query("SET SESSION time_zone = '{$this->timezone}';");
        }
    }

    /**
     * Validates and merges the options array with default PDO settings.
     *
     * Ensures that the provided options are valid and resolves the custom FETCH_MODE
     * into the corresponding PDO fetch mode.
     *
     * @param array $options The custom options to validate and merge.
     * @return array The validated and merged options.
     * @throws Exception If FETCH_MODE is invalid or not supported.
     */
    private function validate_options(array $options): array {
        // Default PDO options
        $defaultOptions = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
            \PDO::ATTR_EMULATE_PREPARES => true,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}",
        ];

        // Merge options with defaults; keeping the index as it is
        $options = $options + $defaultOptions;

        // Validate FETCH_MODE if provided
        if (isset($options['FETCH_MODE'])) {
            $fetchModes = ['OBJECT' => \PDO::FETCH_OBJ, 'ARRAY' => \PDO::FETCH_ASSOC];

            // Check if the provided FETCH_MODE is valid
            if (!array_key_exists($options['FETCH_MODE'], $fetchModes)) {
                $this->reportError(
                    "Invalid FETCH_MODE '{$options['FETCH_MODE']}'. Valid options are 'OBJECT' or 'ARRAY'."
                );
            }

            // Set the PDO fetch mode
            $options[\PDO::ATTR_DEFAULT_FETCH_MODE] = $fetchModes[$options['FETCH_MODE']];

            // Update the FETCH_MODE property to the key itself
            $this->FETCH_MODE = $options['FETCH_MODE'];

            // Remove FETCH_MODE from options
            unset($options['FETCH_MODE']);
        }

        return $options;
    }

    /**
     * Establishes a connection to the database using the specified SQL manager.
     *
     * @throws Exception If the connection fails or invalid credentials are provided.
     */
    private function connect() {
        // Disconnect if already connected
        if ($this->is_connected()) {
            $this->disconnect();
        }

        switch ($this->sql_manager) {
            case 'MySQLi':
                $mysqli = new \mysqli($this->host, $this->username, $this->password, $this->dbname, $this->port, $this->socket);
                if ($mysqli->connect_error) {
                    $this->connection_error = $mysqli->connect_error;
                    $this->reportError("MySQLi Connection error: {$mysqli->connect_error}", 3);
                }
                if (!$mysqli->set_charset($this->charset)) {
                    $this->reportError("MySQLi Charset config error: {$mysqli->error}", 4);
                }
                $this->connection = $mysqli;
                break;

            case 'PDO':
                try {
                    $pdo = new \PDO($this->dsn, $this->username, $this->password, $this->options);
                    $this->connection = $pdo;
                } catch (\PDOException $e) {
                    $this->connection_error = $e->getMessage();
                    $this->reportError("PDO Connection error: " . $e->getMessage(), 3);
                }
                break;
        }
    }

    /**
     * Disconnects from the database and releases the connection.
     */
    public function disconnect() {
        if ($this->is_mysqli_manager()) {
            $this->connection->close();
        }
        $this->connection = null;
    }

    /**
     * Public method to return the connection status.
     * 
     * Sample usage:
     * $database = new LCS_DBManager(CREDS, ['FETCH_MODE' => 'OBJECT'], 'PDO');
     * if ($database->is_connected()) {
     *    echo 'Connected!';
     * } else {
     *   echo 'Not connected!';
     * }
     * 
     * @return bool True if connected, false otherwise.
     */
    public function is_connected() {
        return $this->connection !== null;
    }

    /**
     * Switches the SQL manager to the specified type and 
     * reconnects to the database with the new manager.
     */
    public function switch_sql_manager($sql_manager) {
        if (!in_array($sql_manager, self::SQL_MANAGERS)) {
            $this->reportError("Invalid SQL Manager '$sql_manager'. Use either " . implode(', ', self::SQL_MANAGERS), 2);
        }
        $this->previous_sql_manager = $this->sql_manager;
        $this->sql_manager = $sql_manager;
        $this->connect();
    }

    /**
     * Reverts the SQL manager to the previous type. 
     * If no previous manager is set, defaults to PDO.
     */
    public function revert_sql_manager() {
        $sql_manager = 'PDO';
        if (!empty($this->previous_sql_manager)) {
            $sql_manager = $this->previous_sql_manager;
        }
        $this->switch_sql_manager($sql_manager);
    }

    /**
     * Resets the SQL manager to the default type (PDO).
     */
    public function default_sql_manager() {
        $this->switch_sql_manager('PDO');
    }

    /**
     * Parses and builds the database credentials from the provided DSN string.
     *
     * Extracts individual components (e.g., host, username, password) for internal use 
     * and constructs the DSN string for PDO.
     *
     * @throws Exception If credentials are missing or improperly formatted.
     */
    private function build_credentials() {
        // Validate the credentials input
        if (empty($this->credentials)) {
            $this->reportError(
                'Config error: Necessary credentials are required. Example: ' .
                '$creds = "mysql:host=localhost;dbname=testdb;charset=utf8mb4;username=demo;password=@user123";' .
                '$db = new LCS_DBManager($creds);',
                2
            );
        }

        // Split credentials into key-value pairs
        $credsArray = explode(';', trim($this->credentials));
        $extendedCredsArray = [];

        foreach ($credsArray as $cred) {
            // Ensure each pair has a valid key=value structure
            if (strpos($cred, '=') === false) {
                $this->reportError("Config error: Invalid credential format in '{$cred}'. Expected key=value pairs.", 2);
            }
            [$key, $value] = explode('=', $cred, 2); // Limit to 2 parts to handle values containing `=`
            $extendedCredsArray[$key] = $value;
        }
        
        // Process and map the credentials
        foreach ($extendedCredsArray as $credKey => $credValue) {
            switch ($credKey) {
                case (strpos($credKey, ':host') !== false ? $credKey : false):
                    $this->host = $credValue;
                    break;
                case 'username':
                    $this->username = $credValue;
                    unset($extendedCredsArray[$credKey]);
                    break;
                case 'password':
                    $this->password = $credValue;
                    unset($extendedCredsArray[$credKey]);
                    break;
                case 'dbname':
                    $this->dbname = $credValue;
                    break;
                case 'port':
                    $this->port = $credValue;
                    break;
                case 'socket':
                    $this->socket = $credValue;
                    unset($extendedCredsArray[$credKey]);
                    break;
                case 'prefix':
                    $this->prefix = $credValue;
                    unset($extendedCredsArray[$credKey]);
                    break;
                case 'mysql_engine':
                    $this->mysql_engine = $credValue;
                    unset($extendedCredsArray[$credKey]);
                    break;
                case 'charset':
                case 'default_charset':
                case 'character_set':
                    $this->charset = $credValue;
                    break;
                case 'collation':
                    $this->collate = $credValue;
                    break;
                default:
                    // Unrecognized keys are left for DSN construction
                    break;
            }
        }

        // Construct the DSN string for PDO using remaining key-value pairs
        $this->dsn = implode(';', array_map(
            fn($key, $value) => "{$key}={$value}",
            array_keys($extendedCredsArray),
            $extendedCredsArray
        ));
        
    }

    /**
     * Retrieves the database charset and collation settings.
     *
     * This method returns the appropriate charset and collation for database table creation.
     * If `auto_charset` is enabled, it uses the current database connection's settings;
     * otherwise, it defaults to UTF-8 with the `utf8mb4` charset and `utf8mb4_general_ci` collation.
     *
     * @return string The charset and collation settings in the format `CHARACTER SET charset COLLATE collation`.
     */
    public function get_charset_collate() {
        // Default to utf8mb4 charset and collation
        $default_charset = $this->charset;
        $default_collation = $this->collate;

        // If auto_charset is disabled, return the default settings
        if (!$this->auto_charset) {
            return "CHARACTER SET $default_charset COLLATE $default_collation";
        }

        // Try to get charset and collation from the current database connection
        try {
            if ($this->sql_manager === 'PDO') {
                // Use PDO to query charset and collation
                $stmt = $this->connection->query("SHOW VARIABLES LIKE 'character_set_database'");
                $charset = $stmt->fetchColumn(1) ?: $default_charset;

                $stmt = $this->connection->query("SHOW VARIABLES LIKE 'collation_database'");
                $collation = $stmt->fetchColumn(1) ?: $default_collation;
            } elseif ($this->sql_manager === 'MySQLi') {
                // Use MySQLi to query charset and collation
                $result = $this->connection->query("SHOW VARIABLES LIKE 'character_set_database'");
                $charset = $result->fetch_assoc()['Value'] ?? $default_charset;

                $result = $this->connection->query("SHOW VARIABLES LIKE 'collation_database'");
                $collation = $result->fetch_assoc()['Value'] ?? $default_collation;
            } else {
                // Fallback to defaults if the SQL manager is unknown
                return "CHARACTER SET $default_charset COLLATE $default_collation";
            }

            return "CHARACTER SET $charset COLLATE $collation";

        } catch (\Exception $e) {
            // Log error and return default charset and collation
            $this->reportError("Error fetching charset and collation: " . $e->getMessage());
            return "CHARACTER SET $default_charset COLLATE $default_collation";
        }
    }

    /**
     * Retrieves the table prefix used in the database connection.
     *
     * @return string|null The table prefix, or null if not set.
     */
    public function get_table_prefix() {
        return $this->prefix;
    }

    /**
     * Retrieves the database engine type for the specified platform.
     *
     * Currently, only MySQL is supported.
     *
     * @param string $platform The database platform (default: 'mysql').
     * @return string|null The engine type for the platform, or null if unsupported.
     */
    public function get_engine_type( $platform = 'mysql' ) {
        // Currently only MySQL is supported
        if ( ! in_array( strtolower( $platform) , self::DB_PLATFORMS ) ) {
            $this->reportError( "Unsupported database platform '$platform' for engine type retrieval." );
            return null;
        }

        if ( strtolower($platform) === 'mysql' ) {
            return $this->mysql_engine;
        }

        return null;
    }

    /**
     * Retrieves the default character set for the database connection.
     *
     * @return string The default character set.
     */
    public function get_default_charset() {
        return $this->charset;
    }

    /**
     * Retrieves the database collation setting.
     *
     * @return string The collation setting.
     */
    public function get_collation() {
        return $this->collate;
    }

    /**
     * Executes a query and fetches the results.
     *
     * Supports both direct SQL execution and prepared statements with parameters.
     *
     * @param string $sql The SQL query string.
     * @param mixed ...$values Values to the placeholder in the $sql.
     * 
     * @return array|false An array of results, or false on failure.
     */
    public function get_results($sql, ...$values): array|false 
    {
        try {
            $sql = $this->validate_sql($sql, $values);
            // Direct SQL Execution (prepare not needed)
            if (!$this->contains_ph($sql)) {
                return $this->fetch_all($sql, false);
            }

            // Prepared Statement Execution
            $specifiers = $this->build_specifiers($values);
            $dataValues = $this->flatten_array($values);
            return $this->fetch_all($sql, true, $dataValues, $specifiers);
        } catch (\Exception $e) {
            $this->reportError($e->getMessage());
            return []; // Allows calling code to handle errors
        }
    }

    /**
     * Inserts a new row into a database table.
     *
     * @param string $table The name of the table.
     * @param array $data Associative array of column-value pairs to insert.
     * @return int|null The ID of the inserted row, or null on failure.
     * @throws Exception If the table name or data is invalid.
     */
    public function insert($table, $data) {
        // Validate inputs
        if (empty($table) || !is_string($table)) {
            $this->reportError("Invalid table name provided for insert.");
        }
        if (empty($data) || !is_array($data)) {
            $this->reportError("Insert data must be a non-empty associative array.");
        }

        // Prepare SQL
        $columns = implode(', ', array_map(fn($col) => "`$col`", array_keys($data))); // Sanitize column names
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";

        // Execute the query
        $this->get_results($sql, array_values($data));

        // Return the last insert ID
        return $this->is_pdo_manager() ? $this->connection->lastInsertId() : $this->connection->insert_id;
    }

    /**
     * Updates rows in a database table.
     *
     * @param string $table The name of the table.
     * @param array $data Associative array of column-value pairs to update.
     * @param array $where Associative array of conditions for the WHERE clause.
     * 
     * @return array|false The results of the update query, or false on failure.
     * @throws Exception If the table name, data, or where conditions are invalid.
     */
    public function update($table, $data, $where) : array|false
    {
        // Validate inputs
        if (empty($table) || !is_string($table)) {
            $this->reportError("Invalid table name provided for update.");
        }
        if (empty($data) || !is_array($data)) {
            $this->reportError("Update data must be a non-empty associative array.");
        }
        if (empty($where) || !is_array($where)) {
            $this->reportError("Update conditions must be a non-empty associative array.");
        }

        // Prepare SQL
        $set = implode(', ', array_map(fn($key) => "`$key` = ?", array_keys($data))); // Sanitize column names
        $conditions = implode(' AND ', array_map(fn($key) => "`$key` = ?", array_keys($where))); // Sanitize condition columns
        $sql = "UPDATE `$table` SET $set WHERE $conditions";

        // Execute the query
        return $this->get_results($sql, array_merge(array_values($data), array_values($where)));
    }

    /**
     * Deletes rows from a database table.
     *
     * @param string $table The name of the table.
     * @param array $where Associative array of conditions for the WHERE clause.
     * @return bool
     * @throws Exception If the table name or where conditions are invalid.
     */
    public function delete($table, $where) {
        // Validate inputs
        if (empty($table) || !is_string($table)) {
            $this->reportError("Invalid table name provided for delete.");
        }
        if (empty($where) || !is_array($where)) {
            $this->reportError("Delete conditions must be a non-empty associative array.");
        }

        // Prepare SQL
        $conditions = implode(' AND ', array_map(fn($key) => "`$key` = ?", array_keys($where))); // Sanitize condition columns
        $sql = "DELETE FROM `$table` WHERE $conditions";

        // Execute the query
        return $this->query($sql, array_values($where));
    }

    /**
     * Inserts or updates a row in a database table (REPLACE INTO).
     *
     * This method checks if a record with the same unique key exists. If it does,
     * it will update the record; otherwise, it will insert a new row.
     *
     * @param string $table The name of the table.
     * @param array $data Associative array of column-value pairs to insert or update.
     * @return void
     * @throws Exception If the table name or data is invalid.
     */
    public function replace($table, $data) {
        // Validate inputs
        if (empty($table) || !is_string($table)) {
            $this->reportError("Invalid table name provided for replace.");
        }
        if (empty($data) || !is_array($data)) {
            $this->reportError("Replace data must be a non-empty associative array.");
        }

        // Prepare SQL
        $columns = implode(', ', array_map(fn($col) => "`$col`", array_keys($data))); // Sanitize column names
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "REPLACE INTO `$table` ($columns) VALUES ($placeholders)";

        // Execute the query
        $this->get_results($sql, array_values($data));
    }

    /**
     * Fetches the primary key value(s) based on the provided conditions.
     *
     * If no table or conditions are provided, returns the last inserted ID.
     *
     * @param string|null $table The table name (required for WHERE conditions).
     * @param array $where Conditions for selecting the primary key.
     * @return int|array|null Single ID, array of IDs, or null on failure.
     * @throws Exception If the table name is provided without a WHERE condition.
     */
    public function get_id($table = null, $where = []) { 
        try {
            // If no table name and WHERE condition are provided, return the last insert ID
            if (is_null($table) && empty($where)) {
                return $this->is_pdo_manager() ? $this->connection->lastInsertId() : $this->connection->insert_id;
            }
    
            // If table is provided, WHERE must also be provided
            if (!is_null($table) && empty($where)) {
                $this->reportError("`get_id()` requires a WHERE condition when a table name is specified.");
            }
    
            // Validate table and retrieve primary key
            if (!is_null($table)) {
                $primaryKey = $this->get_primary_key($table);
                if (!$primaryKey) {
                    $this->reportError("Unable to determine primary key for table `$table`.");
                }
    
                // Build the WHERE clause dynamically
                $conditions = implode(' AND ', array_map(fn($key) => "$key = ?", array_keys($where)));
                $sql = "SELECT $primaryKey FROM $table WHERE $conditions";
    
                // Execute the query and fetch results
                $results = $this->get_col($sql, array_values($where));
    
                // Return results as a single value or array of integers
                if (!$results) {
                    return []; // Return empty array if no matches
                }
    
                return count($results) === 1 ? (int)$results[0] : array_map('intval', $results);
            }
    
        } catch (\Exception $e) {
            $this->reportError($e->getMessage());
            return null;
        }
    }

    /**
     * Fetches a column of data from the result set.
     *
     * @param string $sql The SQL query string.
     * @param mixed ...$params Parameters to bind in prepared statements.
     * @return array|null Array of column values, or null on failure.
     */
    public function get_col($sql, ...$params) {
        try {
            $results = $this->get_results($sql, ...$params);

            // If no results, return an empty array
            if (!$results || !is_array($results)) {
                return [];
            }

            // Extract the first column from each row
            $column = [];
            foreach ($results as $row) {
                if (is_object($row)) {
                    $row = (array)$row;
                }
                if (is_array($row)) {
                    $column[] = reset($row);
                }
            }

            return $column;

        } catch (\Exception $e) {
            $this->reportError($e->getMessage());
            return null;
        }
    }

    /**
     * Fetches a single row from the result set.
     *
     * @param string $sql The SQL query string.
     * @param mixed ...$params Parameters to bind in prepared statements.
     * @return object|array|null The row as an object/array based on fetch mode, or null on failure.
     */
    public function get_row($sql, ...$params) {
        try {
            $results = $this->get_results($sql, ...$params);
    
            if (!$results || empty($results)) {
                return null; // Return null if no rows found
            }
    
            // Return the first row
            return $results[0];
    
        } catch (\Exception $e) {
            $this->reportError($e->getMessage());
            return null;
        }
    }    
    
    /**
     * Fetches a single value from the result set.
     *
     * Useful for scalar queries like COUNT, SUM, or retrieving a single column value.
     *
     * @param string $sql The SQL query string.
     * @param mixed ...$params Parameters to bind in prepared statements.
     * @return mixed|null The value from the query result, or null on failure.
     */
    public function get_var($sql, ...$params) {
        try {
            $row = $this->get_row($sql, ...$params);

            // If no rows are found, return null
            if (!$row || (!is_array($row) && !is_object($row))) {
                return null;
            }

            // Convert object to array if needed, then reset to get the first value
            $rowArray = is_object($row) ? (array)$row : $row;
            return reset($rowArray);

        } catch (\Exception $e) {
            $this->reportError($e->getMessage());
            return null;
        }
    }

    /**
     * Executes a SQL query.
     *
     * Supports both direct SQL execution and prepared statements with parameters.
     * Handles placeholder validation, parameter binding, and execution based on the type of query.
     *
     * @param string $sql The SQL query string.
     * @param mixed ...$values Values to bind to the placeholders in the query.
     * @return PDOStatement|mysqli_result|bool|null Query result, or null on failure.
     */
    public function query($sql, ...$values) {
        try {
            // Validate and normalize the SQL
            $sql = $this->validate_sql($sql, $values);

            // Direct SQL Execution (if no placeholders are found)
            if (!$this->contains_ph($sql)) {
                return $this->connection->query($sql);
            }

            // Prepared Statement Execution
            $specifiers = $this->build_specifiers($values);
            $dataValues = $this->flatten_array($values);

            return $this->prepare($sql, $dataValues, $specifiers);

        } catch (\Exception $e) {
            // Log the error and return null
            $this->reportError("Query Error: " . $e->getMessage() . " | SQL: $sql");
            return null;
        }
    }

    /**
     * Sanitizes input data for database operations.
     *
     * This method ensures that the provided data is safe for storage or use in SQL queries
     * by escaping special characters, stripping harmful tags, and handling different data types.
     * It is designed to prevent SQL injection and other common vulnerabilities.
     *
     * @param mixed $data The data to sanitize. It can be a string, number, array, or object.
     * @return mixed The sanitized data, with appropriate escaping applied for strings, and validation for other types.
     */
    public function sanitize_data($data) {
        // Check if the input is an array; sanitize recursively
        if (is_array($data)) {
            return array_map([$this, 'sanitize_data'], $data);
        }

        // If the input is an object, sanitize its public properties
        if (is_object($data)) {
            foreach ($data as $key => $value) {
                $data->$key = $this->sanitize_data($value);
            }
            return $data;
        }

        // For numeric values, return as is (safe for database operations)
        if (is_numeric($data)) {
            return $data;
        }

        // For strings, apply database-specific escaping
        if (is_string($data)) {
            if ($this->is_pdo_manager()) {
                return $this->connection->quote($data); // PDO escaping
            } elseif ($this->connection instanceof \mysqli) {
                return $this->connection->real_escape_string($data); // MySQLi escaping
            } else {
                // Fallback for custom database managers
                return addslashes($data); // General escaping as a last resort
            }
        }

        // For null or other unsupported types, return NULL to indicate safe storage
        return null;
    }
    
    /**
     * Retrieves the primary key column for the specified table.
     *
     * @param string $table The table name.
     * @return string|null The primary key column name, or null on failure.
     */
    private function get_primary_key($table) {
        try {
            $primaryKey = null;
    
            if ($this->is_pdo_manager()) {
                $stmt = $this->connection->prepare("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
                $stmt->execute();
                $result = $stmt->fetch();
                if ($result && isset($result['Column_name'])) {
                    $primaryKey = $result['Column_name'];
                }
            } else {
                $stmt = $this->connection->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
                $result = $stmt->fetch_assoc();
                if ($result && isset($result['Column_name'])) {
                    $primaryKey = $result['Column_name'];
                }
            }
    
            return $primaryKey;
    
        } catch (\Exception $e) {
            $this->reportError("Error fetching primary key: " . $e->getMessage());
            return null;
        }
    }    

    /**
     * Fetches all rows from a query result.
     * 
     * This method handles both prepared statements and 
     * direct SQL execution, and it dynamically adapts to 
     * the configured fetch mode (object or associative array).
     *
     * @param string $sql The SQL query string.
     * @param bool $prepare Whether to use prepared statements.
     * @param array|null $values Values to bind in prepared statements.
     * @param string|null $specifiers Type specifiers for MySQLi bind_param.
     * 
     * @return array An array of results, where each result is an object or 
     * associative array based on the fetch mode.
     */
    private function fetch_all($sql, $prepare = true, $values = null, $specifiers = null): array 
    {
        // Refresh fetch mode
        $this->refresh_fetch_mode();

        if ($prepare) {
            // Prepared Statement Handling
            $stmt = $this->prepare($sql, $values, $specifiers);
            if (!$stmt) {
                return [];
            }
            
            if ($this->is_pdo_manager()) {
                // PDO: Fetch all results
                return $stmt->fetchAll();
            } else {
                // MySQLi: Dynamically bind and fetch results
                $meta = $stmt->result_metadata();
                if (!$meta) {
                    return [];
                }
    
                $fields = [];
                $row = [];
                while ($field = $meta->fetch_field()) {
                    $fields[] = &$row[$field->name];
                }
                call_user_func_array([$stmt, 'bind_result'], $fields);
    
                $results = [];
                while ($stmt->fetch()) {
                    $results[] = $this->FETCH_MODE === 'OBJECT'
                        ? (object) array_map(fn($v) => $v, $row)
                        : array_map(fn($v) => $v, $row);
                }
    
                $stmt->close();
                return $results;
            }
    
        } else {
            // Direct Query Execution
            $stmt = $this->connection->query($sql);
            if ($this->is_pdo_manager()) {
                if (!$stmt) {
                    $this->reportError($this->connection->errorInfo()[2]);
                    return [];
                }
                return $stmt->fetchAll();
            } else {
                $stmt = $this->connection->query($sql);
                if (!$stmt) {
                    $this->reportError($this->connection->error);
                    return [];
                }
                $rows = [];
                while ($row = $stmt->fetch_assoc()) {
                    $rows[] = $this->FETCH_MODE === 'OBJECT' ? (object) $row : $row;
                }
                return $rows;
            }
        }
    }    

    /**
     * Prepares a SQL statement for execution.
     *
     * @param string $sql The SQL query string.
     * @param array $values Values to bind in prepared statements.
     * @param string|null $specifiers Type specifiers for MySQLi bind_param.
     * @return PDOStatement|mysqli_stmt|null Prepared statement instance, or null on failure.
     */
    private function prepare($sql, $values, $specifiers = null) 
    {
        try {
            // Check connection
            if (!$this->is_connected()) {
                $this->reportError("No active database connection.");
                return null;
            }

            if ($this->is_pdo_manager()) {
                $stmt = $this->connection->prepare($sql);
                $stmt->execute($values);
                return $stmt;
            } else {
                $stmt = $this->connection->prepare($sql);
                if ($stmt === false) {
                    $this->reportError($this->connection->error);
                }
    
                if ($values && $specifiers) {
                    $stmt->bind_param($specifiers, ...$values);
                }
    
                if (!$stmt->execute()) {
                    $this->reportError($stmt->error);
                }
                
                return $stmt; // Ensure $stmt is returned
            }
        } catch (\Exception $e) {
            $this->reportError($e->getMessage());
        }
    }

    /**
     * Starts a new database transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function startTransaction(): bool
    {
        try {
            if ($this->is_pdo_manager()) {
                if (!$this->connection->inTransaction()) {
                    $this->connection->beginTransaction();
                } else {
                    // Nested transaction: use savepoint
                    $this->transactionDepth = ($this->transactionDepth ?? 0) + 1;
                    $this->connection->exec("SAVEPOINT LEVEL{$this->transactionDepth}");
                }
            } else {
                if (empty($this->inTransaction)) {
                    $this->connection->begin_transaction();
                    $this->inTransaction = true;
                } else {
                    $this->transactionDepth = ($this->transactionDepth ?? 0) + 1;
                }
            }

            return true;
        } catch (\Throwable $e) {
            $this->reportError("Transaction start error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Commits the current database transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function commit(): bool
    {
        try {
            if (!$this->isInTransaction()) {
                return true;
            }

            if ($this->is_pdo_manager()) {
                if (!empty($this->transactionDepth)) {
                    $this->connection->exec("RELEASE SAVEPOINT LEVEL{$this->transactionDepth}");
                    $this->transactionDepth--;
                } else {
                    $this->connection->commit();
                }
            } else {
                if (!empty($this->transactionDepth)) {
                    $this->transactionDepth--;
                } elseif (!empty($this->inTransaction)) {
                    $this->connection->commit();
                    $this->inTransaction = false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            $this->reportError("Transaction commit error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Rolls back the current database transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function rollBack(): bool
    {
        try {
            if (!$this->isInTransaction()) {
                $this->inTransaction = false;
                return false;
            }

            if ($this->is_pdo_manager()) {
                if (!empty($this->transactionDepth)) {
                    $this->connection->exec("ROLLBACK TO SAVEPOINT LEVEL{$this->transactionDepth}");
                    $this->transactionDepth--;
                } else {
                    $this->connection->rollBack();
                }
            } else {
                if (!empty($this->transactionDepth)) {
                    $this->transactionDepth--;
                } elseif (!empty($this->inTransaction)) {
                    $this->connection->rollback();
                    $this->inTransaction = false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            $this->reportError("Transaction rollback error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Checks if a transaction is currently active.
     *
     * @return bool True if a transaction is active, false otherwise.
     */
    private function isInTransaction(): bool
    {
        if ($this->is_pdo_manager()) {
            // For PDO, use PDO::inTransaction() directly
            return $this->connection instanceof \PDO && $this->connection->inTransaction();
        } else {
            // For MySQLi, rely on manual tracking
            return !empty($this->inTransaction) && $this->inTransaction === true;
        }
    }

    /**
     * Check if a table exists in the current database.
     *
     * @param string $table_name The name of the table to check.
     * @return bool True if the table exists, false otherwise.
     */
    public function is_table_exist( string $table_name):bool {
        // Define the SQL query to check for the existence of the table
        // The query checks the information_schema.tables to see if a table with the given name exists in the current schema.
        $sql = "SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = %s AND table_name = %s";

        // Execute the query using the main database name and the provided table name as parameters
        $result = $this->get_var($sql, $this->dbname, $table_name);

        // Return true if the table exists (count > 0), otherwise return false
        return ($result > 0);
    }

    /**
     * Check if a column exists in a table.
     *
     * @param string $table_name The name of the table to check.
     * @param string $column_name The name of the column to check.
     * @return bool True if the column exists, false otherwise.
     */
    public function is_table_column_exist( string $table_name, string $column_name):bool {
        // Define the SQL query to check for the existence of the column
        // The query checks the information_schema.columns to see if a column with the given name exists in the specified table.
        $sql = "SELECT COUNT(*) AS count FROM information_schema.columns WHERE table_schema = %s AND table_name = %s AND column_name = %s";

        // Execute the query using the main database name, the provided table name, and the column name as parameters
        $result = $this->get_var($sql, $this->dbname, $table_name, $column_name);

        // Return true if the column exists (count > 0), otherwise return false
        return ($result > 0);
    }

    /**
     * Backs up a table with its complete definition and data.
     *
     * This method uses SHOW CREATE TABLE to capture the exact table definition including:
     * - Column definitions (types, NULL/NOT NULL, DEFAULT values, AUTO_INCREMENT)
     * - Primary keys and all indexes
     * - Foreign key constraints
     * - Table engine, charset, collation, and other metadata
     *
     * This is the only safe way to backup a table for faithful restoration.
     *
     * @param string $table_name Name of the table to backup.
     * @param string|null $backup_file Optional custom backup file path. 
     *                                 Defaults to 'lcs_bu_tables.db' in the current directory.
     * @param bool $append Whether to append to existing backup file (true) or overwrite (false).
     * @return bool True on success, or throws an exception on failure.
     * @throws Exception If there is an error retrieving table data or writing to the backup file.
     */
    public function backup_table($table_name, $backup_file = null, $append = true) {
        
        // Set default backup file if not provided
        if (is_null($backup_file)) {
            $backup_file = 'lcs_bu_tables.db';
        }

        // Adjust table name with prefix
        $table_prefix = $this->prefix ?? '';
        $table = (strpos($table_name, $table_prefix) === 0) ? $table_name : $table_prefix . $table_name;

        // Get the complete CREATE TABLE statement
        $createResult = $this->get_row("SHOW CREATE TABLE `$table`");
        if (!$createResult) {
            $this->reportError("Failed to get CREATE TABLE statement for table '$table'.");
        }

        // Extract the CREATE TABLE SQL (it's the second column in the result)
        $createArray = (array)$createResult;
        $createSql = array_values($createArray)[1];

        // Get all rows from the table
        $rows = $this->get_results("SELECT * FROM `$table`");
        if ($rows === false) {
            $rows = []; // Empty table is valid
        }

        // Convert rows to arrays for consistent storage
        if ($this->FETCH_MODE === 'OBJECT') {
            $rows = array_map(fn($row) => (array)$row, $rows);
        }

        // Prepare backup data
        $table_backup = [
            'table_name' => $table,
            'create_sql' => $createSql,
            'rows' => $rows,
            'backup_timestamp' => date('Y-m-d H:i:s'),
            'row_count' => count($rows)
        ];

        // Load existing backups if appending
        $existing_backups = [];
        if ($append && file_exists($backup_file)) {
            $file_contents = file_get_contents($backup_file);
            if ($file_contents !== false) {
                $existing_backups = unserialize($file_contents);
                if (!is_array($existing_backups)) {
                    $existing_backups = [];
                }
            }
        }

        // Store backup with table name as key
        $existing_backups[$table] = $table_backup;

        // Serialize and save to file
        $serialized_data = serialize($existing_backups);
        $result = file_put_contents($backup_file, $serialized_data, LOCK_EX);

        if ($result === false) {
            $this->reportError("Failed to write backup data to file '$backup_file'.");
        }

        return true;
    }

    /**
     * Backs up multiple tables to a single backup file.
     *
     * @param array $table_names Array of table names to backup.
     * @param string|null $backup_file Optional custom backup file path.
     * @return bool True on success, or throws an exception on failure.
     * @throws Exception If there is an error during the backup process.
     */
    public function backup_tables(array $table_names, $backup_file = null) {
        
        if (empty($table_names)) {
            $this->reportError("No table names provided for backup.");
        }

        // Set default backup file if not provided
        if (is_null($backup_file)) {
            $backup_file = 'lcs_bu_tables.db';
        }

        $all_backups = [];

        // Load existing backups if file exists
        if (file_exists($backup_file)) {
            $file_contents = file_get_contents($backup_file);
            if ($file_contents !== false) {
                $existing = unserialize($file_contents);
                if (is_array($existing)) {
                    $all_backups = $existing;
                }
            }
        }

        // Backup each table
        foreach ($table_names as $table_name) {
            // Adjust table name with prefix
            $table_prefix = $this->prefix ?? '';
            $table = (strpos($table_name, $table_prefix) === 0) ? $table_name : $table_prefix . $table_name;

            // Get CREATE TABLE statement
            $createResult = $this->get_row("SHOW CREATE TABLE `$table`");
            if (!$createResult) {
                $this->reportError("Failed to get CREATE TABLE for '$table'. Skipping.");
                continue;
            }

            $createArray = (array)$createResult;
            $createSql = array_values($createArray)[1];

            // Get all rows
            $rows = $this->get_results("SELECT * FROM `$table`");
            if ($rows === false) {
                $rows = [];
            }

            // Convert rows to arrays
            if ($this->FETCH_MODE === 'OBJECT') {
                $rows = array_map(fn($row) => (array)$row, $rows);
            }

            // Store backup
            $all_backups[$table] = [
                'table_name' => $table,
                'create_sql' => $createSql,
                'rows' => $rows,
                'backup_timestamp' => date('Y-m-d H:i:s'),
                'row_count' => count($rows)
            ];
        }

        // Save all backups to file
        $serialized_data = serialize($all_backups);
        $result = file_put_contents($backup_file, $serialized_data, LOCK_EX);

        if ($result === false) {
            $this->reportError("Failed to write backup data to file '$backup_file'.");
        }

        return true;
    }

    /**
     * Restores a table from backup data.
     *
     * This method restores a table using the complete CREATE TABLE statement captured during backup,
     * ensuring all indexes, constraints, and metadata are preserved exactly as they were.
     *
     * @param array $table_data Array containing 'table_name', 'create_sql', and 'rows'.
     * @param bool $drop_existing Whether to drop the existing table before restoring (default: true).
     * @return bool True on success, or throws an exception on failure.
     * @throws Exception If there is an error during table restoration.
     */
    public function restore_table($table_data, $drop_existing = true) {

        // Validate backup data structure
        if (!isset($table_data['table_name'], $table_data['create_sql'], $table_data['rows'])) {
            $this->reportError("Invalid table data provided. Required keys: 'table_name', 'create_sql', 'rows'.");
        }

        $tableName = $table_data['table_name'];
        $createSql = $table_data['create_sql'];
        $rows = $table_data['rows'];

        // Start transaction for atomic restore
        $this->startTransaction();

        try {
            // Drop existing table if requested
            if ($drop_existing) {
                $this->query("DROP TABLE IF EXISTS `$tableName`");
            }

            // Recreate table with exact definition
            $createResult = $this->query($createSql);
            if (!$createResult) {
                throw new \Exception("Failed to recreate table: " . $this->last_error);
            }

            // Insert rows if any exist
            if (!empty($rows)) {
                // Temporarily disable foreign key checks for safer restore
                $this->query("SET FOREIGN_KEY_CHECKS = 0");

                foreach ($rows as $row) {
                    $this->insert($tableName, $row);
                }

                // Re-enable foreign key checks
                $this->query("SET FOREIGN_KEY_CHECKS = 1");
            }

            // Commit transaction
            $this->commit();
            return true;

        } catch (\Exception $e) {
            // Rollback on error
            $this->rollBack();
            $this->reportError("Error restoring table '$tableName': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lists all tables backed up in a backup file.
     *
     * @param string|null $backup_file Optional custom backup file path.
     * @return array Array of backed up table names with their backup information.
     * @throws Exception If the backup file doesn't exist or cannot be read.
     */
    public function list_backups($backup_file = null) {
        
        // Set default backup file if not provided
        if (is_null($backup_file)) {
            $backup_file = 'lcs_bu_tables.db';
        }

        // Check if backup file exists
        if (!file_exists($backup_file)) {
            $this->reportError("Backup file '$backup_file' does not exist.");
        }

        // Read and unserialize backup file
        $file_contents = file_get_contents($backup_file);
        if ($file_contents === false) {
            $this->reportError("Failed to read backup file '$backup_file'.");
        }

        $backups = unserialize($file_contents);
        if (!is_array($backups)) {
            $this->reportError("Invalid backup file format in '$backup_file'.");
        }

        // Return list of backed up tables with metadata
        $backup_list = [];
        foreach ($backups as $table_name => $table_data) {
            $backup_list[] = [
                'table_name' => $table_name,
                'backup_timestamp' => $table_data['backup_timestamp'] ?? 'Unknown',
                'row_count' => $table_data['row_count'] ?? count($table_data['rows'] ?? [])
            ];
        }

        return $backup_list;
    }

    /**
     * Retrieves backup data for a specific table from the backup file.
     * This is a helper method that can be used with restore_table().
     *
     * @param string $table_name Name of the table to retrieve from backup.
     * @param string|null $backup_file Optional custom backup file path.
     * @return array Table backup data in the format expected by restore_table().
     * @throws Exception If the backup file doesn't exist or table is not found.
     */
    public function get_backup_data($table_name, $backup_file = null) {
        
        // Set default backup file if not provided
        if (is_null($backup_file)) {
            $backup_file = 'lcs_bu_tables.db';
        }

        // Check if backup file exists
        if (!file_exists($backup_file)) {
            $this->reportError("Backup file '$backup_file' does not exist.");
        }

        // Read and unserialize backup file
        $file_contents = file_get_contents($backup_file);
        if ($file_contents === false) {
            $this->reportError("Failed to read backup file '$backup_file'.");
        }

        $backups = unserialize($file_contents);
        if (!is_array($backups)) {
            $this->reportError("Invalid backup file format in '$backup_file'.");
        }

        // Adjust table name with prefix for lookup
        $table_prefix = $this->prefix ?? '';
        $table = (strpos($table_name, $table_prefix) === 0) ? $table_name : $table_prefix . $table_name;

        // Check if table exists in backup
        if (!isset($backups[$table])) {
            $this->reportError("Table '$table_name' not found in backup file '$backup_file'.");
        }

        return $backups[$table];
    }

    /**
     * Gets all table names in the current database.
     *
     * @param bool $with_prefix Whether to include only tables with the configured prefix (default: false).
     * @return array Array of table names.
     * @throws Exception If there is an error retrieving table names.
     */
    public function get_table_names($with_prefix = false) {
        
        // Query to get all tables in the current database
        $tables = $this->get_col("SHOW TABLES");
        
        if ($tables === false || $tables === null) {
            $this->reportError("Failed to retrieve table names from database '$this->dbname'.");
        }

        // Filter by prefix if requested and prefix is set
        if ($with_prefix && !empty($this->prefix)) {
            $tables = array_filter($tables, function($table) {
                return strpos($table, $this->prefix) === 0;
            });
        }

        return array_values($tables); // Re-index array
    }

    /**
     * Checks if a table is already backed up in the backup file.
     *
     * @param string $table_name Name of the table to check.
     * @param string|null $backup_file Optional custom backup file path.
     * @return bool True if the table is backed up, false otherwise.
     */
    public function is_table_backed_up($table_name, $backup_file = null) {
        
        // Set default backup file if not provided
        if (is_null($backup_file)) {
            $backup_file = 'lcs_bu_tables.db';
        }

        // If backup file doesn't exist, table is not backed up
        if (!file_exists($backup_file)) {
            return false;
        }

        // Read and unserialize backup file
        $file_contents = file_get_contents($backup_file);
        if ($file_contents === false) {
            return false;
        }

        $backups = unserialize($file_contents);
        if (!is_array($backups)) {
            return false;
        }

        // Adjust table name with prefix for lookup
        $table_prefix = $this->prefix ?? '';
        $table = (strpos($table_name, $table_prefix) === 0) ? $table_name : $table_prefix . $table_name;

        // Check if table exists in backup
        return isset($backups[$table]);
    }

    /**
     * Backs up the entire database.
     *
     * This method backs up all tables in the current database to a single backup file.
     * It intelligently skips tables that are already backed up (unless $force_overwrite is true)
     * and provides detailed progress feedback.
     *
     * @param string|null $backup_file Optional custom backup file path.
     * @param bool $force_overwrite Whether to overwrite existing table backups (default: false).
     * @param bool $with_prefix_only Whether to backup only tables with the configured prefix (default: false).
     * @return array Statistics about the backup operation.
     * @throws Exception If there is an error during the backup process.
     */
    public function backup_db($backup_file = null, $force_overwrite = false, $with_prefix_only = false) {
        
        // Set default backup file if not provided
        if (is_null($backup_file)) {
            $backup_file = 'lcs_bu_database_' . $this->dbname . '.db';
        }

        // Get all table names
        $all_tables = $this->get_table_names($with_prefix_only);
        
        if (empty($all_tables)) {
            $this->reportError("No tables found in database '$this->dbname'.");
        }

        // Load existing backups if file exists
        $existing_backups = [];
        if (file_exists($backup_file)) {
            $file_contents = file_get_contents($backup_file);
            if ($file_contents !== false) {
                $existing = unserialize($file_contents);
                if (is_array($existing)) {
                    $existing_backups = $existing;
                }
            }
        }

        // Statistics tracking
        $stats = [
            'total_tables' => count($all_tables),
            'backed_up' => 0,
            'skipped' => 0,
            'failed' => 0,
            'tables_backed_up' => [],
            'tables_skipped' => [],
            'tables_failed' => [],
            'start_time' => microtime(true),
            'backup_file' => $backup_file
        ];

        // Backup each table
        foreach ($all_tables as $table_name) {
            
            // Check if already backed up and skip if not forcing overwrite
            if (!$force_overwrite && $this->is_table_backed_up($table_name, $backup_file)) {
                $stats['skipped']++;
                $stats['tables_skipped'][] = $table_name;
                continue;
            }

            try {
                // Get CREATE TABLE statement
                $createResult = $this->get_row("SHOW CREATE TABLE `$table_name`");
                if (!$createResult) {
                    throw new \Exception("Failed to get CREATE TABLE statement");
                }

                $createArray = (array)$createResult;
                $createSql = array_values($createArray)[1];

                // Get all rows
                $rows = $this->get_results("SELECT * FROM `$table_name`");
                if ($rows === false) {
                    $rows = [];
                }

                // Convert rows to arrays
                if ($this->FETCH_MODE === 'OBJECT') {
                    $rows = array_map(fn($row) => (array)$row, $rows);
                }

                // Store backup
                $existing_backups[$table_name] = [
                    'table_name' => $table_name,
                    'create_sql' => $createSql,
                    'rows' => $rows,
                    'backup_timestamp' => date('Y-m-d H:i:s'),
                    'row_count' => count($rows)
                ];

                $stats['backed_up']++;
                $stats['tables_backed_up'][] = $table_name;

            } catch (\Exception $e) {
                $stats['failed']++;
                $stats['tables_failed'][] = [
                    'table' => $table_name,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Add database metadata to backup
        $existing_backups['__metadata__'] = [
            'database_name' => $this->dbname,
            'backup_timestamp' => date('Y-m-d H:i:s'),
            'total_tables' => $stats['backed_up'],
            'charset' => $this->charset,
            'collate' => $this->collate
        ];

        // Save all backups to file
        $serialized_data = serialize($existing_backups);
        $result = file_put_contents($backup_file, $serialized_data, LOCK_EX);

        if ($result === false) {
            $this->reportError("Failed to write backup data to file '$backup_file'.");
        }

        // Calculate duration
        $stats['end_time'] = microtime(true);
        $stats['duration_seconds'] = round($stats['end_time'] - $stats['start_time'], 2);

        return $stats;
    }

    /**
     * Restores the entire database from a backup file.
     *
     * This method restores all tables from a backup file, intelligently handling:
     * - Tables that already exist (drops and recreates by default)
     * - Foreign key dependencies (temporarily disables checks)
     * - Transaction rollback on errors
     * - Detailed progress tracking
     *
     * @param string|null $backup_file Optional custom backup file path.
     * @param bool $drop_existing Whether to drop existing tables before restoring (default: true).
     * @param array|null $tables_to_restore Optional array of specific table names to restore. 
     *                                      If null, restores all tables in backup.
     * @return array Statistics about the restore operation.
     * @throws Exception If the backup file doesn't exist or is invalid.
     */
    public function restore_db($backup_file = null, $drop_existing = true, $tables_to_restore = null) {
        
        // Set default backup file if not provided
        if (is_null($backup_file)) {
            $backup_file = 'lcs_bu_database_' . $this->dbname . '.db';
        }

        // Check if backup file exists
        if (!file_exists($backup_file)) {
            $this->reportError("Backup file '$backup_file' does not exist.");
        }

        // Read and unserialize backup file
        $file_contents = file_get_contents($backup_file);
        if ($file_contents === false) {
            $this->reportError("Failed to read backup file '$backup_file'.");
        }

        $backups = unserialize($file_contents);
        if (!is_array($backups)) {
            $this->reportError("Invalid backup file format in '$backup_file'.");
        }

        // Extract metadata and remove it from backups array
        $metadata = $backups['__metadata__'] ?? null;
        unset($backups['__metadata__']);

        // Determine which tables to restore
        $tables = is_null($tables_to_restore) ? array_keys($backups) : $tables_to_restore;

        if (empty($tables)) {
            $this->reportError("No tables found in backup file to restore.");
        }

        // Statistics tracking
        $stats = [
            'total_tables' => count($tables),
            'restored' => 0,
            'skipped' => 0,
            'failed' => 0,
            'tables_restored' => [],
            'tables_skipped' => [],
            'tables_failed' => [],
            'start_time' => microtime(true),
            'backup_file' => $backup_file,
            'metadata' => $metadata
        ];

        // Disable foreign key checks for safe restoration
        $this->query("SET FOREIGN_KEY_CHECKS = 0");

        // Start a transaction for the entire restore operation
        $this->startTransaction();

        try {
            foreach ($tables as $table_name) {
                
                // Check if table exists in backup
                if (!isset($backups[$table_name])) {
                    $stats['skipped']++;
                    $stats['tables_skipped'][] = [
                        'table' => $table_name,
                        'reason' => 'Not found in backup'
                    ];
                    continue;
                }

                $table_data = $backups[$table_name];

                // Validate backup data structure
                if (!isset($table_data['table_name'], $table_data['create_sql'], $table_data['rows'])) {
                    $stats['failed']++;
                    $stats['tables_failed'][] = [
                        'table' => $table_name,
                        'error' => 'Invalid backup data structure'
                    ];
                    continue;
                }

                try {
                    $tableName = $table_data['table_name'];
                    $createSql = $table_data['create_sql'];
                    $rows = $table_data['rows'];

                    // Check if table already exists
                    $table_exists = $this->is_table_exist($tableName);

                    // Drop existing table if requested
                    if ($table_exists && $drop_existing) {
                        $this->query("DROP TABLE IF EXISTS `$tableName`");
                    } elseif ($table_exists && !$drop_existing) {
                        $stats['skipped']++;
                        $stats['tables_skipped'][] = [
                            'table' => $tableName,
                            'reason' => 'Table already exists and drop_existing is false'
                        ];
                        continue;
                    }

                    // Recreate table with exact definition
                    $createResult = $this->query($createSql);
                    if (!$createResult) {
                        throw new \Exception("Failed to recreate table: " . $this->last_error);
                    }

                    // Insert rows if any exist
                    $rows_inserted = 0;
                    if (!empty($rows)) {
                        foreach ($rows as $row) {
                            $this->insert($tableName, $row);
                            $rows_inserted++;
                        }
                    }

                    $stats['restored']++;
                    $stats['tables_restored'][] = [
                        'table' => $tableName,
                        'rows_restored' => $rows_inserted
                    ];

                } catch (\Exception $e) {
                    $stats['failed']++;
                    $stats['tables_failed'][] = [
                        'table' => $table_name,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Commit transaction
            $this->commit();

            // Re-enable foreign key checks
            $this->query("SET FOREIGN_KEY_CHECKS = 1");

        } catch (\Exception $e) {
            // Rollback on error
            $this->rollBack();
            $this->query("SET FOREIGN_KEY_CHECKS = 1");
            $this->reportError("Database restore failed: " . $e->getMessage());
        }

        // Calculate duration
        $stats['end_time'] = microtime(true);
        $stats['duration_seconds'] = round($stats['end_time'] - $stats['start_time'], 2);

        return $stats;
    }

    /**
     * DEPRECATED: Use backup_table() instead.
     * This method is kept for backward compatibility but will be removed in future versions.
     * 
     * Retrieves the entire table along with its structure and data.
     * WARNING: This method does NOT capture indexes, foreign keys, or table metadata.
     *
     * @param string $table_name Name of the table.
     * @return array The table data and structure (incomplete - missing indexes/constraints).
     * @throws Exception If there is an error retrieving the table data or structure.
     * @deprecated Use backup_table() for complete and safe table backups.
     */
    public function get_table_data($table_name) {
        // Adjust table name with prefix
        $table_prefix = $this->prefix ?? '';
        $table = (strpos($table_name, $table_prefix) === 0) ? $table_name : $table_prefix . $table_name;

        // Get all rows using the proper get_results method
        $table_data = $this->get_results("SELECT * FROM `$table`");
        
        if ($table_data === false) {
            $this->reportError("Failed to retrieve data for table '$table'.");
        }

        // Get the table structure using DESCRIBE
        $structure_result = $this->get_results("DESCRIBE `$table`");

        if (!$structure_result) {
            $this->reportError("Failed to retrieve table structure for table '$table'.");
        }

        // Convert objects to arrays if needed
        if ($this->FETCH_MODE === 'OBJECT') {
            $table_data = array_map(fn($row) => (array)$row, $table_data);
            $structure_result = array_map(fn($row) => (array)$row, $structure_result);
        }

        return [
            'table_name' => $table,
            'structure' => $structure_result,
            'rows' => $table_data
        ];
    }

    /**
     * Checks if a SQL string contains placeholders.
     *
     * Identifies named (e.g., :name), positional (?), or custom placeholders (e.g., %d).
     *
     * @param string $sql_string The SQL string to check.
     * @return bool True if placeholders are found, false otherwise.
     */
    private function contains_ph($sql_string) {
        return preg_match('/\?|:\w+|%[dsf]/', $sql_string);
    }

    /**
     * Validates the SQL query by ensuring the number of placeholders matches the parameters.
     * Normalizes placeholders in the SQL query to '?' and checks if their count matches the 
     * number of parameters provided. Throws an exception if there's a mismatch.
     *
     * @param string $sql    The SQL query with placeholders.
     * @param array $params  The parameters to bind to the placeholders.
     * @return string        The normalized SQL query.
     * @throws Exception     If the placeholders and parameters don't match.
     */
    private function validate_sql($sql, $params) {
        $sql = preg_replace('/\?|:\w+|%[dsf]/', '?', $sql);
        $placeholderCount = preg_match_all('/\?/', $sql);
        if ($placeholderCount !== count($this->flatten_array($params))) {
            $this->reportError(
                "SQL mismatch error: Expected $placeholderCount placeholders, " .
                "but got " . count($params) . " values.: $sql"
            );
            $this->reportError($this->last_error, 5);
        }
        return $sql;
    }

    /**
     * Builds type specifiers for MySQLi prepared statements.
     *
     * Constructs a string of specifiers ('s', 'i', 'd', 'b') based on the parameter types:
     * - `s` for strings
     * - `i` for integers
     * - `d` for floats (doubles)
     * - `b` for blobs
     *
     * @param mixed ...$params The parameters to analyze for type specifiers.
     * @return string A string of type specifiers (e.g., 'ssi').
     */
    private function build_specifiers(...$params) {
        $specifiers = '';
        foreach ($this->flatten_array($params) as $param) {
            $specifiers .= is_string($param) ? 's' : (is_int($param) ? 'i' : (is_float($param) ? 'd' : 'b'));
        }
        return $specifiers;
    }

    /**
     * Determines whether the current SQL manager is PDO.
     *
     * @return bool True if the SQL manager is PDO, false otherwise.
     */
    private function is_pdo_manager() {
        return $this->sql_manager === 'PDO' && $this->connection instanceof \PDO;
    }

    private function is_mysqli_manager() {
        // Check if instance is MySQLi
        return $this->sql_manager === 'MySQLi' && $this->connection instanceof \mysqli;
    }

    /**
     * Flattens a multi-dimensional array into a single-level array.
     *
     * This function recursively processes all nested arrays and extracts their values
     * into a single-level array.
     *
     * @param array $array The multi-dimensional array to flatten.
     * @return array The flattened array.
     */
    private function flatten_array(array $array): array {
        $result = [];
        foreach ($array as $value) {
            $result = is_array($value) ? array_merge($result, $this->flatten_array($value)) : [...$result, $value];
        }
        return $result;
    }

    /**
     * Retrieves the type of the database server.
     */
    public function get_database_type() {
        $this->switch_sql_manager('PDO');
        $dbType = $this->connection->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $this->revert_sql_manager();
        return $dbType;
    }

    /**
     * Retrieves the constraints for a given table in the database.
     *
     * This method supports multiple database types including MySQL, PostgreSQL, SQLite, SQL Server, Oracle, IBM DB2, Firebird, and Sybase.
     *
     * @param string $tableName The name of the table for which to retrieve constraints.
     * @return array An array of constraints for the specified table. Each constraint includes the constraint name and type.
     * @throws \Exception If the database type is unsupported.
     */
    public function get_table_constraints( string $tableName):array {
        $dbType = $this->get_database_type();
        $this->switch_sql_manager('PDO');
        $pdo = $this->connection;
        $constraints = [];

        switch ($dbType) {
            case 'mysql':
                $sql = "SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE 
                        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = ?";
                break;
            case 'pgsql':
                $sql = "SELECT con.conname AS constraint_name, 
                            CASE con.contype 
                                WHEN 'p' THEN 'PRIMARY KEY'
                                WHEN 'f' THEN 'FOREIGN KEY'
                                WHEN 'u' THEN 'UNIQUE'
                                WHEN 'c' THEN 'CHECK'
                            END AS constraint_type 
                        FROM pg_constraint con
                        JOIN pg_class rel ON rel.oid = con.conrelid
                        JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
                        WHERE rel.relname = ?";
                break;
            case 'sqlite':
                // Step 1: Get foreign key constraints
                $sqlFk = "PRAGMA foreign_key_list($tableName)";
                $stmt = $pdo->query($sqlFk);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $constraints[] = [
                        'constraint_name' => 'FK_' . $row['id'],
                        'constraint_type' => 'FOREIGN KEY'
                    ];
                }

                // Step 2: Extract PRIMARY KEY, UNIQUE, CHECK constraints from table schema
                $sqlSchema = "PRAGMA table_info($tableName)";
                $stmt = $pdo->query($sqlSchema);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    if ($row['pk'] == 1) {
                        $constraints[] = [
                            'constraint_name' => 'PK_' . $row['name'],
                            'constraint_type' => 'PRIMARY KEY'
                        ];
                    }
                    if ($row['notnull'] == 1) {
                        $constraints[] = [
                            'constraint_name' => 'NN_' . $row['name'],
                            'constraint_type' => 'NOT NULL'
                        ];
                    }
                }

                // Step 3: Get UNIQUE constraints from index list
                $sqlUnique = "PRAGMA index_list($tableName)";
                $stmt = $pdo->query($sqlUnique);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    if ($row['unique'] == 1) {
                        $constraints[] = [
                            'constraint_name' => $row['name'],
                            'constraint_type' => 'UNIQUE'
                        ];
                    }
                }

                // Step 4: SQLite does not directly store CHECK constraints in metadata
                // A workaround is to check sqlite_master for CHECK constraints in the CREATE TABLE statement.
                $sqlCheck = "SELECT sql FROM sqlite_master WHERE type='table' AND name=?";
                $stmt = $pdo->prepare($sqlCheck);
                $stmt->execute([$tableName]);
                $createTableSql = $stmt->fetchColumn();
                if ($createTableSql) {
                    preg_match_all('/CHECK\s*\(.*?\)/i', $createTableSql, $matches);
                    foreach ($matches[0] as $index => $check) {
                        $constraints[] = [
                            'constraint_name' => 'CHECK_' . ($index + 1),
                            'constraint_type' => 'CHECK'
                        ];
                    }
                }

                // SQLite handling is done, return result
                $this->revert_sql_manager();
                return $constraints;

            case 'sqlsrv':
                $sql = "SELECT name AS constraint_name, type_desc AS constraint_type 
                        FROM sys.objects 
                        WHERE type IN ('PK', 'F', 'UQ', 'C') 
                        AND parent_object_id = OBJECT_ID(?)";
                break;
            case 'oci':
                $sql = "SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE 
                        FROM ALL_CONSTRAINTS 
                        WHERE TABLE_NAME = UPPER(?) 
                        AND OWNER = (SELECT USER FROM DUAL)";
                break;
            case 'ibm':
                $sql = "SELECT CONSTNAME AS CONSTRAINT_NAME, 
                            CASE TYPE 
                                WHEN 'P' THEN 'PRIMARY KEY'
                                WHEN 'F' THEN 'FOREIGN KEY'
                                WHEN 'U' THEN 'UNIQUE'
                                WHEN 'C' THEN 'CHECK'
                            END AS CONSTRAINT_TYPE 
                        FROM SYSCAT.TABCONST 
                        WHERE TABNAME = UPPER(?)";
                break;
            case 'firebird':
                $sql = "SELECT RDB\$CONSTRAINT_NAME AS CONSTRAINT_NAME, 
                            RDB\$CONSTRAINT_TYPE AS CONSTRAINT_TYPE 
                        FROM RDB\$RELATION_CONSTRAINTS 
                        WHERE RDB\$RELATION_NAME = UPPER(?)";
                break;
            case 'sybase':
                $sql = "SELECT name AS constraint_name, 
                            CASE type 
                                WHEN 'PK' THEN 'PRIMARY KEY'
                                WHEN 'F' THEN 'FOREIGN KEY'
                                WHEN 'UQ' THEN 'UNIQUE'
                                WHEN 'C' THEN 'CHECK'
                            END AS constraint_type 
                        FROM sysobjects 
                        WHERE type IN ('PK', 'F', 'UQ', 'C') 
                        AND id = OBJECT_ID(?)";
                break;
            default:
                $this->reportError("Unsupported database type: " . $dbType);
        }

        // Execute for all non-SQLite databases
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tableName]);
        $constraints = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->revert_sql_manager();
        return $constraints;
    }

    /**
     * Checks if a constraint with the given name exists in the specified table.
     *
     * @param string $tableName The name of the table.
     * @param string $constraintName The name of the constraint.
     * @return bool True if the constraint exists, false otherwise.
     */
    public function is_constraint_name_exist( string $tableName, string $constraintName): bool {
        $constraints = $this->get_table_constraints($tableName);
        foreach ($constraints as $constraint) {
            if ($constraint['constraint_name'] === $constraintName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Refreshes the fetch mode for query results.
     *
     * @param string|null $fetchMode The desired fetch mode ('ARRAY' or 'OBJECT'). Defaults to the current fetch mode.
     * @throws \Exception If an invalid fetch mode is provided.
     */
    private function refresh_fetch_mode(string|null $fetchMode = null): void {
        $FM = is_null($fetchMode) ? $this->FETCH_MODE : $fetchMode;

        try {
            // Validate the fetch mode
            if (!in_array(strtoupper($FM), ['ARRAY', 'OBJECT'])) {
                $this->reportError("Invalid fetch mode '{$FM}'. Valid options are 'ARRAY' or 'OBJECT'.");
            }

            // Update fetch mode settings based on the SQL manager
            $FM = strtoupper($FM);
            if ($this->is_pdo_manager()) {
                $fmSettings = [
                    'ARRAY' => \PDO::FETCH_ASSOC,
                    'OBJECT' => \PDO::FETCH_OBJ,
                ];
                $this->connection->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, $fmSettings[$FM]);
            }

            $this->FETCH_MODE = $FM;
        } catch (\Exception $e) {
            $this->reportError("Error refreshing fetch mode: " . $e->getMessage());
        }
    }

    /**
     * Public method to return the connection error message.
     * 
     * Sample usage:
     * $database = new LCS_DBManager(CREDS, ['FETCH_MODE' => 'OBJECT'], 'PDO');
     * if ($database->is_connected()) {
     *    echo 'Connected!';
     * } else {
     *   echo 'Not connected! Error: ' . $database->connection_error();
     * }
     * 
     * @return string The connection error message.
     */
    public function connection_error() {
        return $this->connection_error;
    }

    /**
     * Reports an error using the Logs class.
     *
     * @param string $message The error message to report.
     * @param int $code Optional error code (default is 0).
     */
    private function reportError( $message, $code = 0 ) {
        $this->last_error = $message;
        Logs::reportError( $message, $this->throwError ? 3 : 1, 'DATABASE_ERROR', $code );
    }

    /**
     * Closes the database connection upon object destruction.
     */
    public function __destruct() {
        $this->disconnect();
    }
}