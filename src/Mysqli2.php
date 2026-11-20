<?php

class Mysqli2 extends mysqli {
    private $version = '2.0.0';
    
    private static $devMode = false; // Default: production

    protected static $instance;
    protected static $options = [];
    
    private static $useExceptions = true;
    private $lastError = null;
    private $resultFilter = null;

    /**
     * @param string|null $host
     * @param int|null $port
     * @param string|null $user
     * @param string|null $password
     * @param string|null $database
     * @param bool $sock
     * @return self
     */
    public static function getInstance($host = null, $port = null, $user = null, $password = null, $database = null, $sock = false) {
        if ($host !== null) {
            self::$options = [
                'host' => $host,
                'user' => $user,
                'pass' => $password,
                'dbname' => $database,
                'port' => $port,
                'sock' => $sock
            ];
        }
        
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function isDev($enable = null) {
        if ($enable !== null) {
            self::$devMode = (bool)$enable;
        }
        return self::$devMode;
    }
    
    public static function isProd($enable = null) {
        if ($enable !== null) {
            self::$devMode = !((bool)$enable);
        }
        return !self::$devMode;
    }
    
    public function __construct() {
        $o = self::$options;
        
        // Sett mysqli_report basert på klasse-modus
        mysqli_report(self::$devMode ? 
            MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT : 
            MYSQLI_REPORT_OFF
        );
        
        @parent::__construct(
            isset($o['host']) ? $o['host'] : 'localhost',
            isset($o['user']) ? $o['user'] : 'root',
            isset($o['pass']) ? $o['pass'] : '',
            isset($o['dbname']) ? $o['dbname'] : 'world',
            isset($o['port']) ? $o['port'] : 3306,
            isset($o['sock']) ? $o['sock'] : false
        );
        
        if (mysqli_connect_errno()) {
            $this->handleError(mysqli_connect_error(), '', mysqli_connect_errno());
        }
    }

    /**
     * @param bool $use
     * @return void
     */
    public static function setUseExceptions($use) {
        self::$useExceptions = $use;
    }
    
    /**
     * @return array|null
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * @return string
     */
    public function getVersion() {
        return $this->version;
    }

    /**
     * @param array $opt
     * @return void
     */
    public static function setOptions(array $opt) {
        self::$options = array_merge(self::$options, $opt);
    }

    /**
     * @param string $error
     * @param string $query
     * @param int $errno
     * @return false
     */
    private function handleError($error, $query = '', $errno = 0) {
        $this->lastError = [
            'error' => $error,
            'query' => $query,
            'errno' => $errno
        ];
        
        if (self::$useExceptions) {
            throw new DatabaseException($error, $query, $errno);
        }
        
        return false;
    }

    /**
     * Execute a MySQL query directly (overrides mysqli::query)
     * 
     * @param string $query SQL query to execute
     * @param int $resultmode Result mode (MYSQLI_STORE_RESULT or MYSQLI_USE_RESULT)
     * @return mysqli_result|bool Returns mysqli_result for SELECT queries, 
     *                           true for other successful queries, false on failure
     */
    #[\ReturnTypeWillChange]
    public function query(string $query, $resultmode = MYSQLI_STORE_RESULT)
    {
        // Consume the filter up front so a failed query never leaks it to the next one
        $filter = $this->resultFilter;
        $this->resultFilter = null;

        if (!$this->real_query($query)) {
            return $this->handleError($this->error, $query, $this->errno);
        }

        $result = new \mysqli_result($this);

        // No result set (INSERT/UPDATE/DDL): nothing to filter
        if ($filter === null || !$this->field_count) {
            return $result;
        }
        return $this->applyResultFilter($result, $filter[0], $filter[1]);
    }

    /**
     * EWS compat: run a query and return a single value/row
     *
     * @param string $query
     * @param mixed $return
     *   'insert_id': last insert id
     *   0:           first column of first row
     *   int > 0:     that column of first row
     *   null:        first row as assoc array
     * @return mixed null when there are no rows
     */
    public function query1($query, $return = null)
    {
        if (!$this->real_query($query)) {
            return $this->handleError($this->error, $query, $this->errno);
        }

        if ($return === 'insert_id') {
            return $this->insert_id;
        }
        if (!$this->field_count) {
            return null;
        }

        $result = new \mysqli_result($this);
        if (is_numeric($return)) {
            $row = $result->fetch_row();
            return $row === null ? null : $row[(int)$return];
        }
        return $result->fetch_assoc();
    }

    /**
     * Chainable: shape the result of the next query() call.
     * Example: $mysqli->result('array', '[int]')->query('SELECT DISTINCT(UserID) FROM t');
     *
     * @param string $mode 'array', 'assoc' or 'text'
     * @param string|int|null $type
     *   array: null | 'int' | '[int]' | 'int=>assoc' | 'int=>string' | 'string=>string' | 'int=>*' | 'string=>*'
     *   assoc: null | 'int=>assoc' | 'int=>key::<column>'
     *   text:  null (all columns) | column index
     * @return $this
     */
    public function result($mode, $type = null)
    {
        $this->resultFilter = [$mode, $type];
        return $this;
    }

    /**
     * Port of the Mysqli2 v1.6 (EWS) result filter
     *
     * @return array|string|mysqli_result
     */
    private function applyResultFilter(mysqli_result $result, $mode, $type)
    {
        if ($mode === 'assoc') {
            $out = [];
            while ($row = $result->fetch_assoc()) {
                if ($type === 'int=>assoc') {
                    $out[reset($row)] = $row;
                } elseif (is_string($type) && str_starts_with($type, 'int=>key::')) {
                    $out[$row[substr($type, 10)]] = $row;
                } else {
                    $out[] = $row;
                }
            }
            return $out;
        }

        if ($mode === 'text') {
            $out = '';
            while ($row = $result->fetch_row()) {
                $out .= ($type !== null ? $row[(int)$type] : implode(', ', $row)) . "\n";
            }
            return $out;
        }

        if ($mode === 'array') {
            $out = [];
            while ($row = $result->fetch_row()) {
                if ($type === null) {
                    $out[] = $result->field_count == 1 ? $row[0] : $row;
                    continue;
                }
                switch ($type) {
                    case 'int=>assoc':     $value = $row; break;
                    case 'int':            $value = (int)$row[0]; break;
                    case '[int]':          $value = [(int)$row[0]]; break;
                    case 'int=>string':
                    case 'string=>string': $value = (string)$row[1]; break;
                    default:               $value = $row[0];
                }
                if (str_starts_with($type, 'int=>')) {
                    $out[(int)$row[0]] = $value;
                } elseif (str_starts_with($type, 'string=>')) {
                    $out[(string)$row[0]] = $value;
                } else {
                    $out[] = $value;
                }
            }
            return $out;
        }

        return $result;
    }

    /**
     * Execute prepared statement (SELECT/INSERT/UPDATE/DELETE)
     * 
     * @param mixed $sql Array format: [$sql, $types, $params] or just SQL string
     * @param string $types Type definition string (i=int, s=string, d=double)
     * @param array $params Parameters to bind
     * @return mixed
     *   - SELECT: Array of rows
     *   - INSERT: Last insert ID
     *   - UPDATE/DELETE: Affected rows
     *   - false on error
     */
    public function execute($sql, $types = '', $params = []) {
        try {
            // Handle array format
            if (is_array($sql)) {
                list($sql, $types, $params) = $sql;
            }
            
            $stmt = $this->prepare($sql);
            if (!$stmt) {
                return $this->handleError($this->error, $sql, $this->errno);
            }
            
            // Bind parameters if provided
            if ($types && $params) {
                // Validate parameter count matches type string
                $expectedCount = strlen($types);
                $actualCount = count($params);
                
                if ($expectedCount !== $actualCount) {
                    $stmt->close();
                    return $this->handleError(
                        "Parameter count mismatch: Query expects {$expectedCount} parameters (types: '{$types}'), but {$actualCount} provided",
                        $sql,
                        0
                    );
                }
                
                array_unshift($params, $types);
                call_user_func_array([$stmt, 'bind_param'], $this->refValues($params));
            }
            
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $errno = $stmt->errno;
                $stmt->close();
                return $this->handleError($error, $sql, $errno);
            }
            
            // Determine query type from SQL
            $trimmedSql = trim($sql);
            $queryType = strtoupper(substr($trimmedSql, 0, 6));
            
            // Special check for DELETE since it's 7 chars
            if (strtoupper(substr($trimmedSql, 0, 7)) === 'DELETE ') {
                $queryType = 'DELETE';
            }
            
            switch ($queryType) {
                case 'INSERT':
                    $result = $stmt->insert_id ?: $stmt->affected_rows;
                    break;
                    
                case 'UPDATE':
                case 'DELETE':
                    $result = $stmt->affected_rows;
                    break;
                    
                case 'SELECT':
                    $meta = $stmt->result_metadata();
                    if (!$meta) {
                        $stmt->close();
                        return [];
                    }
                    
                    $row = [];
                    $params = [];
                    while ($field = $meta->fetch_field()) {
                        $params[] = &$row[$field->name];
                    }
                    
                    call_user_func_array([$stmt, 'bind_result'], $params);
                    
                    $result = [];
                    while ($stmt->fetch()) {
                        $c = [];
                        foreach($row as $key => $val) {
                            $c[$key] = $val;
                        }
                        $result[] = $c;
                    }
                    break;
                    
                default:
                    $result = $stmt->affected_rows;
            }
            
            $stmt->close();
            return $result;
            
        } catch (mysqli_sql_exception $e) {
            return $this->handleError($e->getMessage(), $sql, $e->getCode());
        }
    }

