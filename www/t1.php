<?php
require '../vendor/autoload.php';
require '../credentials.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mysqli = Mysqli2::getInstance($mysql_host, $mysql_port, $mysql_user, $mysql_password, $mysql_database);
mysqli_set_charset($mysqli, "utf8");
if ($mysqli->connect_errno) {
    echo 'Failed to connect to MySQL: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
}
if( $mysqli->character_set_name() != 'utf8' ){
    if (!$mysqli->set_charset("utf8")) {
        printf("Error loading character set utf8: %s\n", $mysqli->error);
        exit();
    }
}


echo '<pre>';
var_dump( $mysqli->export_table('mytable') );


// $mysqli->return_columns('mytable')                   // ['id','user_id','email','hours','desc','valid_from','valid_to']
// $mysqli->return_columns('mytable', 'name', true)     // ['user_id','email','hours','desc','valid_from','valid_to']
// $mysqli->return_columns('mytable', 'full')           // Full column information array
// $mysqli->return_columns('mytable', 'type')           // ['int', 'int', 'varchar', 'decimal', 'text', 'datetime', 'datetime']
// $mysqli->return_columns('mytable', 'nullable')       // ['NO', 'NO', 'NO', 'NO', 'NO', 'YES', 'YES']