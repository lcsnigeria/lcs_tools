<?php
namespace LCSNG_EXT\Database;

use LCSNG_EXT\Database\LCS_DBManager;

class LCS_DBTable extends LCS_DBManager 
{

    /** @var bool Flag to indicate if a table is being created. */
    private $creating_table = false;

    /** @var bool Flag to indicate if a table is being altered. */
    private $altering_table = false;

    /** @var string|null The name of the table being created. */
    private $table_name;

    /** @var string|null The character set and collation for the table. */
    private $charset_collate;

    /** @var string The primary key column definition. */
    private $id = NULL;

    /** @var string|null SQL statement for creation timestamp column. */
    private $creation_date_sql = NULL;

    /** @var string|null SQL statement for update timestamp column. */
    private $updated_at_sql = NULL;

    /** @var string|null SQL statement for primary key. */
    private $primary_key_sql = NULL;

    /** @var array List of foreign key constraints. */
    private $foreign_keys_sql = [];

    private $index_sql = [];

    /** @var string Stores SQL parts for column definitions. */
    private $table_field_sql = '';

    /**
     * Constructor for initializing the class.
     *
     */
    public function __construct( string $credentials, array $options = [], string $sql_manager = "PDO" )
    {
        parent::__construct( $credentials, $options, $sql_manager );
        $this->charset_collate = $this->get_charset_collate();
    }

    /**
     * Prepares the instance to create a new table.
     *
     * @return $this
     */
    public function new_table()
    {
        $this->creating_table = true;
        return $this;
    }

    /**
     * Prepares the instance to alter an existing table.
     *
     * @return $this
     */
    public function alter_table()
    {
        $this->altering_table = true;
        return $this;
    }

    /**
     * Validates the table name and returns the full table name with prefix.
     *
     * @return string The full table name with prefix.
     * @throws \Exception If table name is invalid.
     */
    public function full_table_name() {
        $table_name = $this->table_name;
        if ($this->prefix && strpos($table_name, $this->prefix) === false) {
            $table_name = $this->prefix . $table_name;
        }
        return $table_name;
    }

    /**
     * Sets the table name.
     *
     * @param string $table_name The name of the table.
     * @throws \Exception If table name is invalid.
     */
    public function set_table_name($table_name)
    {
        // Validate table operation state
        if (!$this->creating_table && !$this->altering_table) {
            throw new \Exception("Table state unknown: neither creating nor altering. Use new_table() or alter_table().");
        }

        if (!is_string($table_name) || trim($table_name) === '') {
            throw new \Exception("Table name must be a non-empty string.");
        }
        $this->table_name = trim($table_name);
    }

    /**
     * Checks if a table has primary key.
     * 
     * @param string|null $table_name The name of the table.
     * @return bool Returns true if the table has a primary key.
     * @throws \Exception If table does not exist.
     */
    public function is_table_has_primary_key($table_name = null)
    {
        $table_name = $table_name ?? $this->full_table_name();

        // Check if table exist and throw exception if not
        if (!$this->is_table_exist($table_name)) {
            throw new \Exception("Table does not exist.");
        }

        $sql = "SHOW KEYS FROM `$table_name` WHERE Key_name = 'PRIMARY'";
        $result = $this->query($sql);
        return !empty($result);
    }

    /**
     * Checks if a table has an auto-increment column.
     *
     * @param string|null $table_name The name of the table.
     * @return bool Returns true if the table has an auto-increment column.
     * @throws \Exception If table does not exist.
     */
    public function is_table_has_auto_increment($table_name = null)
    {
        $table_name = $table_name ?? $this->full_table_name();

        // Check if table exists and throw exception if not
        if (!$this->is_table_exist($table_name)) {
            throw new \Exception("Table does not exist.");
        }

        $sql = "SHOW COLUMNS FROM `$table_name` WHERE Extra LIKE '%auto_increment%'";
        $result = $this->query($sql);
        return !empty($result);
    }

    /**
     * Checks if a SQL query contains a primary key definition.
     *
     * @param string $sql The SQL query to check.
     * @return bool Returns true if the SQL contains a primary key definition.
     */
    private function is_sql_contains_primary_key($sql)
    {
        if (!preg_match('/\bPRIMARY\s+KEY\s*(\([a-zA-Z0-9_]+\))?/i', $sql)) {
            return false;
        }
        return true;
    }