    /**
     * Execute and return single row/value
     * 
     * @param mixed $sql Array format: [$sql, $types, $params] or just SQL string
     * @param string $types Type definition string
     * @param array $params Parameters to bind
     * @param mixed $return Return mode:
     *   - 0: First column value only
     *   - true: Full row or null if empty
     *   - 'default': Always return first row
     * @return mixed
     */
    public function execute1($sql, $types = '', $params = [], $return = 'default') {
        // Fix single parameter convenience
        if ($types && !is_array($params)) {
            $params = [$params];
        }
        
        $result = $this->execute($sql, $types, $params);
        
        if (!$result || !is_array($result) || !count($result)) {
            if ($return === true) {
                return null;
            }
            return $this->handleError('execute1 expects results but got none', is_array($sql) ? $sql[0] : $sql, 1);
        }
        
        if ($return === 0) {
            return array_shift($result[0]);
        } else if ($return === true) {
            return empty($result) ? null : $result[0];
        } else {
            return $result[0];
        }
    }

    /**
     * EWS compat: alias for execute()
     */
    public function prepared_query($sql, $types = '', $params = []) {
        return $this->execute($sql, $types, $params);
    }

    /**
     * EWS compat: alias for execute() (INSERT returns insert_id, or affected rows)
     */
    public function prepared_insert($sql, $types = '', $params = []) {
        return $this->execute($sql, $types, $params);
    }

