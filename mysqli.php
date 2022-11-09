<?php

/**
 * Mysqli Abstraction Layer v1.3.2
 *
 * Description:
 * Mainly for development and logging of queries, but now that the class is up and running
 * future releases should be expected to do the heavy lifting of queries and iteration.
 *
 * Maintained by: @steinhaug
 *
 * Chainable functions:
 *
 *      ->log(), ->return() and ->echo()
 *      Example: $mysqli->log('Query for new items')->query($sql);
 *               $mysqli->log('types')->return('array','int=>string')->query($sql);
 * 
 * Function overview:
 *
 * ->query($sql)
 *   Default query method for SQL queries, with logggin abilities
 * ->query1($sql, $mode)
 *   Queries with only one result, result is returned.
 * ->get_row_count($tablename)
 *   Return row count of table
 * ->get_engine($tablename)
 *   Return the engine being used for a table
 * ->table_exist($table_name)
 *   Returns boolean for if table exist
 * ->col_exists(€column_name)
 *   Returns boolean for if column exist
 * ->drop_col_if_exists($column_name)
 *   Drops column if it exists and returns boolean
 * ->return_charset_and_collate()
 *   Gives you the local charsets for the database needed for collate
 * ->return_full_columns($table_name)
 *   Fetches all the metadata for a given table
 * ->parse_col_type($column_name)
 *   internal function for how to handle the data from the different column types
 * ->query_exporter(...)
 *   Short hand framework to create SQL insert queries from a SELECT statement.
 * ->prepared_query(...)
 *   Run a single prepared query
 * ->prepared_multiquery(...)
 *   Run a single, or multi prepared statment query
 */
class Mysqli2 extends mysqli
{

    private $version = '1.3';

    protected static $instance;
    protected static $options = [];

    protected static $verbose_level = 0;
    protected static $verbose_queries = false;
    protected static $verbose_type = 'html';

    protected static $log_once = false;
    protected static $log_once_tag = '';

    protected static $echo_once = false;
    protected static $echo_once_pre  = '<pre><code class="sql">';
    protected static $echo_once_post = '</code></pre>';

    protected static $query_exporter_settings = [];
    protected static $array_full_columns = null;

    protected $result_filter = null;

    static $logfile_folder_path = 'D:/htdocs/fimo/logs';

    public function getVersion()
    {
        return $this->version;
    }

    public static function set_logfile_path($path)
    {
        self::$logfile_folder_path = $path;
    }

    public function __construct()
    {
        $o = self::$options;

        // turn of error reporting
        mysqli_report(MYSQLI_REPORT_OFF);

        // connect to database
        @parent::__construct(
            isset($o['host']) ? $o['host'] : 'localhost',
            isset($o['user']) ? $o['user'] : 'root',
            isset($o['pass']) ? $o['pass'] : '',
            isset($o['dbname']) ? $o['dbname'] : 'world',
            isset($o['port']) ? $o['port'] : 3306,
            isset($o['sock']) ? $o['sock'] : false
        );

        // check if a connection established
        if (mysqli_connect_errno()) {
            throw new exception(mysqli_connect_error(), mysqli_connect_errno());
        }
    }