    /**
     * Checks if a data type is an integer type.
     *
     * @param string $data_type The data type to check.
     * @return bool Returns true if the data type is an integer type.
     */
    private function is_data_type_integer($data_type)
    {
        $integer_types = ['INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT'];
        return in_array(strtoupper($data_type), $integer_types);
    }

    /**
     * Returns an array of allowed data types.
     *
     * @return array An array of allowed data types.
     */
    private function allowed_data_types()
    {
        $allowed_data_types = [
            'VARCHAR', 'CHAR', 'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT',
            'BINARY', 'VARBINARY', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB',
            'ENUM', 'SET', 'JSON',
            'INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT',
            'FLOAT', 'DOUBLE', 'DECIMAL', 'REAL', 'NUMERIC',
            'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'YEAR',
            'BIT', 'BOOL'
        ];
        return $allowed_data_types;
    }

    /**
     * Checks if a data type is valid.
     *
     * @param string $data_type The data type to check.
     * @return bool Returns true if the data type is valid.
     */
    private function is_valid_data_type($data_type)
    {
        return in_array(strtoupper($data_type), $this->allowed_data_types());
    }

    /**
     * Validates and processes a table field before adding it to the table structure.
     *
     * Ensures that the field name, data type, constraints, and attributes are valid before
     * constructing the SQL definition for the table field.
     *
     * @param array $field_data Associative array of field properties:
     *                          - name (string)        : Column name.
     *                          - data_type (string)   : SQL data type (e.g., INT, VARCHAR).
     *                          - precision (int|null) : Length/precision for applicable types.
     *                          - modifier (string)    : Optional SQL modifier (e.g., UNSIGNED).
     *                          - default (mixed)      : Default value for the field.
     *                          - unique (bool)        : Whether the field should be UNIQUE.
     *                          - primary_key (bool)   : Whether the field is a PRIMARY KEY.
     *                          - auto_increment (bool): Whether the field should be AUTO_INCREMENT.
     *
     * @return bool Returns true if validation is successful.
     *
     * @throws \Exception If:
     *                    - The primary key is already set.
     *                    - The field name is missing or invalid.
     *                    - The data type is missing or unsupported.
     *                    - A required length/precision is missing.
     *                    - An invalid modifier is provided.
     *                    - AUTO_INCREMENT is set on a non-integer type.
     *                    - More than one AUTO_INCREMENT field is attempted.
     */
    public function validate_table_field_sql($field_data)
    {
        
        // Validate field name
        if (empty($field_data['name']) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field_data['name'])) {
            throw new \Exception("Invalid or missing field name.");
        }
        $name = trim($field_data['name']);
        if ($this->altering_table && $this->is_table_column_exist($this->full_table_name(), $name)) {
            throw new \Exception("Column with name '$name' already exists in the DB table '" . $this->full_table_name() . "'.");
        }

        // Check if primary key is being set and prevent duplicate primary keys
        if (!empty($field_data['primary_key'])) {
            if ($this->creating_table) {
                if (strpos(strtolower($this->id), 'primary key') !== false || !is_null($this->primary_key_sql)) {
                    throw new \Exception("Primary key already set.");
                }

            } elseif ($this->altering_table) {
                if ($this->is_table_has_primary_key()) {
                    throw new \Exception("Primary key already set.");
                }
            }
        }

        // Validate data type
        if (empty($field_data['data_type'])) {
            throw new \Exception("Data type is required for field: $name.");
        }
        $dataType = strtoupper(trim($field_data['data_type']));
        if (!$this->is_valid_data_type($dataType)) {
            throw new \Exception("Unsupported data type: $dataType." . PHP_EOL . "Allowed types: " . implode(', ', $this->allowed_data_types()));
        }

        // Handle length/precision for applicable data types
        $length_sql = '';
        if (in_array($dataType, ['VARCHAR', 'CHAR', 'DECIMAL', 'FLOAT', 'DOUBLE'])) {
            if (empty($field_data['length_or_precision']) || !is_numeric($field_data['length_or_precision']) || intval($field_data['length_or_precision']) <= 0) {
                throw new \Exception("$dataType requires a valid length/precision.");
            }
            $length_sql = "(".intval($field_data['length_or_precision']).")";
        }