    /**
     * EWS compat: single-result prepared query
     *
     * @param mixed $return 0: first column of first row, true: first row, 'default': all rows
     * @param mixed $onEmpty Returned when there are no rows, or the query failed with exceptions disabled
     * @return mixed
     */
    public function prepared_query1($sql, $types = '', $params = [], $return = 'default', $onEmpty = null) {
        if ($types && !is_array($params)) {
            $params = [$params];
        }

        $result = $this->execute($sql, $types, $params);

        if (!is_array($result) || !count($result)) {
            return $onEmpty;
        }
        if ($return === 0) {
            return reset($result[0]);
        }
        if ($return === true) {
            return $result[0];
        }
        return $result;
    }

    /**
     * @param array $arr
     * @return array
     */
    private function refValues($arr) {
        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }

    /**
     * Return working collate charsets from mysql
     *
     * @param array $c Optional array overriding the $collate array inside function, and only if exist
     * 
     * @return array [ charset => collate charset ]
     */
    public function return_charset_and_collate($c = [])
    {
        $collate = [
            'utf8' => 'utf8_general_ci',
            'utf8mb4' => 'utf8mb4_general_ci',
        ];
        $_collate = $collate;

        $this->real_query("SHOW COLLATION LIKE 'utf8%'");
        $res = new mysqli_result($this);

        while ($row = $res->fetch_array(MYSQLI_ASSOC)) {
            if (in_array($row['Collation'], ['utf8_danish_ci', 'utf8mb4_danish_ci'])) {
                if ($collate[$row['Charset']] == $_collate[$row['Charset']]) {
                    $collate[$row['Charset']] = $row['Collation'];
                }
            }
            if (in_array($row['Collation'], ['utf8_swedish_ci', 'utf8mb4_swedish_ci'])) {
                $collate[$row['Charset']] = $row['Collation'];
            }
        }

        if( !empty($c['utf8']) and $this->doesCollationExist($c['utf8'])){
            $collate['utf8'] = $c['utf8'];
        }

        if( !empty($c['utf8mb4']) and $this->doesCollationExist($c['utf8mb4'])){
            $collate['utf8mb4'] = $c['utf8mb4'];
        }

        return $collate;
    }

