<?php

/**
 * Mysqli2Debug - Debug extension for Mysqli2
 * 
 * Extends Mysqli2 with debugging capabilities.
 * Use this class instead of Mysqli2 when you need debug features.
 * 
 * Usage:
 *   $mysqli = Mysqli2Debug::getInstance($host, $port, $user, $pass, $db);
 *   $mysqli->echo()->query($sql);
 */
class Mysqli2Debug extends Mysqli2
{
    /**
     * Override getInstance to return debug instance
     */
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
            self::$instance = new self();  // Creates Mysqli2Debug instance instead
        }
        return self::$instance;
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

        // Note: js_and_css_include() must be implemented in your project
        return js_and_css_include([
            '/dist/vendor/highlight/js/v10.1.1.barebones.js',
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

        $driver = new \mysqli_driver();
        $driver->report_mode = MYSQLI_REPORT_ALL;
        try {
            $this->real_query($query);
            $html .= '<pre style="margin-top: -0.9em;"><code class="php">';

            if($this->errno){
                $html .= 'Error ' . $this->errno . ', ' . $this->error . '. ';
            }
            $result = new \mysqli_result($this);
            $html .= 'Query results: ' . $result->num_rows . ' rows, ' . $result->field_count . ' columns pr row.';

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

        return "Array\n(\n    " . substr($out,6, -1) . "\n)";
    }
}