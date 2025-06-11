<?php

class DatabaseException20 extends Exception {
    private $sqlQuery;
    private $sqlError;
    private $sqlErrno;
    
    public function __construct($message, $query = '', $errno = 0) {
        $this->sqlQuery = $query;
        $this->sqlError = $message;
        $this->sqlErrno = $errno;
        parent::__construct($message, $errno);
    }
    
    public function getSqlQuery() { return $this->sqlQuery; }
    public function getSqlError() { return $this->sqlError; }
    public function getSqlErrno() { return $this->sqlErrno; }
}

class Mysqli20 extends mysqli {
    private $version = '2.0.0';
    
    protected static $instance;
    protected static $options = [];
    
    private static $useExceptions = true;
    private $lastError = null;

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

    public function __construct() {
        $o = self::$options;
        
        mysqli_report(MYSQLI_REPORT_OFF);
        
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
            throw new DatabaseException20($error, $query, $errno);
        }
        
        return false;
    }

    /**
     * @param string $query
     * @param int $resultmode
     * @return mysqli_result|false
     */
    #[\ReturnTypeWillChange]
    public function query(string $query, $resultmode = MYSQLI_STORE_RESULT)
    {
        if (!$this->real_query($query)) {
            return $this->handleError($this->error, $query, $this->errno);
        }
        
        return new \mysqli_result($this);
    }

    /**
     * @param string $query
     * @param mixed $return
     * @return mixed
     */
    public function query1($query, $return = null) {
        if (!$this->real_query($query)) {
            return $this->handleError($this->error, $query, $this->errno);
        }
        
        $result = new \mysqli_result($this);
        
        if ($return === 'insert_id') {
            return $this->insert_id;
        } else if ($return === 0) {
            $row = $result->fetch_row();
            return $row[0];
        } else if (is_numeric($return) && ($return > 0)) {
            $row = $result->fetch_row();
            return $row[(int)$return];
        } else {
            $row = $result->fetch_assoc();
            return $row;
        }
    }

    /**
     * @param string $query
     * @return mysqli_stmt|false
     */
    #[\ReturnTypeWillChange]
    public function prepare(string $query): mysqli_stmt|false {
        $stmt = parent::prepare($query);
        if (!$stmt) {
            return $this->handleError($this->error, $query, $this->errno);
        }
        return $stmt;
    }

    /**
     * @param mixed $sql
     * @param string|false $types
     * @param array|false $variables
     * @return int|false
     */
    public function prepared_insert($sql, $types = false, $variables = false) {
        if (is_array($sql)) {
            list($sql, $types, $variables) = $sql;
        }
        
        $stmt = $this->prepare($sql);
        if (!$stmt) {
            return false;
        }
        
        if ($types !== false && $variables !== false) {
            array_unshift($variables, $types);
            call_user_func_array([$stmt, 'bind_param'], $this->refValues($variables));
        }
        
        if (!$stmt->execute()) {
            return $this->handleError($stmt->error, $sql, $stmt->errno);
        }
        
        $result = $stmt->insert_id ?: $stmt->affected_rows;
        $stmt->close();
        return $result;
    }

    /**
     * @param mixed $sql
     * @param string|false $types
     * @param array|false $variables
     * @param string $results_processing
     * @return array|int|false
     */
    public function prepared_query($sql, $types = false, $variables = false, $results_processing = '') {
        if (is_array($sql)) {
            list($sql, $types, $variables) = $sql;
        }
        
        $stmt = $this->prepare($sql);
        if (!$stmt) {
            return false;
        }
        
        if ($types !== false && $variables !== false) {
            array_unshift($variables, $types);
            call_user_func_array([$stmt, 'bind_param'], $this->refValues($variables));
        }
        
        if (!$stmt->execute()) {
            return $this->handleError($stmt->error, $sql, $stmt->errno);
        }
        
        // Handle DELETE queries
        if (mb_strtoupper(substr($sql, 0, 12)) == 'DELETE FROM ') {
            $ret = $stmt->affected_rows;
            $stmt->close();
            return $ret;
        }
        
        // Handle SELECT queries
        $meta = $stmt->result_metadata();
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
        
        $stmt->close();
        return $result;
    }

    /**
     * @param mixed $sql
     * @param string|false $types
     * @param array|false $variables
     * @param string $return
     * @param mixed $onEmpty
     * @return mixed
     */
    public function prepared_query1($sql, $types = false, $variables = false, $return = 'default', $onEmpty = null) {
        if ((strlen($types) == 1) && !is_array($variables)) {
            $variables = [$variables];
        }
        
        $res = $this->prepared_query($sql, $types, $variables);
        
        if (!$res || !count($res)) {
            if ($onEmpty === null) {
                return null;
            } else {
                return $this->handleError('prepared_query1 should return results, came up empty!', is_array($sql) ? $sql[0] : $sql, 1);
            }
        }
        
        if ($return === 0) {
            return array_shift($res[0]);
        } else if ($return === true) {
            return empty($res) ? null : $res[0];
        } else {
            return $res[0];
        }
    }

    /**
     * @param string $sql
     * @param string $types
     * @param array $vars
     * @return int|false
     */
    public function prepared_delete($sql, $types, $vars) {
        $stmt = $this->prepare($sql);
        if (!$stmt) {
            return false;
        }
        
        $rc = $stmt->bind_param($types, ...$vars);
        if (!$rc) {
            return $this->handleError($stmt->error, $sql, $stmt->errno);
        }
        
        $rc = $stmt->execute();
        if (!$rc) {
            return $this->handleError($stmt->error, $sql, $stmt->errno);
        }
        
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $affected_rows;
    }

    /**
     * @param mixed $sql
     * @param string|false $typeDef
     * @param array|false $params
     * @return array|false
     */
    public function prepared_multiquery($sql, $typeDef = false, $params = false) {
        if (is_array($sql)) {
            list($sql, $typeDef, $params) = $sql;
        }
        
        $stmt = $this->prepare($sql);
        if (!$stmt) {
            return false;
        }
        
        if (count($params) == count($params, 1)) {
            $params = [$params];
            $multiQuery = false;
        } else {
            $multiQuery = true;
        }
        
        if ($typeDef) {
            $bindParams = [];
            $bindParamsReferences = [];
            $bindParams = array_pad($bindParams, (count($params, 1) - count($params)) / count($params), "");
            foreach ($bindParams as $key => $value) {
                $bindParamsReferences[$key] = &$bindParams[$key];
            }
            array_unshift($bindParamsReferences, $typeDef);
            $bindParamsMethod = new \ReflectionMethod('mysqli_stmt', 'bind_param');
            $bindParamsMethod->invokeArgs($stmt, $bindParamsReferences);
        }
        
        $result = [];
        foreach ($params as $queryKey => $query) {
            foreach ($bindParams as $paramKey => $value) {
                $bindParams[$paramKey] = $query[$paramKey];
            }
            $queryResult = [];
            if ($stmt->execute()) {
                $resultMetaData = $stmt->result_metadata();
                if ($resultMetaData) {
                    $stmtRow = [];
                    $rowReferences = [];
                    while ($field = mysqli_fetch_field($resultMetaData)) {
                        $rowReferences[] = &$stmtRow[$field->name];
                    }
                    mysqli_free_result($resultMetaData);
                    $bindResultMethod = new \ReflectionMethod('mysqli_stmt', 'bind_result');
                    $bindResultMethod->invokeArgs($stmt, $rowReferences);
                    while ($stmt->fetch()) {
                        $row = [];
                        foreach ($stmtRow as $key => $value) {
                            $row[$key] = $value;
                        }
                        $queryResult[] = $row;
                    }
                    $stmt->free_result();
                } else {
                    $queryResult[] = $stmt->affected_rows;
                }
            } else {
                return $this->handleError($stmt->error, $sql, $stmt->errno);
            }
            $result[$queryKey] = $queryResult;
        }
        $stmt->close();
        
        return $multiQuery ? $result : $result[0];
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
        // Handle array format
        if (is_array($sql)) {
            list($sql, $types, $params) = $sql;
        }
        
        $stmt = $this->prepare($sql);
        if (!$stmt) {
            return false;
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
            return $this->handleError($stmt->error, $sql, $stmt->errno);
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
     * Execute batch prepared statements
     * 
     * @param string $sql SQL with placeholders
     * @param string $types Type definition string
     * @param array $paramSets Array of parameter arrays
     * @return array Results for each execution
     */
    public function executeBatch($sql, $types, $paramSets) {
        if (count($paramSets) == count($paramSets, 1)) {
            $paramSets = [$paramSets];
        }
        
        $stmt = $this->prepare($sql);
        if (!$stmt) {
            return false;
        }
        
        // Validate type string length
        $expectedCount = strlen($types);
        
        $bindParams = array_pad([], $expectedCount, '');
        $bindParamsReferences = [];
        foreach ($bindParams as $key => $value) {
            $bindParamsReferences[$key] = &$bindParams[$key];
        }
        array_unshift($bindParamsReferences, $types);
        
        $bindParamsMethod = new \ReflectionMethod('mysqli_stmt', 'bind_param');
        $bindParamsMethod->invokeArgs($stmt, $bindParamsReferences);
        
        $results = [];
        foreach ($paramSets as $index => $params) {
            // Validate each parameter set
            $actualCount = count($params);
            if ($expectedCount !== $actualCount) {
                $results[] = $this->handleError(
                    "Batch item {$index}: Parameter count mismatch. Expected {$expectedCount} (types: '{$types}'), got {$actualCount}",
                    $sql,
                    0
                );
                continue;
            }
            
            foreach ($params as $key => $value) {
                $bindParams[$key] = $value;
            }
            
            if ($stmt->execute()) {
                $queryType = strtoupper(substr(trim($sql), 0, 6));
                
                if ($queryType === 'SELECT') {
                    $meta = $stmt->result_metadata();
                    if ($meta) {
                        $row = [];
                        $rowReferences = [];
                        while ($field = $meta->fetch_field()) {
                            $rowReferences[] = &$row[$field->name];
                        }
                        $bindResultMethod = new \ReflectionMethod('mysqli_stmt', 'bind_result');
                        $bindResultMethod->invokeArgs($stmt, $rowReferences);
                        
                        $queryResult = [];
                        while ($stmt->fetch()) {
                            $c = [];
                            foreach ($row as $k => $v) {
                                $c[$k] = $v;
                            }
                            $queryResult[] = $c;
                        }
                        $results[] = $queryResult;
                        $stmt->free_result();
                    }
                } else if ($queryType === 'INSERT') {
                    $results[] = $stmt->insert_id ?: $stmt->affected_rows;
                } else {
                    $results[] = $stmt->affected_rows;
                }
            } else {
                $results[] = $this->handleError($stmt->error, $sql, $stmt->errno);
            }
        }
        
        $stmt->close();
        return $results;
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
        $res = new \mysqli_result($this);

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

    public function table_exist($table_name)
    {
        $query = "SELECT COUNT(*)
        FROM information_schema.tables 
        WHERE table_schema = '" . self::$options['dbname'] . "' 
        AND table_name = '" . $table_name . "'";

        if( !$this->real_query($query) )
            return $this->process_error($this->error, $query, $this->errno, '', __METHOD__);

        $result = new \mysqli_result($this);
        $row = $result->fetch_row();
        if (!$row[0]) {
            return false;
        } else {
            return true;
        }
    }

}