    /**
     * Check if a collation charset already exists in MySQL
     *
     * @param string $collation Name of collation
     *
     * @return bool True if collation charset exists and false if not found
     */
    public function doesCollationExist($collation)
    {
        $this->real_query("SHOW COLLATION LIKE '" . $collation . "'");
        $res = new mysqli_result($this);

        while ($row = $res->fetch_array(MYSQLI_ASSOC)) {
            if (in_array($row['Collation'], [$collation])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the table exists
     *
     * @param string $table_name Name of the table to check
     * @return bool Returns TRUE if table exists, else FALSE if failure or not found.
     */
    public function table_exist($table_name)
    {
        $query = "SELECT COUNT(*)
        FROM information_schema.tables 
        WHERE table_schema = '" . self::$options['dbname'] . "' 
        AND table_name = '" . $table_name . "'";

        if( !$this->real_query($query) )
            return $this->handleError($this->error, $query, $this->errno);

        $result = new mysqli_result($this);
        $row = $result->fetch_row();
        if (!$row[0]) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Checks if a table has an index with the given name
     *
     * @return bool
     */
    public function index_exist($table, $indexName)
    {
        $query = 'SHOW INDEX FROM `' . $table . '`';
        if (!$this->real_query($query)) {
            return $this->handleError($this->error, $query, $this->errno);
        }

        $result = new mysqli_result($this);
        while ($row = $result->fetch_assoc()) {
            if ($row['Key_name'] == $indexName && $row['Seq_in_index'] == 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if a column exists in a table
     *
     * @return bool
     */
    public function col_exists($table, $col_name)
    {
        $query = 'SHOW COLUMNS FROM `' . $table . '`';
        if (!$this->real_query($query)) {
            return $this->handleError($this->error, $query, $this->errno);
        }

        $result = new mysqli_result($this);
        while ($row = $result->fetch_assoc()) {
            if ($row['Field'] == $col_name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Drops a column if it exists
     *
     * @return bool True if the column was dropped, false if it did not exist (or the drop failed with exceptions disabled)
     */
    public function drop_col_if_exists($table, $col)
    {
        if (!$this->col_exists($table, $col)) {
            return false;
        }

        $query = 'ALTER TABLE `' . $table . '` DROP COLUMN `' . $col . '`';
        if (!$this->real_query($query)) {
            return $this->handleError($this->error, $query, $this->errno);
        }
        return true;
    }
}