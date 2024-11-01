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

/*
$result = $mysqli->query('SHOW TABLES');
while($item = $result->fetch_row()) {
    echo $item[0] . '<br>';
}

*/

echo '
    <h2>Quick menu</h2>
    <a href="?run=tests">1. Run Mysqli tests</a><br>
    <a href="?run=manual">2. List manual</a>
';

if( isset($_GET['run']) AND ($_GET['run']=='tests') ){
    echo '<h2>Running mysqli-tests</h2>';
    include 'test-mysqli.php';
}