    public static function getInstance($host = null, $port = null, $user = null, $password = null, $database = null, $sock = false)
    {
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
     * Setting the options
     */
    public static function setOptions(array $opt)
    {
        self::$options = array_merge(self::$options, $opt);
    }

    /**
     * Ment for enabling the log_once feature, the next SQL query will be logged-
     *
     * Usage: $mysqli->log()->query($sql);
     */
    public function log($title = '')
    {
        $_db = debug_backtrace(2);

        $file_path = $_db[0]['file'];
        $file = pathinfo($_db[0]['file']);
        $dir_old = $file['dirname'];
        $dir_new = dirname($dir_old);
        $len = strlen($dir_new);
        $cut = str_replace(['/', '\\'], ['', ''], substr($dir_old, $len));
        $out_path = $cut;
        if ($cut == 'www') {
            $file_path = './' . $out_path;
        } else {
            $dir_old = $dir_new;
            $dir_new = dirname($dir_old);
            $len = strlen($dir_new);
            $cut = str_replace(['/', '\\'], ['', ''], substr($dir_old, $len));
            $out_path = $cut . '/' . $out_path;
            if ($cut == 'www') {
                $file_path = './' . $out_path;
            } else {
                $dir_old = $dir_new;
                $dir_new = dirname($dir_old);
                $len = strlen($dir_new);
                $cut = str_replace(['/', '\\'], ['', ''], substr($dir_old, $len));
                $out_path = $cut . '/' . $out_path;
                $file_path = './' . $out_path;
            }
        }

        self::$log_once = true;
        self::$log_once_tag = $file_path . ':' . $_db[0]['line'] . ' ' . $title;

        return $this;
    }

    /**
     * Chain-filter for query() funksjon, preprocessing av spørringen
     *
     * Mulige $param1 :
     *      array: resultatsettet returnert som en array
     *      array, 'int': resultatsettet er bare tall
     *      array, '[int]': resultatsettet er en array med tallet
     *      array, 'int=>string': resultatsettet er en associative array hvor key og value er fra kolonne 1 og 2 fra query
     */
    public function result($result_filter, $typeof = null)
    {

        $this->result_filter = [$result_filter, $typeof];

        return $this;
    }

    /**
     * Enable syntax highlighted QUERY on page
     */
    public function echo()
    {
        self::$echo_once = true;
        return $this;
    }

    /**
     * Performs a query on the database
     *
     * @param string $query The query string.
     * @param mixed $resultmode Void
     * 
     * @return Returns FALSE on failure. For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries query() will return a mysqli_result object. For other successful queries query() will return TRUE.
     */
    public function query($query, $resultmode = null)
    {

        if (self::$log_once) {
            self::$log_once = false;
            if (!empty(self::$log_once_tag)) {
                $this->write_to_logfile(self::$log_once_tag);
                self::$log_once_tag = '';
            }
            $this->write_to_logfile($query);
        }

        if (self::$echo_once) {
            self::$echo_once = false;
            echo self::$echo_once_pre . htmlentities($query, ENT_QUOTES, "UTF-8") . self::$echo_once_post;
            echo $this->highlighterLibrary();
            echo $this->debugTheQuery($query);
        }

        if (self::$verbose_queries) {
            if (self::$verbose_type == 'plain') {
                echo $query . "\n";
            } else {
                echo htmlentities($query) . '<br>';
            }
        } elseif (self::$verbose_level) {
            echo '.';
        }

        if (!$this->real_query($query)) {
            $this->write_to_logfile('sqlerror: ' . $this->errno . ', ' . $this->error);
            $this->write_to_logfile($query);
            throw new exception($this->error, $this->errno);
        }

        $result = new mysqli_result($this);

        $result_filter = $this->result_filter;
        $this->result_filter = null;

        if( $result_filter !== null ){
            if(is_array($result_filter) and ($result_filter[0] === 'array')){
                $new_result = [];
                if( $result->num_rows ){
                    while ($row = $result->fetch_row()) {

                        //echo count($row) . '<br>';
                        //echo implode('', array_keys($row)) . '<br>';
                        //echo implode('', array_values($row)) . '<br>';
                        if($result_filter[1] === null){

                            if( $result->field_count == 1 )
                                $new_result[] = $row[0];
                                else
                                $new_result[] = $row;

                        } else {

                            $value = $row[0];
                            if( $result_filter[1] === 'int' )
                                $value = (int) $value;
                            if( $result_filter[1] === '[int]' )
                                $value = [ (int) $value ];
                            if( ($result_filter[1] === 'int=>string') 
                                or
                                ($result_filter[1] === 'string=>string') )
                                $value = (string) $row[1];

                            if ( substr($result_filter[1], 0, 5) === 'int=>' ) {
                                $new_result[ (int) $row[0] ] = $value;
                            } else if( substr($result_filter[1], 0, 8) === 'string=>' ) {
                                $new_result[ (string) $row[0] ] = $value;
                            } else {
                                $new_result[] = $value;
                            }

                        }

                    }
                }
            }

            if( isset($new_result) ){
                return $new_result;
            }
        }


        return $result;
    }

    /**
     * When the select always returns 1 result, then return the value(s).
     *
     * Return types: 0, first index of result. Else the total result.
     *
     * @return string The value corresponding to [0] from the rowset
     */
    public function query1($query, $return = null)
    {

        if (self::$log_once) {
            self::$log_once = false;
            if (!empty(self::$log_once_tag)) {
                $this->write_to_logfile(self::$log_once_tag);
                self::$log_once_tag = '';
            }
            $this->write_to_logfile($query);
        }

        if (self::$echo_once) {
            self::$echo_once = false;
            echo self::$echo_once_pre . htmlentities($query, ENT_QUOTES, "UTF-8") . self::$echo_once_post;
            echo $this->highlighterLibrary();
            echo $this->debugTheQuery($query);
        }

        $this->real_query($query);
        $result = new mysqli_result($this);
        if ($return === 0) {
            $row = $result->fetch_row();
            return $row[0];
        } else if( is_numeric($return) and ($return > 0) ){
            $row = $result->fetch_row();
            return $row[ (int) $return ];
        } else {
            $row = $result->fetch_assoc();
            return $row;
        }
    }

    public function insert_multi_query($multi_query){

        // https://stackoverflow.com/questions/14715889/strict-standards-mysqli-next-result-error-with-mysqli-multi-query
        $affected_rows = 0;
        if( $this->multi_query($multi_query) ){
            do{
                $affected_rows+=$this->affected_rows;
            } while( $this->more_results() && $this->next_result() );
        }
        if( $this->error ){
            echo "SQL Error:<br>" . $this->error;
        }

        return $affected_rows;
    }



    /**
     * Get the row count for a table.
     *
     * @return int Row count
     */
    public function get_row_count($tablename)
    {
        if ($this->get_engine($tablename) == 'MyISAM') {
            $this->real_query('SELECT COUNT(*) FROM `' . $tablename . '`');
            $result = new mysqli_result($this);
            $row = $result->fetch_row();
            return (int) $row[0];
        } else {
            $cols = $this->return_full_columns($tablename);
            $col = array_shift($cols);
            $id = $col['Field'];
            $this->real_query('SELECT COUNT(`' . $id . '`) FROM `' . $tablename . '`');
            $result = new mysqli_result($this);
            $row = $result->fetch_row();
            return (int) $row[0];
        }
    }

    /**
     * Get the engine being used for a table
     *
     * @return string Engine name. InnoDB or MyISAM
     */
    public function get_engine($tablename)
    {
        $query = "SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='" . $tablename . "' AND TABLE_SCHEMA='" . self::$options['dbname'] . "'";
        return $this->query1($query,0);
    }

    public function prepare($query)
    {
        $stmt = new mysqli_stmt($this, $query);
        return $stmt;
    }

    /**
     * Checks if the table exists
     *
     * @return Returns TRUE if table exists, else FALSE if failure or not found.
     */
    public function table_exist($table_name)
    {
        $query = "SELECT COUNT(*)
        FROM information_schema.tables 
        WHERE table_schema = '" . self::$options['dbname'] . "' 
        AND table_name = '" . $table_name . "'";

        if (self::$log_once) {
            self::$log_once = false;
            if (!empty(self::$log_once_tag)) {
                $this->write_to_logfile(self::$log_once_tag);
                self::$log_once_tag = '';
            }
            $this->write_to_logfile($query);
        }

        if (self::$echo_once) {
            self::$echo_once = false;
            echo self::$echo_once_pre . htmlentities($query, ENT_QUOTES, "UTF-8") . self::$echo_once_post;
            echo $this->highlighterLibrary();
            echo $this->debugTheQuery($query);
        }

        $this->real_query($query);
        $result = new mysqli_result($this);
        $row = $result->fetch_row();
        if (!$row[0]) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Check if a table has a specific index in place
     */
    public function index_exist($table_name, $index_name)
    {
        $query = 'SHOW INDEX FROM ' . $table_name . '';

        if (self::$log_once) {
            self::$log_once = false;
            if (!empty(self::$log_once_tag)) {
                $this->write_to_logfile(self::$log_once_tag);
                self::$log_once_tag = '';
            }
            $this->write_to_logfile($query);
        }

        if (self::$echo_once) {
            self::$echo_once = false;
            echo self::$echo_once_pre . htmlentities($query, ENT_QUOTES, "UTF-8") . self::$echo_once_post;
            echo $this->highlighterLibrary();
            echo $this->debugTheQuery($query);
        }

        $this->real_query($query);
        $result = new mysqli_result($this);

        $match = false;

        if ($result->num_rows) {
            while ($row = $result->fetch_assoc()) {
                if (($row['Key_name'] == $index_name) and ($row['Seq_in_index'] == 1)) {
                    $match = true;
                    break;
                }
            }
        }

        return $match;
    }

    /**
     * Check if table has the column
     *
     * @param string $table
     * @param string $col_name
     * 
     * @return bool true on success, false on failure
     */
    public function col_exists($table, $col_name)
    {
        $query = 'SHOW COLUMNS FROM `' . $table . '`';

        if (self::$log_once) {
            self::$log_once = false;
            if (!empty(self::$log_once_tag)) {
                $this->write_to_logfile(self::$log_once_tag);
                self::$log_once_tag = '';
            }
            $this->write_to_logfile($query);
        }

        if (self::$echo_once) {
            self::$echo_once = false;
            echo self::$echo_once_pre . htmlentities($query, ENT_QUOTES, "UTF-8") . self::$echo_once_post;
            echo $this->highlighterLibrary();
            echo $this->debugTheQuery($query);
        }

        $this->real_query($query);

        $result = new mysqli_result($this);
        $cols = [];
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            if ($row['Field'] == $col_name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if table has the column, if found the column is deleted.
     *
     * @return bool true on success, false on failure
     */
    public function drop_col_if_exists($table, $col)
    {
        if ($this->col_exists($table, $col)) {
            $this->real_query('ALTER TABLE `' . $table . '` DROP COLUMN `' . $col . '`;');
            return true;
        }
        return false;
    }

    /**
     * Return working collate charsets from mysql
     *
     * @return array [ charset => collate charset ]
     */
    public function return_charset_and_collate()
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

        return $collate;
    }

    /**
     * Get the column data from a table
     *
     * @return array Column names and column meta data
     */
    public function return_full_columns($table)
    {
        $table_data = [];
        $this->real_query('SHOW FULL COLUMNS FROM `' . $table . '`');
        $res = new mysqli_result($this);
        $count = $res->num_rows;
        if ($count) {
            for ($i = 0;$i < $count;$i++) {
                $item = $res->fetch_array(MYSQLI_ASSOC);
                $table_data[$item['Field']] = $item;
            }
        }
        return $table_data;
    }

    /**
     * Simplify the type of column for further processing
     *
     * @param string $needle
     * @param array $full_table_reference
     * 
     * @return void
     */
    public function parse_col_type($needle, $full_table_reference = null)
    {
        if ($full_table_reference === null) {
            $full_table_reference = self::$array_full_columns;
            if (self::$array_full_columns === null) {
                throw new exception('parse_col_type error, missing table reference.', 1);
            }
        }

        $match = $full_table_reference[$needle]['Type'];
        if (preg_match('/^int/i', $match)) {
            if ($this->_bool($full_table_reference[$needle]['Null'])) {
                return 'intornull';
            } else {
                return 'int';
            }
        }
        if (preg_match('/^smallint/i', $match)) {
            if ($this->_bool($full_table_reference[$needle]['Null'])) {
                return 'intornull';
            } else {
                return 'int';
            }
        }
        if (preg_match('/^tinyint/i', $match)) {
            if ($this->_bool($full_table_reference[$needle]['Null'])) {
                return 'intornull';
            } else {
                return 'int';
            }
        }
        if (preg_match('/^decimal/i', $match)) {
            return 'dec';
        }
        if (preg_match('/^datetime/i', $match)) {
            if ($this->_bool($full_table_reference[$needle]['Null'])) {
                return 'datetimeornull';
            } else {
                return 'datetime';
            }
        }
        if (preg_match('/^date/i', $match)) {
            if ($this->_bool($full_table_reference[$needle]['Null'])) {
                return 'dateornull';
            } else {
                return 'date';
            }
        }
        if (preg_match('/^varchar/i', $match)) {
            if ($this->_bool($full_table_reference[$needle]['Null'])) {
                return 'ornull';
            } else {
                return 'str';
            }
        }
        return 'str';
    }

    /**
     * Init the query exporter, we need a table to get the meta from
     *
     * @param string $table
     * 
     * @return void
     */
    public function new_query_exporter($table)
    {
        self::$array_full_columns = $this->return_full_columns($table);
        self::$query_exporter_settings = [
            'table' => $table,
            'query_type' => 'insert',
            'use_column_names' => true,
            'skip_primary_auto_increment_col' => true,
            'extended_inserts_max' => 250
        ];
    }

    /**
     * Undocumented function
     *
     * @param array $acc_arr
     * @param string $query_type
     *
     * @return void
     */
    public function query_exporter($acc_arr, $query_type = 'insert')
    {
        $sql = '';

        $keys = [];
        $vals = [];

        foreach ($acc_arr as $key => $val) {
            if (self::$query_exporter_settings['skip_primary_auto_increment_col']) {
                if ((self::$array_full_columns[$key]['Key'] == 'PRI') and (self::$array_full_columns[$key]['Extra'] == 'auto_increment')) {
                    continue;
                }
            }

            $keys[] = '`' . $key . '`';
            $vals[] = $this->qe__make_key($key, $val);
        }

        if ($query_type == 'extend') {
            return ', (' . implode(',', $vals) . ')';
        }

        $o = 'INSERT INTO `' . self::$query_exporter_settings['table'] . '`';
        if (self::$query_exporter_settings['use_column_names']) {
            $o .= ' (' . implode(',', $keys) . ')';
        }
        $o .= ' VALUES (' . implode(',', $vals) . ')';

        if ($query_type == 'insert') {
            $o .= ';';
        }

        return $o;
    }

    /**
     * Query_exporter: make the value for the SQL query, including possible quotes
     *
     * @return string SQL ready and escaped value for use in a query
     */
    public function qe__make_key($key, $val)
    {
        if (self::$array_full_columns === null) {
            throw new exception('function qe__make_key is not initialized, you need to envoke new_query_exporter()', 1);
        }

        $str_the_key = '`' . $key . '`';
        $type = $this->parse_col_type($key);
        $quote_char = '\'';
        $null_allowed = false;

        if (strpos($type, 'ornull') !== false) {
            $type = str_replace('ornull', '', $type);
            $null_allowed = true;
        }

        if ($null_allowed and ($this->considered_null($val) or empty($val))) {
            $str_the_value = 'NULL';
        } else {
            switch ($type) {
                case 'date':
                    $str_the_value = $quote_char . mysqli_fix_sloppydate($val, 'sql') . $quote_char;
                    break;
                case 'datetime':
                    $str_the_value = $quote_char . mysqli_fix_sloppydate($val, 'sql') . ' ' . mysqli_fix_sloppydate($val, 'datetime2time') . $quote_char;
                    break;
                case 'dec':
                    $str_the_value = fix_make_number($val);
                    break;
                case 'int':
                    $str_the_value = (int) $val;
                    break;
                default:
                    $str_the_value = $quote_char . $this->real_escape_string($val) . $quote_char;
            }
        }

        return $str_the_value;
    }

    /**
     * Is the value a classical NULL for the SQL
     *
     * @return boolean True or false
     */
    public function considered_null($val)
    {
        if ($val === false) {
            return true;
        }

        if (!strlen($val)) {
            return true;
        }

        if (strtolower($val) === 'null') {
            return true;
        }

        return false;
    }


    /**
     * Quick function to export a table into INSERT statments.
     *
     * Example: $mysqli->export_tabe('tablename');
     *          $mysqli->export_tabe('tablename 10');   <- will become a limit 0,10 ment for testing
     * 
     * @param string $table_name Table name to export
     * 
     * @return A block of text with INSERT statments.
     */
    public function export_table($table_name)
    {

        if(strpos($table_name, ' ')!==false){
            $p = explode(' ', $table_name);
            $this->real_query('SELECT * FROM `' . $p[0] . '` limit 0,' . (int) $p[1]);
            $result = new mysqli_result($this);
            $this->new_query_exporter($p[0]);
        } else {
            $this->real_query('SELECT * FROM `' . $table_name . '`');
            $result = new mysqli_result($this);
            $this->new_query_exporter($table_name);
        }

        $buffer = '';
        if ($result->num_rows) {

            $extended_insert_item_count = 0;
            while($row = $result->fetch_assoc()){

                if(!$extended_insert_item_count)
                    $sql  = $this->query_exporter($row, 'extended_insert');
                    else
                    $sql .= $this->query_exporter($row, 'extend');

                if( $extended_insert_item_count and !($extended_insert_item_count % self::$query_exporter_settings['extended_inserts_max']) ){
                    $extended_insert_item_count = -1;
                    $sql .= ';';
                    $buffer .= $sql . "\n";
                }

                $extended_insert_item_count++;
            }

            if(($extended_insert_item_count > 0) and strlen($sql)){
                $sql .= ';';
                $buffer .= $sql . "\n";
            }

        }

        return $buffer;

    }


    /* alfa setup, no scheme is in effect */
    public function debug_queries($type = 'plain')
    {
        self::$verbose_queries = true;
        self::$verbose_type = $type;
    }

    /* alfa setup, no scheme is in affect */
    public function verbose($lv)
    {
        self::$verbose_level = $lv;
    }

    /**
     * Function to write a line into a file, currently will only write to file if file already exist.|
     *
     * @param string $the_string
     * @param string $file
     * 
     * @return void
     */
    public function write_to_logfile($the_string, $file = 'sqllog')
    {
        if (file_exists(self::$logfile_folder_path . '/' . $file . '.log')) {
            if ($fh = @fopen(self::$logfile_folder_path . '/' . $file . '.log', 'a+')) {
                fputs($fh, $the_string . "\n", strlen($the_string . "\n"));
                fclose($fh);
                return(true);
            }
        }
    }

    /**
     * Run a prepared statment, results as associated array. Supports multi-query.
     *
     * $sql = "SELECT * FROM table WHERE id=? and anotherid=?";
     * $typ = "ii";
     * $variables = [[$id, 2], [$id, 3]];
     * $mysqli->prepared_multiquery($sql, $typ, $variables);
     *
     * $sql = "SELECT * FROM table WHERE id=?";
     * $typ = "i";
     * $variables = [$id];
     * $mysqli->prepared_multiquery($sql, $typ, $variables);
     *
     * @param string $sql The query, as a string.
     * @param string $types A string that contains one or more characters which specify the types for the corresponding bind variables.
     * @param array $variables The number of variables and length of string types must match the parameters in the statement.
     * 
     * @return Associated array from result set.
     */
    public function prepared_multiquery($sql, $typeDef = false, $params = false)
    {
        $link = $this;

        if( is_array($sql) ){
            list($sql, $typeDef, $params) = $sql;
        }

        if (self::$log_once) {
            self::$log_once = false;
            if (!empty(self::$log_once_tag)) {
                $this->write_to_logfile(self::$log_once_tag);
                self::$log_once_tag = '';
            }
            $this->write_to_logfile($sql);
        }

        if (self::$echo_once) {
            self::$echo_once = false;
            echo self::$echo_once_pre . htmlentities($sql, ENT_QUOTES, "UTF-8") . self::$echo_once_post;
            echo $this->highlighterLibrary();
            echo $this->debugTheQuery($sql);
        }

        if($stmt = mysqli_prepare($link, $sql)){
            if(count($params) == count($params, 1)){
                $params = [$params];
                $multiQuery = false;
            } else {
                $multiQuery = true;
            } 

            if($typeDef){
                $bindParams = [];   
                $bindParamsReferences = [];
                $bindParams = array_pad($bindParams, (count($params, 1)-count($params))/count($params), "");        
                foreach($bindParams as $key => $value){
                    $bindParamsReferences[$key] = &$bindParams[$key]; 
                }
                array_unshift($bindParamsReferences, $typeDef);
                $bindParamsMethod = new ReflectionMethod('mysqli_stmt', 'bind_param');
                $bindParamsMethod->invokeArgs($stmt, $bindParamsReferences);
            }

            $result = [];
            foreach($params as $queryKey => $query){
                foreach($bindParams as $paramKey => $value){
                    $bindParams[$paramKey] = $query[$paramKey];
                }
                $queryResult = [];
                if(mysqli_stmt_execute($stmt)){
                    $resultMetaData = mysqli_stmt_result_metadata($stmt);
                    if($resultMetaData){                                                                              
                        $stmtRow = [];  
                        $rowReferences = [];
                        while ($field = mysqli_fetch_field($resultMetaData)){
                            $rowReferences[] = &$stmtRow[$field->name];
                        }                               
                        mysqli_free_result($resultMetaData);
                        $bindResultMethod = new ReflectionMethod('mysqli_stmt', 'bind_result');
                        $bindResultMethod->invokeArgs($stmt, $rowReferences);
                        while(mysqli_stmt_fetch($stmt)){
                            $row = [];
                            foreach($stmtRow as $key => $value){
                                $row[$key] = $value;          
                            }
                            $queryResult[] = $row;
                        }
                        mysqli_stmt_free_result($stmt);
                    } else {
                        $queryResult[] = mysqli_stmt_affected_rows($stmt);
                    }
                } else {
                    $queryResult[] = false;
                }
                $result[$queryKey] = $queryResult;
            }
            mysqli_stmt_close($stmt);  
        } else {
            $result = false;
        }

        if($multiQuery){
            return $result;
        } else {
            return $result[0];
        }
    }

    /**
     * Run a prepared statment, results as associated array
     *
     * $sql = "SELECT * FROM table WHERE id=?";
     * $typ = "i";
     * $variables = [$id];
     * $mysqli->prepared_query($sql, $typ, $variables);
     *
     * @param string $sql The query, as a string.
     * @param string $types A string that contains one or more characters which specify the types for the corresponding bind variables.
     * @param array $variables The number of variables and length of string types must match the parameters in the statement.
     * 
     * @return Associated array from result set.
     */
    public function prepared_query($sql, $types = false, $variables = false)
    {
        $result = [];

        if( is_array($sql) ){
            list($sql, $types, $variables) = $sql;
        }

        if (self::$log_once) {
            self::$log_once = false;
            if (!empty(self::$log_once_tag)) {
                $this->write_to_logfile(self::$log_once_tag);
                self::$log_once_tag = '';
            }
            $this->write_to_logfile($sql);
        }

        if (self::$echo_once) {
            self::$echo_once = false;
            echo self::$echo_once_pre . htmlentities($sql, ENT_QUOTES, "UTF-8") . self::$echo_once_post;
            echo $this->highlighterLibrary();
            echo $this->debugTheQuery($sql);
        }

        $stmt = $this->prepare($sql);
        // i-nteger, d-ouble, s-tring, b.lob

        if( $types !== false and $variables !== false ){
            array_unshift($variables, $types);
            call_user_func_array([$stmt, 'bind_param'], $this->refValues($variables));
        }

        if( !$stmt->execute() ){
            printf("Error: %s.\n", $stmt->error);
        }

        // Make sure result set becomes associated array
        $meta = $stmt->result_metadata();
        while ($field = $meta->fetch_field())
        {
            $params[] = &$row[$field->name];
        }

        call_user_func_array([$stmt, 'bind_result'], $params);

        while ($stmt->fetch()) {
            foreach($row as $key => $val)
            {
                $c[$key] = $val;
            }
            $result[] = $c;
        }
        
        $stmt->close();

        return $result;
    }

    public function prepared_query1($sql, $types = false, $variables = false)
    {

        if( (strlen($types) == 1) and !is_array($variables) ){
            $_variables = [];
            $_variables[] = $variables;
            $variables = $_variables;
            unset($_variables);
        }

        $res = $this->prepared_query($sql, $types, $variables);
        return $res[0];
    }

    /**
     * Prepared MySQLI Insert Statement
     * 
     * Syntax:
     * $sql = [
     *    "INSERT INTO debug__eventlog (`type`, `created`, `json`) VALUES (?, NOW(), ?)",
     *    'ss',
     *    [$type, $json]
     * ];
     * $result = $mysqli->prepared_insert($sql);
     *
     * @param mixed $sql Prepared statement, or array of all params (sql, type, var)
     * @param string $types A string that contains one or more characters which specify the types for the corresponding bind variables.
     * @param array $variables The number of variables and length of string types must match the parameters in the statement.
     *
     * @return bool False on fail, or true on success
     */
    public function prepared_insert($sql, $types = false, $variables = false)
    {

        if( is_array($sql) ){
            list($sql, $types, $variables) = $sql;
        }

        $stmt = $this->prepare($sql);

        if( $types !== false and $variables !== false ){
            array_unshift($variables, $types);
            call_user_func_array([$stmt, 'bind_param'], $this->refValues($variables));
        }

        if( !$stmt->execute() ){
            echo mysqli_stmt_error($stmt);
            return false;
        } else {
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            return $affected_rows;
        }

    }

    /**
     * PHP 5.3+ requires values by reference
     *
     * @param array $arr
     * 
     * @return void
     */
    function refValues($arr)
    {
        $refs = [];
        foreach ($arr as $key => $value){
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }


    /**
     * Required code for loading and initializing highlight.js library, requires jQuery for document ready init.
     *
     * @return HTML markup to be included in page
     */
    public function highlighterLibrary()
    {
            $highlight_init_snippet = "
            $(document).ready(function(){
                document.querySelectorAll('pre code').forEach(function (block) {
                    hljs.highlightBlock(block);
                });
            });
            ";

            $themes = [
                'atelier-sulphurpool-dark',
                'atelier-sulphurpool-light',
                'darcula',
                'googlecode',
                'ir-black',
                'paraiso-dark',
                'paraiso-light',
                'routeros',
                'xt256'
            ];

            return js_and_css_include([
                '/inc/libs/highlight/js/v10.1.1.barebones.js',  // json, js, apache, xml, php, css, sql
                '/inc/libs/highlight/css/' . $themes[4] . '.css',
                $highlight_init_snippet
            ], 1);
    }

    /**
     * Runs the query so that we can display some properties around the query, will also perform an explain query.
     * 
     * @param string @query The SQL query
     * 
     * @return The markup for the debug data
     */
    public function debugTheQuery($query)
    {

        $html = '';

        $driver = new mysqli_driver();
        $driver->report_mode = MYSQLI_REPORT_ALL;
        try {
            $this->real_query($query);
            $html .= '<pre style="margin-top: -0.9em;"><code class="php">';

            if($this->errno){
                $html .= 'Error ' . $this->errno . ', ' . $this->error . '. ';
            }
            $result = new mysqli_result($this);
            $html .= 'Query results: ' . $result->num_rows . ' rows, ' . $result->field_count . ' columns pr row.';

            //$row = $result->fetch_row();
            //foreach ($result->lengths as $i => $val) {
            //    printf("%1d,", $val);
            //}

            $explain = $this->query1('EXPLAIN ' . $query);
            $max_col_width = [];
            foreach($explain as $k=>$v){
                if( strlen($k) > strlen($v) )
                    $max_col_width[$k] = strlen($k);
                    else
                    $max_col_width[$k] = strlen($v);
            }
            $html .= "\n\n<span style=\"color:yellow\">";
            foreach ($explain as $k => $v) {
                $html .= str_pad($k, $max_col_width[$k]) . ' | ';
            }
            $html .= "</span>\n";
            foreach ($explain as $k => $v) {
                $html .= str_pad($v, $max_col_width[$k]) . ' | ';
            }

            $html .= self::$echo_once_post;

        } catch (mysqli_sql_exception $e) {

            $html .= '<pre style="margin-top: -0.9em;"><code class="php">';
            $html .= htmlentities('$pre = $no; echo "lk";') . "\n";
            $html .= 'Error: ' . $this->errno . '<br>' . $e->__toString();
            $html .= '</code></pre>';
            return $html;

        #} finally {
        #    echo 'done';
        }

        return $html;

    }


    /**
    * True False Boolean converter
    * 
    * There are several ways to express a true false switch, it could be 0 and 1 just as on and off. Even
    * true and false in itself does not have anything to do with a boolean used in else if statments.
    * Wrap it around your variable and you get what you intended as logic.
    *
    * Example:
    * $test = 'true';
    * if(_bool($test)){ echo 'true'; } else { echo 'false'; }
    * 
    * @param {mixed} $var A true false statment not being a boolean
    *
    * @author Kim Steinhaug, <kim@steinhaug.com>
    * 
    * @return {bool} Boolean
    */
    public function _bool($var)
    {
        if(is_bool($var)){
            return $var;
        } else if($var === NULL || $var === 'NULL' || $var === 'null'){
            return false;
        } else if(is_string($var)){
            $var = strtolower(trim($var));
            if($var=='false'){ return false;
            } else if($var=='true'){ return true;
            } else if($var=='no'){ return false;
            } else if($var=='yes'){ return true;
            } else if($var=='off'){ return false;
            } else if($var=='on'){ return true;
            } else if($var==''){ return false;
            } else if(ctype_digit($var)){
            if( (int) $var)
                return true;
                else
                return false;
            } else { return true; }
        } else if( ctype_digit( (string) $var)){
            if( (int) $var)
            return true;
            else
            return false;
        } else if(is_array($var)){
            if(count($var))
            return true;
            else
            return false;
        } else if(is_object($var)){
            return true; // No reason to (bool) an object, we assume OK for crazy logic
        } else {
            return true; // Whatever came though must be something,  OK for crazy logic
        }
    }

}

$mysqli = Mysqli2::getInstance($mysql_host, $mysql_port, $mysql_user, $mysql_password, $mysql_database);
if ($mysqli->connect_errno) {
    echo 'Failed to connect to MySQL: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
}
if (!$mysqli->set_charset("utf8")) {
    printf("Error loading character set utf8: %s\n", $mysqli->error);
    exit();
}

// Ideeally DB Connect should be before the functions call.
//get_keys(true);
