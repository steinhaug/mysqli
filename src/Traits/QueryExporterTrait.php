<?php
namespace Steinhaug\Mysqli\Traits;

trait QueryExporterTrait
{
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
            throw new \exception('function qe__make_key is not initialized, you need to envoke new_query_exporter()', 1);
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
                    $str_the_value = $quote_char . \mysqli_fix_sloppydate($val, 'sql') . $quote_char;
                    break;
                case 'datetime':
                    $str_the_value = $quote_char . \mysqli_fix_sloppydate($val, 'sql') . ' ' . \mysqli_fix_sloppydate($val, 'datetime2time') . $quote_char;
                    break;
                case 'dec':
                    $str_the_value = \fix_make_number($val);
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
            $result = new \mysqli_result($this);
            $this->new_query_exporter($p[0]);
        } else {
            $this->real_query('SELECT * FROM `' . $table_name . '`');
            $result = new \mysqli_result($this);
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

}