<?php

class Mysqli2 extends mysqli {
    private $version = '2.0.2';
    
    private static $devMode = false; // Default: production

    protected static $instance;
    protected static $options = [];
    
    private static $useExceptions = true;
    private static $errorHandler = null;
    private static $inErrorHandler = false;
    private static $prefix = '';
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
            self::$instance = new static();
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
        
        // Errors are reported by handleError() only, so dev and prod behave the same
        mysqli_report(MYSQLI_REPORT_OFF);

        if (empty($o['user']) || empty($o['dbname'])) {
            $error = 'Mysqli2: user and database must be set, use getInstance() or setOptions()';
            $this->handleError($error);
            throw new DatabaseException($error); // config error: never continue unconnected
        }

        @parent::__construct(
            $o['host'] ?? 'localhost',
            $o['user'],
            $o['pass'] ?? '',
            $o['dbname'],
            $o['port'] ?? 3306,
            empty($o['sock']) ? null : $o['sock']
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
     * Called by handleError() with ($error, $query, $errno) before it throws or returns false,
     * so every database error can be logged in one place.
     *
     * @param callable|null $fn null removes the handler
     * @return void
     */
    public static function setErrorHandler(?callable $fn) {
        self::$errorHandler = $fn;
    }

    /**
     * Table prefix used by t(), insert(), update(), count() and exists()
     *
     * @param string $prefix
     * @return void
     */
    public static function setPrefix($prefix) {
        self::$prefix = (string)$prefix;
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
        // Guard: a handler that logs to the database must not loop on its own errors
        if (self::$errorHandler && !self::$inErrorHandler) {
            self::$inErrorHandler = true;
            try {
                (self::$errorHandler)($error, $query, $errno);
            } catch (\Throwable $e) {
                // A failing logger must not replace the original error
                error_log('Mysqli2 error handler failed: ' . $e->getMessage());
            } finally {
                self::$inErrorHandler = false;
            }
        }

        // Set after the handler, so errors from the handler's own queries don't overwrite it
        $this->lastError = [
            'error' => $error,
            'query' => $query,
            'errno' => $errno
        ];

        if (self::$useExceptions) {
            // Dev shows the SQL in the message; prod keeps it in getSqlQuery() only
            $message = self::$devMode && $query !== '' ? $error . ' | SQL: ' . $query : $error;
            throw new DatabaseException($error, $query, $errno, $message);
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

        // No result set (INSERT/UPDATE/DDL): true, like mysqli::query()
        if (!$this->field_count) {
            return true;
        }

        $result = new \mysqli_result($this);
        if ($filter === null) {
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
            
            // Ask the statement, not the SQL text: also covers SHOW, WITH, (SELECT ...) and leading comments
            $meta = $stmt->result_metadata();
            if (!$meta) {
                // No result set: INSERT gives its id (insert_id is 0 for UPDATE/DELETE), else affected rows
                $result = $stmt->insert_id ?: $stmt->affected_rows;
            } else {
                $row = [];
                $params = [];
                while ($field = $meta->fetch_field()) {
                    $params[] = &$row[$field->name];
                }

                call_user_func_array([$stmt, 'bind_result'], $params);

                $result = [];
                while ($stmt->fetch()) {
                    $c = [];
                    foreach ($row as $key => $val) {
                        $c[$key] = $val;
                    }
                    $result[] = $c;
                }
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
        if ($result === false) {
            return false; // already reported by execute()
        }

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
        return (bool)$this->execute1('SELECT COUNT(*) FROM information_schema.collations WHERE collation_name = ?', 's', [$collation], 0);
    }

    /**
     * Prefixed, quoted table name: t('eu_news') gives `{prefix}eu_news`
     *
     * @param string $table Table name without prefix
     * @return string
     */
    public function t($table)
    {
        return $this->quoteIdent(self::$prefix . $table);
    }

    /**
     * INSERT the values queued in a sqlbuddy object, as a prepared statement
     *
     * @param string $table Table name without prefix
     * @param sqlbuddy $sql
     * @return int|false insert_id (affected rows if the table has no AUTO_INCREMENT)
     */
    public function insert($table, $sql)
    {
        // prepared_build() adds the backticks itself
        return $this->execute($sql->prepared_build('insert', self::$prefix . $table));
    }

    /**
     * UPDATE with the values queued in a sqlbuddy object, as a prepared statement.
     * Example: $mysqli->update('eu_news', $sql, 'id=?', [$id]);
     *
     * @param string $table Table name without prefix
     * @param sqlbuddy $sql
     * @param string $where WHERE clause with ? placeholders
     * @param array $params Values for the placeholders, bound as i (int), d (float) or s (everything else)
     * @return int|false Affected rows
     */
    public function update($table, $sql, $where, array $params = [])
    {
        [$query, $types, $values] = $sql->prepared_build('update', self::$prefix . $table, $where);
        foreach ($params as $p) {
            $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
            $values[] = $p;
        }
        return $this->execute($query, $types, $values);
    }

    /**
     * COUNT(*) without fetching the rows
     *
     * @param string $table Table name without prefix
     * @param string $where Optional WHERE clause with ? placeholders
     * @return int|false
     */
    public function count($table, $where = '', $types = '', $params = [])
    {
        $query = 'SELECT COUNT(*) FROM ' . $this->t($table) . ($where !== '' ? ' WHERE ' . $where : '');
        $n = $this->execute1($query, $types, $params, 0);
        return $n === false ? false : (int)$n;
    }

    /**
     * Does at least one row match?
     *
     * @param string $table Table name without prefix
     * @param string $where WHERE clause with ? placeholders
     * @return bool
     */
    public function exists($table, $where, $types = '', $params = [])
    {
        if ($types && !is_array($params)) {
            $params = [$params];
        }
        $result = $this->execute('SELECT 1 FROM ' . $this->t($table) . ' WHERE ' . $where . ' LIMIT 1', $types, $params);
        return is_array($result) && count($result) > 0;
    }

    /**
     * All rows as assoc arrays, short for result('assoc')->query($sql)
     *
     * @param string $sql
     * @return array|false
     */
    public function rows($sql)
    {
        return $this->result('assoc')->query($sql);
    }

    /**
     * First row as assoc array (null if none), short for query1($sql)
     *
     * @param string $sql
     * @return array|null|false
     */
    public function row($sql)
    {
        return $this->query1($sql);
    }

    /**
     * Quote an identifier (table/column name) for use in SQL
     *
     * @param string $name
     * @return string
     */
    private function quoteIdent($name)
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    /**
     * @param string $table
     * @return int Row count
     */
    public function get_row_count($table)
    {
        // COUNT(*) is exact on every engine; InnoDB scans its smallest index for it
        return (int)$this->query1('SELECT COUNT(*) FROM ' . $this->quoteIdent($table), 0);
    }

    /**
     * Checks if the table exists in the current database
     *
     * @param string $table
     * @return bool
     */
    public function table_exist($table)
    {
        return (bool)$this->execute1(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            's', [$table], 0
        );
    }

    /**
     * Checks if a table has an index with the given name
     *
     * @return bool
     */
    public function index_exist($table, $indexName)
    {
        return (bool)$this->execute1(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? AND seq_in_index = 1',
            'ss', [$table, $indexName], 0
        );
    }

    /**
     * Checks if a column exists in a table
     *
     * @return bool
     */
    public function col_exists($table, $col_name)
    {
        return (bool)$this->execute1(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            'ss', [$table, $col_name], 0
        );
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
        return (bool)$this->query('ALTER TABLE ' . $this->quoteIdent($table) . ' DROP COLUMN ' . $this->quoteIdent($col));
    }
}