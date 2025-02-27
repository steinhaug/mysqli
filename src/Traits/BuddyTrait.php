<?php
namespace Steinhaug\Mysqli\Traits;

trait BuddyTrait
{
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
                throw new \exception('parse_col_type error, missing table reference.', 1);
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


}