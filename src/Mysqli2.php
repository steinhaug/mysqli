<?php

/**
 * Mysqli Abstraction Layer v1.6.4
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
 * ->return_columns($table_name, what_to_return, (bool) array_shift returned array)
 *   Fetches all the metadata, but returns only specific meta
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

    private $version = '1.6.3';

    static $die_on_error = true;

    protected static $instance;
    protected static $options = [];

    protected static $verbose_level = 0;
    protected static $verbose_queries = false;
    protected static $verbose_type = 'html';

    protected static $log_skip_writing = false;
    protected static $log_once = null;
    protected static $log_once_tag = '';

    protected static $log_what_queries = 'none';

    protected static $echo_once = false;
    protected static $echo_once_b = false;
    protected static $echo_once_pre  = '<pre class=""><code class="sql">';
    protected static $echo_once_post = '</code></pre>';
    // null = not loaded, true = loading, false = already loaded ignore
    protected static $echo_load_dependencies = null;

    protected static $query_exporter_settings = [];
    protected static $array_full_columns = null;

    protected $result_filter = null;

    static $logfile_folder_path = null;

    public $error_message = '';

    public function getVersion()
    {
        return $this->version;
    }

    public static function set_log_what_queries($v)
    {
        self::$log_what_queries = $v;

    }

    public static function set_logfile_path($path)
    {
        if( strpos($path, "\\") !== false ){
            die('mysqli_connect.php config error: set_logfile_path( $PATH ) <- Do not use slashes in path, only use forward slashes.');
        }

        self::$logfile_folder_path = $path;

        //self::check_logfile_path();
    }

    public function check_logfile_path()
    {

        $logfile = self::$logfile_folder_path . '/' . 'sqllog' . '.log';
        echo '$mysqli->check_logfile_path ...' . "<br>\n-- logfile path: " . $logfile . "<br>\n";

        if( substr(self::$logfile_folder_path,-1) == '/' )
            echo '** Syntax error in path, should not have ending slash! <br>' . "\n" . self::$logfile_folder_path . "<br>\n";

        if( file_exists($logfile) ){
            $byte = filesize($logfile);
            echo '-- File exists (' . $byte . ' bytes)!' . "<br>\n";
        } else {
            echo '-- File does not exist!' . "<br>\n";
        }

        echo "-- Test write, adding " . strlen('Test write...' . "\n") . " bytes to logfile ...<br>\n";

        if ($fh = @fopen($logfile, 'a+')) {
            fputs($fh, 'Test write...' . "\n", strlen('Test write...' . "\n"));
            fclose($fh);
        }

        if (file_exists($logfile)) {
            clearstatcache();
            $byte = filesize($logfile);
            echo '-- SQL Logfile exists (' . $byte . ' bytes)!' . "<br>\n";
        } else {
            echo '-- SQL Logfile does not exist!' . "<br>\n";
        }

        echo "<br>\nQUERY Checks<br>\n";
        echo "-- Quering database: SHOW VARIABLES;<br>\n";
        $vars = $this->result('array')->query("SHOW VARIABLES");
        echo '-- returned ' . count($vars) . ' variables.' . "<br>\n";

        echo "<br>\nGlobal logfile() test.<br>\n";
        $logdir = '/logs';
        if (!empty($GLOBALS['logdir_serverPath'])) {
            $logdir = $GLOBALS['logdir_serverPath'];
        }
        echo '-- logdir: ' .  $logdir . "<br>\n";
        echo '-- Exist and is directory: ' . (is_dir($logdir)?'yes':'no') . "<br>\n";

        echo "<br>\nremove call to checkstatus to continue...";
        exit;
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
     * Set die_on_error variable, for development
     */
    public function setDieOnError($val){
        self::$die_on_error = $val;
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

        if( substr($title,0,1) == '!' ){
            self::$log_skip_writing = true;
        } else {
            self::$log_skip_writing = false;
        }

        self::$log_once = true;
        self::$log_once_tag = $file_path . ':' . $_db[0]['line'] . ' ' . $title;

        return $this;
    }

    /**
     * Chain-filter for query() funksjon, preprocessing av spørringen
     * 
     * assoc                    returns as assoc resultset
     *      , int=>assoc        Main array is indexed by first column in results
     *      , int=>key::NAME    Main array is keyed by value of NAME in results
     * text, o int              returns a comma seperated list, om int er det bare denne kolonanna som er med
     * array, o valFilter       returns a resultset array
     *        'int'                 return integer
     *        '[int]'               return [ integer ], int inside array
     *        'int=>string'         resultatsettet er en associative array hvor key og value er fra kolonne 1 og 2 fra query
     *        'string=>string'      ?
     *        '^int=>'              ?
     *        '^string=>'           ?
     * 
     * 
     * Mulige $param1 : $mysqli->result('array',[int])->query(...
     * 
     * @param string $result_filter Name of filter to use
     * @param mixed $typeof         Optional extra settings
     *
     * @return $this
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
        self::$echo_once   = true;
        self::$echo_once_b = true;
        return $this;
    }

    /**
     * Logic when an error occures, all errors should execute this function
     *
     * @param string $error_message  The SQL Error reported my server
     * @param string $sql_query      The SQL Query
     * @param string $error_number   The SQL Error ID reported by server
     * @param string $reference      A tag or other reference to where the SQL is originating from
     * @param string $caller         Name of fuction where the error occured
     *
     * @return false
     */
    public function process_error($error_message, $sql_query, $error_number = '', $reference = '', $caller='')
    {

        if( is_array($sql_query) ){
            $sql_query = json_encode($sql_query);
        }

        $this->write_to_logfile('ERROR SQL: ' . $sql_query, null, true);
        $this->write_to_logfile($error_message, null, true);

        $error_no = sqlError__alertAndStop((strlen($error_number)?'#' . $error_number . ', ':'') . $error_message, $sql_query, $reference);
        if (self::$die_on_error) {

            if(empty($GLOBALS['dashboardLoaded'])){
                echo 'DB ERROR #' . $error_no . "\n";
                echo '-- ' . $error_message;
                if($GLOBALS['localmode']){
                    echo "-- <br>\n";
                    echo htmlentities($sql_query);
                }
            } else {
                // Assuming theese are opened.
                echo '</div></div></div></div>';

                echo '
                    <script>
                    $(document).ready(function(){
                        let dynmodal = new DynModal.Core();
                        dynmodal.setHeaderTitle("SQL Error #' . $error_no . '")
                            .setShowCloseButton(false)
                            .setBody(function() {
                                return \'<h1 class="mt-n25">EN DATABASEFEIL HAR OPPSTÅTT</h1><p>Det har oppstått en feil i kommunikasjonen mot databasen, feilen er logget og det er sendt varsel til webmaster.</p><p>Databasefeil #' . $error_no . ' vil bli rettet med første annledning!</p><p>Om du er i kontakt med teknisk support kan du referere til denne feilen som databasefeil #' . $error_no . '</p><p><a href="/">Tilbake til forsiden</a><br>&nbsp; eller <br><a href="/?s=' . $_GET['s'] . '">Tilbake til &quot;' . $_GET['s'] . '&quot; modulen</a></p>\';
                        }).setFooter([]).buildAndShow(\'static\');

                    });
                    </script>
                ';

                echo '</body></html>';
            }

            exit;
        }

        throw new exception($error_message, $error_number);

        return false;
    }

    public function chaining_before($query, $multi_types = null, $muli_vars = null)
    {
        if( self::$log_what_queries == 'all' and self::$log_once === null ){
            self::$log_once = true;
        }

        if (self::$log_once) {
            self::$log_once = null;
            if (!empty(self::$log_once_tag)) {
                $this->write_to_logfile(self::$log_once_tag);
                self::$log_once_tag = '';
            }

            if ($multi_types === null and $muli_vars === null) {
                $this->write_to_logfile($query);
            } else {
                $this->write_to_logfile($query . "\n" . 
                                    line_pad(
                                        $this->prettyprint_types(print_r($multi_types, true)) . "\n" .
                                        print_r($muli_vars, true)
                                            , 4)
                                       );
            }
        }





        if (self::$echo_once) {
            self::$echo_once = false;
            echo $this->debugPrintQuery($query);
            echo $this->debugExplainQuery($query);
            if (self::$echo_load_dependencies === null)
                self::$echo_load_dependencies = true;
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

    }
    public function chaining_after($query)
    {

        if (self::$echo_once_b) {
            self::$echo_once_b = false;
            echo $this->debugRunQuery($query);
            if(self::$echo_load_dependencies === null)
                self::$echo_load_dependencies = true;
        }

        // Finalize ->echo() logic and output dependencies and javascript 
        if (self::$echo_load_dependencies === true) {
            echo $this->debugLoadDeps();
        }

        self::$log_skip_writing = false;
        self::$log_once = null;
        self::$echo_once = false;
        self::$echo_once_b = false;
        self::$echo_load_dependencies = false;

    }

    /**
     * Performs a query on the database
     *
     * @param string $query The query string.
     * @param mixed $resultmode Void
     * 
     * @return Returns FALSE on failure. For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries query() will return a mysqli_result object. For other successful queries query() will return TRUE.
     */
    #[\ReturnTypeWillChange]
    public function query(string $query, $resultmode = MYSQLI_STORE_RESULT)
    {

        if( is_object($query) ){
            logerror('SQL Query cannot be an object!', true, debug_backtrace(0));
            exit;
        }

        $this->chaining_before($query);

        if (!$this->real_query($query))
            return $this->process_error($this->error, $query, $this->errno, '', __METHOD__);

        $result = new mysqli_result($this);

        $this->chaining_after($query);

        $result_filter = $this->result_filter;
        $this->result_filter = null;


        if( strtoupper(substr($query, 0, 11)) == 'INSERT INTO' and $result_filter !== null){
            //echo htmlentities($query);
            return $result;
        }

        if( $result_filter !== null ){
            if (is_array($result_filter) and ($result_filter[0] === 'assoc')) {
                    $new_result = [];
                    if ($result->num_rows) {
                        while ($row = $result->fetch_assoc()) {
                            $value = $row;
                            if( is_null($result_filter[1]) ){
                                $new_result[] = $row;
                            } else if ($result_filter[1] === 'int=>assoc') {
                                $_key_id = $row[array_key_first($row)];
                                $new_result[$_key_id] = $row;
                            } else if ( substr($result_filter[1], 0, 10) === 'int=>key::' ){
                                $_results_key = substr($result_filter[1], 10);
                                $_key_id = $row[$_results_key];
                                $new_result[$_key_id] = $row;
                            } else {
                                $new_result[] = $row;
                            }
                        }
                    }
            } else if(is_array($result_filter) and ($result_filter[0] === 'text')){
                $new_result = '';
                if( $result->num_rows ){
                    while ($row = $result->fetch_row()) {

                        if($result_filter[1] !== null){
                            $new_result .= $row[ (int) $result_filter[1] ] . "\n";
                        } else {
                            $new_result .= implode(', ', $row) . "\n";
                        }
                    }
                }

            } else if(is_array($result_filter) and ($result_filter[0] === 'array')){
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
                            if ($result_filter[1] === 'int=>assoc') {
                                $value = $row;
                            }else if ($result_filter[1] === 'int'){
                                $value = (int) $value;
                            } else if( $result_filter[1] === '[int]' ){
                                $value = [ (int) $value ];
                            } else if( ($result_filter[1] === 'int=>string') or ($result_filter[1] === 'string=>string') ){
                                $value = (string) $row[1];
                            }

                            if ( substr($result_filter[1], 0, 5) === 'int=>' ){
                                $new_result[ (int) $row[0] ] = $value;
                            } else if( substr($result_filter[1], 0, 8) === 'string=>' ){
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
     * Return types: 'insert_id' for insert to return ID, 0 for first array index of assoc result. Else the total result as assoc.
     *
     * @return string The value corresponding to [0] from the rowset
     */
    public function query1($query, $return = null)
    {

        if( is_object($query) ){
            logerror('SQL Query cannot be an object!', true, debug_backtrace(0));
            exit;
        }


        $this->chaining_before($query);

        if( !$this->real_query($query) )
            return $this->process_error($this->error, $query, $this->errno, '', __METHOD__);

        $result = new mysqli_result($this);

        if ($return === 'insert_id') {
            return $this->insert_id;
        } else if ($return === 0) {
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

    public function generateRandomString($length = 10) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }


    public function insert_multi_query($multi_query){

        // https://stackoverflow.com/questions/14715889/strict-standards-mysqli-next-result-error-with-mysqli-multi-query
        $affected_rows = 0;
        if( $this->multi_query($multi_query) ){
            do{
                $affected_rows+=$this->affected_rows;
            } while( $this->more_results() && $this->next_result() );
        }

        if( $this->error )
            return $this->process_error($this->error, $multi_query, $this->errno, '', __METHOD__);

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

    public function prepare(string $query): mysqli_stmt|false 
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

        $this->chaining_before($query);

        if( !$this->real_query($query) )
            return $this->process_error($this->error, $query, $this->errno, '', __METHOD__);

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

        $this->chaining_before($query);

        if( !$this->real_query($query) )
            return $this->process_error($this->error, $query, $this->errno, '', __METHOD__);

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

        $this->chaining_before($query);

        if( !$this->real_query($query) )
            return $this->process_error($this->error, $query, $this->errno, '', __METHOD__);

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
     * @param [string] $collation Name of collation
     *
     * @return boolean True if collation charset exists and false if not found
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
     * Get the column data from a table
     *
     * @return array Column names and column meta data
     */
    public function return_full_columns($table)
    {

        if(!$this->table_exist($table)){
            throw new exception('Mysqli->return_full_columns(table) error, table (' . $table . ') does not exist.', 1);
        }

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
     * Get the column data for a table, quick way for only retrieving column names
     *
     * @param string $table Table name
     * @param mixed $return What to return, defaults to name and if replaced with boolean true replaces $shift_first
     * @param boolean $shift_first array_shift the result set
     * 
     * @return array The results
     */
    public function return_columns($table, $return = 'name', $shift_first = false){

        $cols = $this->return_full_columns($table);

        $data = [];
        foreach($cols as $col){
            $data[] = $col['Field'];
        }

        if(
            $shift_first
            or
            ($return === true and $shift_first === false)
        ){
            array_shift($data);
        }

        return $data;
    }


    /**
     * Simplify the type of column for further processing
     *
     * @param string $needle
     * @param array $full_table_reference
     * @param array $add_length Adds column length for str, str:20
     * @param array $col_type default buddy, or prepared for types. (str <=> s, int <=> i, dec <=> d)
     * 
     * @return void
     */
    public function parse_col_type($needle, $full_table_reference = null, $add_length = false, $col_type='buddy')
    {
        if ($full_table_reference === null) {
            $full_table_reference = self::$array_full_columns;
            if (self::$array_full_columns === null) {
                throw new exception('parse_col_type error, missing table reference.', 1);
            }
        }

        $match = $full_table_reference[$needle]['Type'];
        if(preg_match("/^int/i",(string) $match)){
            if($this->_bool($full_table_reference[$needle]['Null'])){
                return $col_type=='buddy'?'intornull':'i';
            } else {
                return $col_type=='buddy'?'int':'i';
            }
        }
        if(preg_match("/^smallint/i",(string) $match)){
            if($this->_bool($full_table_reference[$needle]['Null'])){
                return $col_type=='buddy'?'intornull':'i';
            } else {
                return $col_type=='buddy'?'int':'i';
            }
        }
        if(preg_match("/^tinyint/i",(string) $match)){
            if($this->_bool($full_table_reference[$needle]['Null'])){
                return $col_type=='buddy'?'intornull':'i';
            } else {
                return $col_type=='buddy'?'int':'i';
            }
        }
        if(preg_match("/^decimal/i",(string) $match)){
            return $col_type=='buddy'?'dec':'d';
        }
        if(preg_match("/^datetime/i",(string) $match)){
            if($this->_bool($full_table_reference[$needle]['Null'])){
                return $col_type=='buddy'?'datetimeornull':'s';
            } else {
                return $col_type=='buddy'?'datetime':'s';
            }
        }
        if (preg_match('/^timestamp/i',(string) $match)){
            if ($this->_bool($full_table_reference[$needle]['Null'])) {
                return $col_type=='buddy'?'datetimeornull':'s';
            } else {
                return $col_type=='buddy'?'datetime':'s';
            }
        }

        if(preg_match('/^date/i',(string) $match)){
            if($this->_bool($full_table_reference[$needle]['Null'])){
                return $col_type=='buddy'?'dateornull':'s';
            } else {
                return $col_type=='buddy'?'date':'s';
            }
        }
        if (preg_match('/^varchar/i',(string) $match)) {
            if ($this->_bool($full_table_reference[$needle]['Null'])) {
                return $col_type=='buddy'?'ornull':'s';
            } else {
                if( $add_length ){
                    if( $length = $this->parse_col_length( $full_table_reference[$needle] ) )
                        return $col_type=='buddy'?'str:' . $length:'s';
                        else
                        return $col_type=='buddy'?'str':'s';

                } else {
                    return $col_type=='buddy'?'str':'s';
                }

            }
        }
        return $col_type=='buddy'?'str':'s';
    }

    /**
     * Parse and detect possible length of column
     *
     * @param array $column Array returned by MySQL FULL COLUMN for the column
     *
     * @return Mixed Null if no length found, or integer if found.
     */
    public function parse_col_length($column){

        $length = null;

        $string = mb_strtolower( $column['Type'] );
        if( substr($string, 0, 8) == 'varchar(' ){
            $length = substr($string, 8, -1);
        }

        return $length;

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

        if (mb_strtolower($val) === 'null') {
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
     * @param boolean $force Overrides self::$log_skip_writing
     * 
     * @return void
     */
    public function write_to_logfile($the_string, $file = null, $force = false)
    {

        if( $file === null )
            $file = 'sqllog';

        if (!$force and self::$log_skip_writing) {
            //self::$log_skip_writing = false;
        } else {
            if (self::$logfile_folder_path !== null) {
                if (file_exists(self::$logfile_folder_path . '/' . $file . '.log')) {
                    if ($fh = @fopen(self::$logfile_folder_path . '/' . $file . '.log', 'a+')) {
                        fputs($fh, $the_string . "\n", strlen($the_string . "\n"));
                        fclose($fh);
                    }
                }

                $querystart = substr(trim(substr($the_string, 0, 15)), 0, 6);

                if( mb_strtoupper($querystart) == mb_strtoupper('UPDATE') ){
                    if ($fh = @fopen(self::$logfile_folder_path . '/' . $file . '.UPDATE.log', 'a+')) {
                        fputs($fh, $the_string . "\n", strlen($the_string . "\n"));
                        fclose($fh);
                    }
                }

                if( mb_strtoupper($querystart) == mb_strtoupper('INSERT') ){
                    if ($fh = @fopen(self::$logfile_folder_path . '/' . $file . '.INSERT.log', 'a+')) {
                        fputs($fh, $the_string . "\n", strlen($the_string . "\n"));
                        fclose($fh);
                    }
                }

                return(true);

            }
        }
    }


    /**
     * Quick string edit for log write for prepared statements
     * 
     * IN: isis
     *
     * OUT: Array(
     *     [0] => i,
     *     [1] => s,
     *     [2] => i,
     *     [3] => s
     * )
     */
    public function prettyprint_types($string)
    {

        $lines = str_split(trim($string),1);
        if(count($lines) == 1 and $lines[0] == '')
            return $string;

        $_lines = explode("\n", print_r($lines, true));
        $max_x = count($_lines);

        $out = '';
        for ($x = 0; $x < $max_x; $x++) {
            $line = trim($_lines[$x]);

            if( ($x + 1) < $max_x ){
                $next = trim($_lines[($x + 1)]);
            } else {
                $next = '';
            }

            if( (strpos($line, '=>') !== false) and ($next != ')') )
                $line .= ', ';

            $out .= $line;

        }

        //logfile('OUT:' . "Array\n(\n    " . substr($out,6, -1) . "\n)");
        return "Array\n(\n    " . substr($out,6, -1) . "\n)";
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

        $this->chaining_before($sql, $typeDef, $params);

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
     * Run a prepared SELECT or DELETE statment.
     * 
     * Returns SELECT results as associated array or DELETE affected_rows
     *
     * $sql = "SELECT * FROM table WHERE id=?";
     * $typ = "i";
     * $variables = [$id];
     * $mysqli->prepared_query($sql, $typ, $variables);
     *
     * $sql = "SELECT * FROM table WHERE id=?";
     * $typ = "i";
     * $variables = [$id];
     * $mysqli->prepared_query($sql, $typ, $variables, 'g:id'); // same as GROUP BY id
     *
     * @param string $sql The query, as a string.
     * @param string $types A string that contains one or more characters which specify the types for the corresponding bind variables.
     * @param array $variables The number of variables and length of string types must match the parameters in the statement.
     * @param string $results_processing Compability function for SQL5
     * 
     * @return Associated array from result set.
     */
    public function prepared_query($sql, $types = false, $variables = false, $results_processing = '')
    {
        $result = [];

        if( is_array($sql) ){
            list($sql, $types, $variables) = $sql;
        }

        $this->chaining_before($sql, $types, $variables);

        $stmt = $this->prepare($sql);
        if(!empty($stmt->errno))
            return $this->process_error($stmt->error, $sql, $stmt->errno, '', __METHOD__);

        // i-nteger, d-ouble, s-tring, b.lob

        if( $types !== false and $variables !== false ){
            array_unshift($variables, $types);
            call_user_func_array([$stmt, 'bind_param'], $this->refValues($variables));
        }

        if( !$stmt->execute() )
            return $this->process_error($stmt->error, $sql, $stmt->errno, '', __METHOD__);

        // If it's a DELETE we do not need more and close here
        if( mb_strtoupper(substr($sql, 0, 12)) == 'DELETE FROM ' )
        {
            $ret = $stmt->affected_rows;
            $stmt->close();
            return $ret;
        }

        // Make sure result set becomes associated array
        $meta = $stmt->result_metadata();
        while ($field = $meta->fetch_field())
        {
            $params[] = &$row[$field->name];
        }

        call_user_func_array([$stmt, 'bind_result'], $params);


        $flatten_key = false;
        $flattened_already = [];
        if( (substr($results_processing,0,2) == 'f:') or (substr($results_processing,0,2) == 'g:') ){
            $flatten_key = substr($results_processing,2);
        } else if( substr($results_processing,0,8) == 'flatten:' ){
            $flatten_key = substr($results_processing,8);
        } else if( substr($results_processing,0,6) == 'group:' ){
            $flatten_key = substr($results_processing,6);
        }

        while ($stmt->fetch()) {
            foreach($row as $key => $val)
            {
                $c[$key] = $val;
            }

            if ($flatten_key !== false and isset($c[$flatten_key]) and !in_array($c[$flatten_key], $flattened_already)) {
                $result[] = $c;
                $flattened_already[] = $c[$flatten_key];
            } else if ($flatten_key !== false and isset($c[$flatten_key]) and in_array($c[$flatten_key], $flattened_already)) {
                $flattened_already[] = $c[$flatten_key];
            } else {
                $result[] = $c;
            }

        }
        
        $stmt->close();

        return $result;
    }

    /**
     * Short Query version when expecting 1 row of results
     *
     * $return modes: 0         Returns the first value from result set directly as value
     *                true      Returns NULL if query results in empty results
     *               'default'  Returns the result row as associated array
     * 
     * @param string $sql The SQL query, perpared format
     * @param array $types String defining the variable types
     * @param array $variables Array of the variables from $types and in the SQL query
     * @param string $return Different ways of returning the results, and how to handle zero results.
     *
     * @return mixed Depending on $return
     */
    public function prepared_query1($sql, $types = false, $variables = false, $return = 'default', $onEmpty=null)
    {

        if( (strlen($types) == 1) and !is_array($variables) ){
            $_variables = [];
            $_variables[] = $variables;
            $variables = $_variables;
            unset($_variables);
        }

        $this->chaining_before($sql, $types, $variables);

        $res = $this->prepared_query($sql, $types, $variables);

        if( !count($res)){
            if($onEmpty === null)
                return null;
                else
                throw new exception('prepared_query1 should return results, came up empty!', 1);
        }

        if($return === 0){
            return array_shift($res[0]);
        } else if( $return === true ){
            if( empty($res) )
                return null;
            return $res[0];
        } else {
            return $res;
        }

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

        $this->chaining_before($sql, $types, $variables);

        $stmt = $this->prepare($sql);
        if(!empty($stmt->errno))
            return $this->process_error($stmt->error, $sql, $stmt->errno, '', __METHOD__);

        if( $types !== false and $variables !== false ){
            array_unshift($variables, $types);
            call_user_func_array([$stmt, 'bind_param'], $this->refValues($variables));
        }

        if( !$stmt->execute() ){

            $this->error_message = mysqli_stmt_error($stmt);
            return $this->process_error($this->error_message, $sql, '', '', __METHOD__);

        } else {
            $insert_id = $stmt->insert_id;
            if( !$insert_id )
                $insert_id = $stmt->affected_rows;
            $stmt->close();
            return $insert_id;
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
     * When using prepared statments and DELETE
     *
     * @param [type] $sql The query, eks. DELETE FROM table WHERE id=? AND cid=? AND firstname=? 
     * @param [type] $types The type declaration, eks. iis
     * @param [type] $vars Array with the variables declared as types, eks [$id,$cid,$name]
     *
     * @return int Returns deleted rows reported by Mysqli driver
     */
    function prepared_delete($sql, $types, $vars){

        $this->chaining_before($sql, $types, $vars);

        $stmt = $this->prepare($sql);
        // prepare() can fail because of syntax errors, missing privileges, ....
        if ( false === $stmt ) {
            // and since all the following operations need a valid/ready statement object
            // it doesn't make sense to go on
            // you might want to use a more sophisticated mechanism than die()
            // but's it's only an example
            die('prepare() failed: ' . htmlspecialchars($mysqli->error));
        }

        $rc = $stmt->bind_param($types, ...$vars);
        // bind_param() can fail because the number of parameter doesn't match the placeholders in the statement
        // or there's a type conflict(?), or ....
        if ( false === $rc ) {
            // again execute() is useless if you can't bind the parameters. Bail out somehow.
            die('bind_param() failed: ' . htmlspecialchars($stmt->error));
        }

        $rc = $stmt->execute();
        // execute() can fail for various reasons. And may it be as stupid as someone tripping over the network cable
        // 2006 "server gone away" is always an option
        if ( false === $rc ) {
            die('execute() failed: ' . htmlspecialchars($stmt->error));
        }

        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $affected_rows;

    }


    /**
     * Return the error_message from temp variable
     */
    public function error_message(){
        return $this->error_message;
    }

    /**
     * Quick templater for $sqlbuddy markup
     *
     * @param string $table The table to layout all columns for
     * @param string $type What type of markup you need
     *
     * @return string Valid PHP code to use
     */
    public function buddy($table, $type='insert', $mode='buddy'){

        $cols = $this->return_full_columns( $table );

        $nts = [];// $sql->que('id',            '', 'int');
        $n_x = 0; // key width  ^^
        $t_x = 0; // val width                       ^^^
        foreach ($cols as $ColID=>$col){
            $n = $ColID;
            $t = $this->parse_col_type($ColID, $cols, true, $mode);
            if(strlen($n) > $n_x) $n_x = strlen($n) + 3;
            if(strlen($t) > $t_x) $t_x = strlen($t) + 1;
            $nts[] = [$n, $t];
        }

        if( $mode == 'buddy' ){
                $tpl = '    $sql = new sqlbuddy;' . "\n";
                foreach($nts as $d){
                //$tpl .= '    $sql->que(' . str_pad('\'' . $d[0] . '\',', $n_x) . ' \'\', ' . str_pad('\'' . $d[1] . '\'', $t_x) . ');' . "\n";
                    $tpl .= '    $sql->que(' . str_pad('\'' . $d[0] . '\',', $n_x) . ' \'\', ' . '\'' . $d[1] . '\');' . "\n";
                }
                $tpl .= '    // update formula ' . "\n";
                $tpl .= '    $mysqli->query( $sql->build(\'update\', \'' . $table . '\', \'id=\' . $id) );' . "\n";
                $tpl .= '    // or insert formula' . "\n";
                $tpl .= '    $mysqli->query( $sql->build(\'insert\', \'' . $table . '\') );' . "\n";
                $tpl .= '    $id = $mysqli->insert_id;' . "\n";

                return $tpl;
        } else {

            if($type=='update'){
                $where_id = array_shift($nts);
            }

            foreach($nts as $d){
                $keys[] = '`' . $d[0] . '`';
                $vals[] = '?';
                $vars[] = '$' . $d[0];
                $types[] = $d[1];
            }

            if($type=='insert'){
                $out = '
                $sql = [
                    "INSERT INTO `" . $db_prefix . "table_name` (' . implode(',', $keys) . ') VALUES (' . implode(',', $vals) . ')",
                    "' . implode('', $types) . '",
                    [' . implode(', ', $vars) . ']
                ];
                $inserted_id = $mysqli->prepared_insert($sql);
                ';
            } else if($type=='update'){
                $out = '
                $sql = [
                    "UPDATE `" . $db_prefix . "table_name` SET ' . implode('=?, ', $keys) . '=? WHERE `' . $where_id[0] . '`=?",
                    "' . implode('', $types) . 'i",
                    [' . implode(', ', $vars) . ', $' . $where_id[0] . ']
                ];
                $affected_rows = $mysqli->prepared_insert($sql);
                ';
            } else {
                $out = 'Error, unknown type: ' . $type;
            }


            return $out;



        }


    }



    /**
     * Dependencies loader when using debug functions, adds required CSS and JS.
     *
     * @return HTML markup to be included in page
     */
    public function debugLoadDeps()
    {

        // Already loaded no need.
        if( self::$echo_load_dependencies === false )
            return '';

        // Make sure we dont run this twice
        self::$echo_load_dependencies = false;

            $extra_css_togglers = '<style>
                .echo-block .white-space-trigger {
                    display: none;
                }
                .echo-block {
                    display: flex;
                    align-items: center;
                    flex-direction: row;
                    align-items: stretch;
                    background-color: #000;
                }
                .echo-block.one-line {
                    white-space: normal;
                }
                .echo-block .white-space-trigger {
                    background-color: #075277;
                    position: relative;
                    display: inline-block;
                    width: 0.5em;
                }
                .echo-block.one-line .white-space-trigger {
                    background-color: #0092da;
                    width: 1em;
                }
                .echo-block .white-space-trigger span {
                    display: none;
                }
                .echo-block .white-space-trigger:hover {
                    cursor: hand;
                }

                .echo-block.one-line .white-space-trigger:hover span {
                    box-sizing: border-box;
                    position: absolute;
                    display: block;
                    width: 150px;
                    height: 100%;
                    padding: 5px 15px;
                    z-index: 100;
                    color: #fff;
                    background-color: #0092da;
                    top: 0;
                    left: 10px;
                    opacity: 0.9;
                }

                .styled-table {
                    border-collapse: collapse;
                    font-size: 0.9em;
                    font-family: sans-serif;
                    box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
                }
                .styled-table th,
                .styled-table td {
                    padding: 6px 7px;
                }
                .styled-table thead tr {
                    background-color: #009879;
                    color: #ffffff;
                    text-align: left;
                }
                .styled-table tbody tr {
                    border-bottom: 1px solid #dddddd;
                }
                .styled-table tbody tr:nth-of-type(even) {
                    background-color: #f3f3f3;
                }
                .styled-table tbody tr:last-of-type {
                    border-bottom: 2px solid #009879;
                }
                .styled-table tbody tr:hover {
                    background-color: #f0f0f0;
                }
                .styled-table.collapsed-view thead {
                    display: none;
                }
                .styled-table tbody tr {
                    display: table-row;
                }
                .styled-table.collapsed-view tbody tr {
                    display: none;
                }
                .styled-table.collapsed-view tbody tr:first-of-type {
                    display: table-row;
                }
                .styled-table tbody .toggler {
                    text-align: center;
                }
                .styled-table tbody .toggler msg1 {
                    display: block;
                }
                .styled-table tbody .toggler msg2 {
                    display: none;
                }
                .styled-table.collapsed-view tbody .toggler msg1 {
                    display: none;
                }
                .styled-table.collapsed-view tbody .toggler msg2 {
                    display: block;
                }
                .styled-table tbody tr.toggler {
                    text-align: center;
                    border-top: 2px solid #fff;
                    border-bottom: 2px solid #fff;
                    background-color: #fff;
                    color: #000;
                    font-weight: bold;
                }
                .styled-table tbody tr.toggler:hover {
                    background-color: #3488f5;
                    color: #fff;
                    cursor: hand;
                }
                .styled-table tbody tr.toggler td {
                    padding: 0;
                    font-size: 10px;
                    line-height: 15px;
                }
                .styled-table.collapsed-view tbody tr.toggler td {
                    padding: 4px 16px;
                    border-radius: 10px;
                }
                .styled-table.collapsed-view tbody tr.toggler {
                    background-color: #3488f5;
                    color: #fff;
                }
                .styled-table.collapsed-view tbody tr.toggler:hover {
                    background-color: #fff;
                    color: #3488f5;
                }
                .styled-table .ignoring td {
                    text-align: center;
                    font-weight: bold;
                    font-size: 12px;
                }
            </style>';

            $highlight_init_snippet = '
                !function(c,a){"undefined"!=typeof module?module.exports=a():"function"==typeof define&&"object"==typeof define.g?define(a):this[c]=a()}("domready",function(){var c=[],a,b="object"===typeof document&&document,f=b&&b.documentElement.doScroll,d=b&&(f?/^loaded|^c/:/^loaded|^i|^c/).test(b.readyState);!d&&b&&b.addEventListener("DOMContentLoaded",a=function(){b.removeEventListener("DOMContentLoaded",a);for(d=1;a=c.shift();)a()});return function(e){d?setTimeout(e,0):c.push(e)}});
                domready(function () {
                    document.querySelectorAll(\'pre code\').forEach(function (block) {
                        hljs.highlightBlock(block);
                    });
                })
            ';

            $toggler_func = '
                const triggers = Array.from(document.querySelectorAll(\'[data-toggle="toggler"]\'));

                window.addEventListener(\'click\', (ev) => {
                    let elm = ev.target;

                    // Special case code needed to detect clicks from thead or th, 
                    // bubbling selects the td as trigger
                    if (!elm.hasAttribute("data-target")){ // Needed for thead tr th bubbling
                        elm = elm.parentNode;
                        if (typeof elm.hasAttribute == "function" && !elm.hasAttribute("data-target")){
                            elm = elm.parentNode;
                            if (typeof elm.hasAttribute == "function" && !elm.hasAttribute("data-target")){
                                console.log("mysqli->debugLoadDeps: $toggler_func -> Aborting as we are not on a trigger.");
                                return;
                            }
                        }
                        if (typeof elm.getAttribute !== \'function\'){
                            return;
                        }
                        const selector = elm.getAttribute(\'data-target\');
                        togglerFunc(selector, \'toggle\');
                    }

                    if( elm.hasAttribute("data-target") && elm.hasAttribute("data-class") ){
                        const className = elm.getAttribute("data-class");
                        const selector = elm.getAttribute("data-target");
                        togglerFunc(selector, className);
                    }

                    //if (triggers.includes(elm)) {
                    //    const selector = elm.getAttribute(\'data-target\');
                    //    togglerFunc(selector, \'toggle\');
                    //}

                }, false);

                const togglerFunc = (selector, cmd) => {
                    const targets = Array.from(document.querySelectorAll(selector));
                    targets.forEach(target => {
                        target.classList.toggle(cmd);
                    });
                }
            ';

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
                '/dist/vendor/highlight/js/v10.1.1.barebones.js',  // json, js, apache, xml, php, css, sql
                '/dist/vendor/highlight/css/' . $themes[4] . '.css',
                $highlight_init_snippet,
                $toggler_func
            ], 1) . $extra_css_togglers;
    }


    /**
     * Output the SQL-Query inside a pre/code block with collapse / expand
     *
     * @param string $query The SQL query
     *
     * @return string HTML markup for the verbosed SQL-Query
     */
    public function debugPrintQuery($query)
    {
        $html = '';

        if( strpos($query, "\n") !== false ){

            $cssid = $this->generateRandomString();
            $html .= '<pre class="' . $cssid . ' echo-block one-line"><div class="white-space-trigger" data-toggle="toggler" data-class="one-line" data-target=".' . $cssid . '"><span>Utvid SQL slik den ble mottatt</span></div>';
            $html .= '<code class="sql">';
            $html .= htmlentities($query, ENT_QUOTES, "UTF-8");
            $html .= '</code>';
            $html .= '</pre>';

        } else {

            $html .= self::$echo_once_pre . htmlentities($query, ENT_QUOTES, "UTF-8") . self::$echo_once_post;

        }

        return $html;

    }

    /**
     * Runs the query so that we can display some properties around the query, will also perform an explain query.
     * 
     * @param string @query The SQL query
     * 
     * @return The markup for the debug data
     */
    public function debugExplainQuery($query)
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
            $html .= 'Error 4: ' . $this->errno . '<br>' . $e->__toString();
            $html .= '</code></pre>';
            return $html;

        #} finally {
        #    echo 'done';
        }

        return $html;

    }


    /**
     * Display the results of the query in a collapsed table
     *
     * @param string $query The SQL-query
     *
     * @return string HTML markup for the table
     */
    public function debugRunQuery($query){

        $html = '';

        $once_results = $this->query($query);
        $once_fields = $once_results->fetch_fields();

        $cssid = $this->generateRandomString();

        $html .= '<table class="' . $cssid . ' styled-table collapsed-view" style="margin-top: -1em;">';
        $html .= '<thead>';
        $html .= '<tr>';
        foreach($once_fields as $_field){
            $html .= '<th>';
            $html .= $_field->name;
            $html .= '</th>';
        }
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        $html .= '<tr class="toggler" data-toggle="toggler" data-class="collapsed-view" data-target=".' . $cssid . '"><td colspan="' . $once_results->field_count . '"><msg1>SKJUL DATA</msg1><msg2>KLIKK FOR Å SE DATA</msg2></td></tr>';

        $_row_count = 0;
        while ($_row = $once_results->fetch_row()) {
            $html .= '<tr>';
            foreach ($_row as $__row) {
                $html .= '<td>' . $__row . '</td>';
            }
            $html .= '</tr>';

            $_row_count++;
            if( $_row_count > 50 ){
                $html .= '<tr class="ignoring"><td colspan="' . $once_results->field_count . '"> ... ignoring rest of rows after 50 ...</td></tr>';
                break;
            }

        }

        $html .= '</tbody>';
        $html .= '</table>';

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
            $var = mb_strtolower(trim($var));
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