        // Handle optional attributes (e.g., UNSIGNED)
        $attributes = '';
        if (!empty($field_data['modifier'])) {
            $valid_modifiers = ['UNSIGNED', 'SIGNED', 'ZEROFILL', 'BINARY'];
            $modifier = strtoupper(trim($field_data['modifier']));
            if (!in_array($modifier, $valid_modifiers)) {
                throw new \Exception("Invalid modifier: $modifier.");
            }
            $attributes = " $modifier";
        }

        // Handle DEFAULT values
        $default_sql = '';
        if (empty($field_data['primary_key']) && isset($field_data['default'])) {
            $default_sql = is_numeric($field_data['default']) || $field_data['default'] === 'NULL'
                ? " DEFAULT {$field_data['default']}"
                : " DEFAULT '{$field_data['default']}'";
        }

        // Handle UNIQUE constraint
        $unique_sql = (!empty($field_data['unique']) && empty($field_data['primary_key'])) ? " UNIQUE" : '';

        // Handle PRIMARY KEY constraint
        $primary_key_sql = !empty($field_data['primary_key']) ? " PRIMARY KEY" : '';

        // Handle AUTO_INCREMENT (only for integer types)
        $auto_increment_sql = '';
        if (!empty($field_data['auto_increment'])) {
            if (strpos(strtolower($this->table_field_sql), 'auto_increment') !== false) {
                throw new \Exception("Only one AUTO_INCREMENT field is allowed.");
            }
            if (!$this->is_data_type_integer($dataType)) {
                throw new \Exception("AUTO_INCREMENT is only allowed on integer types.");
            }
            $auto_increment_sql = " AUTO_INCREMENT";
        }

        // Construct final field definition
        $column_sql = "`$name` $dataType$length_sql$attributes$default_sql$primary_key_sql$unique_sql$auto_increment_sql";

        // Prepend 'ADD COLUMN' if altering table
        $column_sql = $this->altering_table ? "ADD COLUMN $column_sql" : $column_sql;

        // Append to field SQL storage
        $this->table_field_sql .= (empty($this->table_field_sql) ? '' : ', ') . $column_sql;

