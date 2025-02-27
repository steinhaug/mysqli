<?php

use Steinhaug\Mysqli\Traits\QueryExporterTrait;
use Steinhaug\Mysqli\Traits\BuddyTrait;
use Steinhaug\Mysqli\Traits\DebuggingTrait;
use Steinhaug\Mysqli\Traits\UtilityTrait;

/**
 * Mysqli Abstraction Layer v1.7.0
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
    use QueryExporterTrait;
    use BuddyTrait;
    use DebuggingTrait;
    use UtilityTrait;

    private $version = '1.7.0';

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
            throw new \exception(mysqli_connect_error(), mysqli_connect_errno());
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

        $error_no = \sqlError__alertAndStop((strlen($error_number)?'#' . $error_number . ', ':'') . $error_message, $sql_query, $reference);
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

        throw new \exception($error_message, (int) $error_number);

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
                                    \line_pad(
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
     * @return Returns FALSE on failure. For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries query() will return a \mysqli_result object. For other successful queries query() will return TRUE.
     */
    #[\ReturnTypeWillChange]
    public function query(string $query, $resultmode = MYSQLI_STORE_RESULT)
    {

        if( is_object($query) ){
            \logerror('SQL Query cannot be an object!', true, debug_backtrace(0));
            exit;
        }

        $this->chaining_before($query);

        if (!$this->real_query($query))
            return $this->process_error($this->error, $query, $this->errno, '', __METHOD__);

        $result = new \mysqli_result($this);

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
            \logerror('SQL Query cannot be an object!', true, debug_backtrace(0));
            exit;
        }


        $this->chaining_before($query);

        if( !$this->real_query($query) )
            return $this->process_error($this->error, $query, $this->errno, '', __METHOD__);

        $result = new \mysqli_result($this);

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
            $result = new \mysqli_result($this);
            $row = $result->fetch_row();
            return (int) $row[0];
        } else {
            $cols = $this->return_full_columns($tablename);
            $col = array_shift($cols);
            $id = $col['Field'];
            $this->real_query('SELECT COUNT(`' . $id . '`) FROM `' . $tablename . '`');
            $result = new \mysqli_result($this);
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
        $stmt = new \mysqli_stmt($this, $query);
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

        $result = new \mysqli_result($this);
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

        $result = new \mysqli_result($this);

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

        $result = new \mysqli_result($this);
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
        $res = new \mysqli_result($this);

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
            throw new \exception('Mysqli->return_full_columns(table) error, table (' . $table . ') does not exist.', 1);
        }

        $table_data = [];
        $this->real_query('SHOW FULL COLUMNS FROM `' . $table . '`');
        $res = new \mysqli_result($this);
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
     * Get the column data for a table with flexible return options
     *
     * @param string $table Table name
     * @param string|bool $return Return format. Options:
     *   - 'name' (default): Returns column names as array
     *   - 'full': Returns full column information array
     *   - 'type': Returns column types as array
     *   - 'nullable': Returns nullable status as array
     *   - true: Alias for 'name' with first column shifted
     * @param bool $shift_first Remove first column (typically primary key)
     * 
     * @return array Columns data based on specified return format
     * 
     * @example
     *   // Get all column names
     *   $columns = $this->return_columns('users');
     *   // Result: ['id', 'username', 'email', ...]
     * 
     * @example
     *   // Get column names without primary key
     *   $columns = $this->return_columns('users', true);
     *   // Result: ['username', 'email', ...]
     * 
     * @example
     *   // Get full column information
     *   $columnInfo = $this->return_columns('users', 'full');
     *   // Result: [['Field' => 'id', 'Type' => 'int', ...], ...]
     */
    public function return_columns($table, $return = 'name', $shift_first = false) {
        $cols = $this->return_full_columns($table);

        if( $return === true ) {
            $return = 'name';
            $shift_first = true;
        }

        // Handle different return formats
        switch ($return) {
            case 'name':
                $data = array_column($cols, 'Field');
                break;
            case 'full':
                $data = $cols;
                break;
            case 'type':
                $data = array_column($cols, 'Type');
                break;
            case 'nullable':
                $data = array_column($cols, 'Null');
                break;
            default:
                throw new \InvalidArgumentException("Invalid return format: {$return}");
        }

        // Shift first column if specified
        if (
            $shift_first 
            || ($return === true && $shift_first === false)
        ) {
            array_shift($data);
        }

        return $data;
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
     * Return the error_message from temp variable
     */
    public function error_message(){
        return $this->error_message;
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
                $bindParamsMethod = new \ReflectionMethod('mysqli_stmt', 'bind_param');
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
                        $bindResultMethod = new \ReflectionMethod('mysqli_stmt', 'bind_result');
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
                        $queryResult[] = \mysqli_stmt_affected_rows($stmt);
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
                throw new \exception('prepared_query1 should return results, came up empty!', 1);
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

            $this->error_message = \mysqli_stmt_error($stmt);
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
        if ( false === $stmt ) {
            die('prepare() failed: ' . htmlspecialchars($mysqli->error));
        }

        $rc = $stmt->bind_param($types, ...$vars);
        if ( false === $rc ) {
            die('bind_param() failed: ' . htmlspecialchars($stmt->error));
        }

        $rc = $stmt->execute();
        if ( false === $rc ) {
            die('execute() failed: ' . htmlspecialchars($stmt->error));
        }

        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $affected_rows;

    }

}
