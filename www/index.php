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

if(!function_exists('sqlError__alertAndStop')){ function sqlError__alertAndStop($sql_error, $sql_query, $reference = '', $UserID = 0, $trace = null){
    return time();
} }

/*
$result = $mysqli->query('SHOW TABLES');
while($item = $result->fetch_row()) {
    echo $item[0] . '<br>';
}

*/

echo '
    <h2>Quick menu</h2>
    <a href="?run=tests">1. Run Mysqli tests</a><br>
    <a href="?run=query1">2. query1</a>
';

if( isset($_GET['run']) AND ($_GET['run']=='tests') ){
    echo '<h2>Running mysqli-tests</h2>';
    include 'test-mysqli.php';
}
if( isset($_GET['run']) AND ($_GET['run']=='query1') ){
    echo '<h2>Running mysqli-tests test-query1.php</h2>';
    include 'test-query1.php';
}