        return true;
    }

    /**
     * Sets the primary key field.
     *
     * @param string  $id_name         The primary key column name.
     * @param string  $dataType        The data type (e.g., INT, BIGINT).
     * @param string  $modifier        Additional SQL modifiers (e.g., UNSIGNED).
     * @param bool    $auto_increment  Whether to enable AUTO_INCREMENT.
     * 
     * @throws \Exception If auto-increment is not used with an integer type.
     */
    public function set_id($id_name = 'id', $dataType = 'INT', $modifier = 'UNSIGNED', $auto_increment = true)
    {
        if (!$this->is_data_type_integer($dataType)) {
            throw new \Exception("AUTO_INCREMENT can only be used with integer types.");
        }
 
        $should_auto_increment = '';
        if ($auto_increment) {
            $should_auto_increment .= ' AUTO_INCREMENT';
        }

        $this->id = "`$id_name` $dataType $modifier PRIMARY KEY$should_auto_increment";
    }

    /**
     * Adds a field to the table definition.
     *
     * @param string      $name              The name of the field.
     * @param string      $dataType          The data type of the field (e.g., VARCHAR, INT, DECIMAL).
     * @param int|string  $length_or_precision  The length (for VARCHAR) or precision (for DECIMAL).
     * @param mixed       $default           Default value for the field.
     * @param bool        $unique            Whether the field should be unique.
     * @param bool        $primary_key       Whether this field is the primary key.
     * @param string|null $modifier          Additional SQL modifiers (e.g., UNSIGNED, ZEROFILL).
     * @param bool        $auto_increment    Whether the field should auto-increment.
     * 
     * @return bool  Returns true on success, or throws an exception on failure.
     * @throws \Exception If any validation fails.
     */
    public function add_field(
        $name, 
        $dataType = 'varchar', 
        $length_or_precision = 255, 
        $default = NULL, 
        $unique = false, 
        $primary_key = false, 
        $modifier = NULL, 
        $auto_increment = false
    ) {
        // Ensure column name is valid
        $this->validate_column_addition($name);

        // Validate data type
        $allowed_data_types = $this->allowed_data_types();
        if (!$this->is_valid_data_type($dataType)) {
            throw new \Exception("Unsupported data type: $dataType. Allowed types: " . implode(', ', $allowed_data_types));
        }

        // Ensure `auto_increment` is used only on valid integer types
        if ($auto_increment && !$this->is_data_type_integer($dataType)) {
            throw new \Exception("AUTO_INCREMENT can only be used with integer types.");
        }

        // Prevent `auto_increment` from being used with default values or unique
        if ($auto_increment && ($default !== NULL || $unique)) {
            throw new \Exception("AUTO_INCREMENT field cannot have a default value or be unique.");
        }

        // Ensure `enum` and `set` types have predefined values
        if (in_array(strtolower($dataType), ['enum', 'set']) && (!is_array($length_or_precision) || empty($length_or_precision))) {
            throw new \Exception("$dataType fields must have predefined values (e.g., ENUM('small', 'medium', 'large')).");
        }

        if ($this->altering_table) {
            if (!$this->is_table_exist($this->full_table_name())) {
                throw new \Exception("Table does not exist.");
            }

            if ($primary_key) {
                if ($this->is_table_has_primary_key()) {
                    throw new \Exception("Primary key already set.");
                }
            }

        } elseif ($this->creating_table) {
            // Check if auto increment is already set
            if ($auto_increment && strpos($this->table_field_sql, strtolower('auto_increment')) !== false) {
                throw new \Exception("AUTO_INCREMENT field already set");
            }

            // Handle primary key
            if ($primary_key) {
                $this->id = "`$name` $dataType($length_or_precision) PRIMARY KEY";
                return true;
            }
        } else {
            throw new \Exception("Table state unknown: neither creating nor altering.");
        }

        // Prepare field data
        $field_data = [
            'name' => $name,
            'data_type' => $dataType,
            'length_or_precision' => $length_or_precision,
            'default' => $default,
            'unique' => $unique,
            'primary_key' => $primary_key,
            'modifier' => $modifier,
            'auto_increment' => $auto_increment
        ];

        // Validate table creation SQL
        return $this->validate_table_field_sql($field_data);
    }

    /**
     * Adds a VARCHAR field.
     *
     * @param string      $name    The column name.
     * @param int         $length  The length of the VARCHAR field.
     * @param string|null $default The default value.
     * @param bool        $unique  Whether the column should be unique.
     * 
     * @return bool Returns true if the field is successfully added.
     * @throws \Exception If field name or length is invalid.
     */
    public function add_varchar($name, $length = 255, $default = NULL, $unique = false)
    {
        // Ensure column name is valid
        $this->validate_column_addition($name);

        // Ensure length is a positive integer
        if (!is_int($length) || $length <= 0) {
            throw new \Exception("VARCHAR length must be a positive integer.");
        }

        if ($this->altering_table) {
            if (!$this->is_table_exist($this->full_table_name())) {
                throw new \Exception("Table does not exist.");
            }
        }

        $field_data = [
            'name' => $name,
            'data_type' => 'VARCHAR',
            'length_or_precision' => $length,
            'default' => $default,
            'unique' => $unique
        ];

        return $this->validate_table_field_sql($field_data);
    }

    /**
     * Adds an INT field with optional attributes.
     *
     * @param string      $name           The column name.
     * @param string      $dataType       Accept all the intergers data type (e.g. INT, BIGINT, etc).
     * @param int|null    $default        Set a default value.
     * @param bool        $unique         Whether the column should be unique.
     * @param string|null $modifier       Additional SQL modifiers.
     * @param bool        $primary_key    Whether the column is the primary key.
     * @param bool        $auto_increment Whether the column should auto-increment (valid only if primary key).
     * 
     * @return bool Returns true if the field is successfully added.
     * @throws \Exception If auto-increment is already set or misused.
     */
    public function add_int($name, $dataType = 'INT', $modifier = 'UNSIGNED', $default = NULL, $unique = false, $primary_key = false, $auto_increment = false)
    {
        // Ensure column name is valid
        $this->validate_column_addition($name);

        // Data type must be an integer type
        if (!$this->is_data_type_integer($dataType)) {
            throw new \Exception("AUTO_INCREMENT can only be used with integer types.");
        }

        if ($this->creating_table) {
            // Check if auto increment is already set
            if ($auto_increment && strpos(strtolower($this->table_field_sql), 'auto_increment') !== false) {
                throw new \Exception("Auto-increment field already set.");
            }
        } elseif ($this->altering_table) {
            // Check if primary key is being set and prevent duplicate primary keys
            if ($primary_key && $this->is_table_has_primary_key()) {
                throw new \Exception("Primary key already set.");
            }

            // Check if table has auto increment column
            if ($auto_increment && $this->is_table_has_auto_increment()) {
                throw new \Exception("Auto-increment field already set.");
            }
        }

        $field_data = [
            'name' => $name,
            'data_type' => $dataType,
            'default' => $default,
            'unique' => $unique,
            'modifier' => $modifier,
            'primary_key' => $primary_key,
            'auto_increment' => $auto_increment
        ];

        $this->validate_table_field_sql($field_data);

        return $this;
    }

    /**
     * Adds an index to the table.
     *
     * @param string       $index_type The type of index.
     * @param string       $index_name The name of the index.
     * @param array|string $columns    The column(s) to be indexed.
     *
     * @return void
     * @throws \Exception If the index name, type, or columns are invalid.
     */
    public function add_index(string $index_type, string $index_name, array|string $columns)
    {
        $allowed_index_types = [
            'primary_key', 'unique', 'index', 'fulltext', 'spatial',
            'clustered', 'nonclustered', 'hash', 'btree', 'rtree',
            'gin', 'gist', 'brin'
        ];

        if (!in_array($index_type, $allowed_index_types)) {
            throw new \Exception("Invalid index type: $index_type. Allowed types: " . implode(', ', $allowed_index_types));
        }

        if (!is_string($index_name) || trim($index_name) === '') {
            throw new \Exception("Index name must be a non-empty string.");
        }

        if (empty($columns)) {
            throw new \Exception("At least one column must be specified for the index.");
        }

        if (is_string($columns)) {
            $columns = [$columns];
        }

        if (!is_array($columns) || empty($columns)) {
            throw new \Exception("Columns must be a non-empty array or a valid string.");
        }

        // Ensure column names are formatted correctly
        $formatted_columns = array_map(fn($col) => "`" . trim($col) . "`", $columns);

        // Primary Key Check: Ensure only one column for PRIMARY KEY
        if ($index_type === 'primary_key') {
            if (count($columns) > 1) {
                throw new \Exception("PRIMARY KEY can only have one column.");
            }
            if (!empty($this->primary_key_sql) || ($this->altering_table && $this->is_table_has_primary_key())) {
                throw new \Exception("Only one PRIMARY KEY is allowed per table.");
            }
        }

        // Index SQL Construction (based on whether we are creating or altering the table)
        if ($this->creating_table) {
            switch ($index_type) {
                case 'primary_key':
                    $index_sql = "PRIMARY KEY (" . implode(', ', $formatted_columns) . ")";
                    $this->primary_key_sql = $index_sql;
                    break;
                case 'unique':
                    $index_sql = "UNIQUE `$index_name` (" . implode(', ', $formatted_columns) . ")";
                    break;
                case 'index':
                    $index_sql = "INDEX `$index_name` (" . implode(', ', $formatted_columns) . ")";
                    break;
                case 'fulltext':
                    $index_sql = "FULLTEXT `$index_name` (" . implode(', ', $formatted_columns) . ")";
                    break;
                case 'spatial':
                    $index_sql = "SPATIAL `$index_name` (" . implode(', ', $formatted_columns) . ")";
                    break;
                case 'clustered':
                    $index_sql = "CLUSTERED `$index_name` (" . implode(', ', $formatted_columns) . ")";
                    break;
                case 'nonclustered':
                    $index_sql = "NONCLUSTERED `$index_name` (" . implode(', ', $formatted_columns) . ")";
                    break;
                case 'hash':
                case 'btree':
                case 'rtree':
                case 'gin':
                case 'gist':
                case 'brin':
                    $index_sql = strtoupper($index_type) . " `$index_name` (" . implode(', ', $formatted_columns) . ")";
                    break;
                default:
                    throw new \Exception("Unsupported index type: $index_type.");
            }
            $this->index_sql[] = $index_sql;

        } elseif ($this->altering_table) {
            $alter_queries = [];
        
            switch ($index_type) {
                case 'primary_key':
                    $alter_queries[] = "ADD PRIMARY KEY (" . implode(', ', $formatted_columns) . ")";
                    break;
                case 'unique':
                    $alter_queries[] = "ADD UNIQUE INDEX `$index_name` (" . implode(', ', $formatted_columns) . ")";
                    break;
                case 'index':
                    $alter_queries[] = "ADD INDEX `$index_name` (" . implode(', ', $formatted_columns) . ")";
                    break;
                case 'fulltext':
                    $alter_queries[] = "ADD FULLTEXT INDEX `$index_name` (" . implode(', ', $formatted_columns) . ")";
                    break;
                case 'spatial':
                    $alter_queries[] = "ADD SPATIAL INDEX `$index_name` (" . implode(', ', $formatted_columns) . ")";
                    break;
                case 'hash':
                case 'btree':
                case 'rtree':
                case 'gin':
                case 'gist':
                case 'brin':
                    $alter_queries[] = "ADD INDEX `$index_name` USING " . strtoupper($index_type) . " (" . implode(', ', $formatted_columns) . ")";
                    break;
                case 'clustered':
                case 'nonclustered':
                    throw new \Exception("Clustered and Nonclustered indexes cannot be modified using ALTER TABLE.");
                default:
                    throw new \Exception("Index type `$index_type` is not supported for ALTER TABLE.");
            }
        
            if (!empty($alter_queries)) {
                $sql = "ALTER TABLE `{$this->table_name}` " . implode(', ', $alter_queries);
                $this->index_sql[] = $sql;
            }       
        }
         else {
            throw new \Exception("Table state unknown: neither creating nor altering.");
        }
    }

    /**
     * Adds a creation timestamp field.
     *
     * @param string $name The column name.
     * @throws \Exception If the table state is unknown or column already exists.
     */
    public function set_creation_timestamp($name = 'creation_date')
    {
        $this->validate_column_addition($name);

        $this->creation_date_sql = ($this->altering_table ? "ADD COLUMN " : "") . 
            "`$name` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    }

    /**
     * Adds an update timestamp field.
     *
     * @param string $name The column name.
     * @throws \Exception If the table state is unknown or column already exists.
     */
    public function set_update_timestamp($name = 'updated_at')
    {
        $this->validate_column_addition($name);

        $this->updated_at_sql = ($this->altering_table ? "ADD COLUMN " : "") . 
            "`$name` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    }

    /**
     * Validates if a column can be added.
     *
     * @param string $name The column name.
     * @throws \Exception If the table state is unknown or column already exists.
     */
    private function validate_column_addition($name)
    {
        if (!$this->creating_table && !$this->altering_table) {
            throw new \Exception("Table state unknown: neither creating nor altering.");
        }

        if (!is_string($name) || trim($name) === '') {
            throw new \Exception("Field name must be a non-empty string.");
        }

        if ($this->altering_table && $this->is_table_column_exist($this->full_table_name(), $name)) {
            throw new \Exception("Column '$name' already exists in table '" . $this->full_table_name() . "'.");
        }
    }

    /**
     * Adds a foreign key constraint to the table.
     *
     * @param string $column_name     The column that references another table.
     * @param string $reference_table The referenced table name.
     * @param string $reference_column The referenced column.
     * @param string $on_update       Action on update (CASCADE, SET NULL, etc.).
     * @param string $on_delete       Action on delete (CASCADE, SET NULL, etc.).
     * 
     * @throws \Exception If any parameter is invalid.
     */
    public function reference_table(
        $column_name, 
        $reference_table, 
        $reference_column, 
        $on_update = 'CASCADE', 
        $on_delete = 'CASCADE'
    ) {
        if (!$this->creating_table && !$this->altering_table) {
            throw new \Exception("Table state unknown: neither creating nor altering.");
        }

        // Validate inputs
        foreach (compact('column_name', 'reference_table', 'reference_column') as $key => $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new \Exception(ucwords(str_replace('_', ' ', $key)) . " must be a non-empty string.");
            }
        }

        $valid_actions = ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION', 'SET DEFAULT'];
        $on_update = strtoupper($on_update);
        $on_delete = strtoupper($on_delete);

        if (!in_array($on_update, $valid_actions)) {
            throw new \Exception("Invalid ON UPDATE action: $on_update.");
        }
        if (!in_array($on_delete, $valid_actions)) {
            throw new \Exception("Invalid ON DELETE action: $on_delete.");
        }

        // Generate constraint ID
        $constraint_id = $this->build_constraint_id('foreign_key', $this->table_name, $reference_table, $column_name);

        // Store the SQL constraint
        $foreign_key_sql = sprintf(
            "FOREIGN KEY (`%s`) REFERENCES `%s`(`%s`) ON UPDATE %s ON DELETE %s",
            $column_name, 
            $reference_table, 
            $reference_column, 
            $on_update, 
            $on_delete
        );

        if ($this->altering_table) {
            $foreign_key_sql = sprintf(
                "ADD CONSTRAINT `%s` %s",
                $constraint_id, 
                $foreign_key_sql
            );
        }

        $this->foreign_keys_sql[] = $foreign_key_sql;
    }

    /**
     * Builds a unique constraint ID with a mix of letters and numbers.
     *
     * @param string $key The type of constraint (e.g., 'foreign_key', 'primary_key').
     * @param string|null $table The child table name.
     * @param string|null $parent_table The parent table name.
     * @param string|null $column The column name involved in the constraint.
     * @return string A unique constraint ID.
     * @throws \Exception If the table state is unknown.
     */
    public function build_constraint_id(
        $key = 'foreign_key', 
        $table = null, 
        $parent_table = null, 
        $column = null
    ) {
        $key = strtolower($key);
        $keyList = [
            'foreign_key' => 'fk',
            'primary_key' => 'pk',
            'unique_key' => 'uk',
            'check_key' => 'ck',
            'index_key' => 'ik'
        ];

        $prefix_alias = $keyList[$key] ?? 'fk';
        $table = $table ?? 'child_table';
        $parent_table = $parent_table ?? 'parent_table';
        $column = $column ?? 'column';

        // Base constraint ID
        $constraint_id = "{$prefix_alias}_{$table}_{$parent_table}_{$column}";

        // Collect existing constraint IDs
        $registered_constraint_ids = [];
        if ($this->creating_table) {
            $registered_constraint_ids = array_map('strtolower', $this->foreign_keys_sql);
        } elseif ($this->altering_table) {
            $constraints = $this->get_table_constraints($this->full_table_name());
            foreach ($constraints as $constraint) {
                $registered_constraint_ids[] = strtolower($constraint['CONSTRAINT_NAME']);
            }
        } else {
            throw new \Exception("Table state unknown: neither creating nor altering.");
        }

        // Generate a random alphanumeric string (4 characters)
        $randomSuffix = function () {
            return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 4);
        };

        // Ensure uniqueness
        do {
            $new_suffix = '_' . $randomSuffix();
            $new_constraint_id = $constraint_id . $new_suffix;
            $conflict = false;

            foreach ($registered_constraint_ids as $cid) {
                if (strpos($cid, $new_constraint_id) !== false || $cid === $new_constraint_id) {
                    $conflict = true;
                    break;
                }
            }
        } while ($conflict);

        return $new_constraint_id;
    }

    /**
     * Creates the table in the database using the accumulated structure.
     *
     * This method constructs the SQL `CREATE TABLE` statement based on previously defined
     * columns, constraints, and indexes, then executes the query to create the table.
     *
     * @return bool Returns true on successful table creation, otherwise throws an exception.
     * @throws \Exception If table creation is not in progress, table name is missing,
     *                    primary key is not set, or SQL execution fails.
     */
    public function create_table()
    {
        try {
            // Ensure that the table creation process was initiated
            if (!$this->creating_table) {
                throw new \Exception("No table creation in progress.");
            }

            // Ensure that a table name is provided
            if (empty($this->table_name)) {
                throw new \Exception("Table name is not set.");
            }

            // Generate the full table name with the prefix
            $full_table_name = $this->prefix . $this->table_name;

            // Start constructing the SQL CREATE TABLE statement
            $sql = "CREATE TABLE IF NOT EXISTS `$full_table_name` (";

            // Append the primary ID field if defined (e.g., an auto-incrementing ID)
            if (!empty($this->id)) {
                $sql .= $this->id;
            }

            // Append additional fields defined for the table
            if (!empty($this->table_field_sql)) {
                $sql .= ", " . $this->table_field_sql;
            }

            // Append timestamp columns for record creation (if applicable)
            if (!empty($this->creation_date_sql)) {
                $sql .= ", " . $this->creation_date_sql;
            }

            // Append timestamp columns for record updates (if applicable)
            if (!empty($this->updated_at_sql)) {
                $sql .= ", " . $this->updated_at_sql;
            }

            // Append the primary key definition if explicitly provided
            if (!empty($this->primary_key_sql)) {
                $sql .= ", " . $this->primary_key_sql;
            }

            // Append defined indexes for the table
            if (!empty($this->index_sql)) {
                $sql .= ", " . implode(", ", $this->index_sql);
            }

            // Append foreign key constraints if defined
            if (!empty($this->foreign_keys_sql)) {
                $sql .= ", " . implode(", ", $this->foreign_keys_sql);
            }

            // Close the CREATE TABLE statement with charset and collation settings
            $sql .= ") {$this->charset_collate};";

            // Ensure that a PRIMARY KEY is set in the final SQL statement
            if (!$this->is_sql_contains_primary_key($sql)) {
                throw new \Exception("Primary key is required for table creation.");
            }

            // Execute the final SQL query
            $result = $this->query($sql);

            // Check for execution failure
            if (!$result) {
                throw new \Exception("Failed to create table `$full_table_name`.");
            }

            // Reset all table-related properties to their default state after execution
            $this->reset_properties();

            return true;
        } catch (\Exception $e) {
            // Catch and rethrow any exceptions with additional context
            throw new \Exception("Error creating table: " . $e->getMessage());
        }
    }

    /**
     * Updates the table in the database using the accumulated modifications.
     *
     * This method constructs the SQL `ALTER TABLE` statement based on previously defined
     * column additions, constraints, and indexes, then executes the query to modify the table.
     *
     * @return bool Returns true on successful table update, otherwise throws an exception.
     * @throws \Exception If table alteration is not in progress, table name is missing,
     *                    the table does not exist, or SQL execution fails.
     */
    public function update_table()
    {
        try {
            // Ensure that the table modification process was initiated
            if (!$this->altering_table) {
                throw new \Exception("No table alteration in progress. Use `alter_table()` to begin.");
            }

            // Ensure that a table name is provided
            if (empty($this->table_name)) {
                throw new \Exception("Table name is not set. Use `set_table_name('name_of_the_table')` to set the table name.");
            }

            // Get the full table name
            $full_table_name = $this->full_table_name();

            // Check if the table exists before altering it
            if (!$this->is_table_exist($full_table_name)) {
                throw new \Exception("Table `$full_table_name` does not exist.");
            }

            // Start constructing the SQL ALTER TABLE statement
            $sql = "ALTER TABLE `$full_table_name` ";

            $alterations = [];

            // Append additional fields defined for the table
            if (!empty($this->table_field_sql)) {
                $alterations[] = $this->table_field_sql;
            }

            // Append timestamp columns for record creation (if applicable)
            if (!empty($this->creation_date_sql)) {
                $alterations[] = $this->creation_date_sql;
            }

            // Append timestamp columns for record updates (if applicable)
            if (!empty($this->updated_at_sql)) {
                $alterations[] = $this->updated_at_sql;
            }

            // Append the primary key definition if explicitly provided
            if (!empty($this->primary_key_sql)) {
                $alterations[] = $this->primary_key_sql;
            }

            // Append defined indexes for the table
            if (!empty($this->index_sql)) {
                $alterations = array_merge($alterations, $this->index_sql);
            }

            // Append foreign key constraints if defined
            if (!empty($this->foreign_keys_sql)) {
                $alterations = array_merge($alterations, $this->foreign_keys_sql);
            }

            // Ensure there is something to alter
            if (empty($alterations)) {
                throw new \Exception("No alterations defined for table `$full_table_name`.");
            }

            // Finalize the SQL statement
            $sql .= implode(", ", $alterations) . ";";

            // Execute the final SQL query
            $result = $this->query($sql);

            // Check for execution failure
            if (!$result) {
                throw new \Exception("Failed to update table `$full_table_name`.");
            }

            // Reset all table-related properties to their default state after execution
            $this->reset_properties();

            return true;
        } catch (\Exception $e) {
            // Catch and rethrow any exceptions with additional context
            throw new \Exception("Error updating table: " . $e->getMessage());
        }
    }

    /**
     * Resets table properties to their default state.
     */
    private function reset_properties()
    {
        $this->creating_table = false;
        $this->altering_table = false;
        $this->table_name = null;
        $this->id = 'id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $this->table_field_sql = '';
        $this->creation_date_sql = null;
        $this->updated_at_sql = null;
        $this->primary_key_sql = null;
        $this->foreign_keys_sql = [];
        $this->index_sql = [];
    }


}