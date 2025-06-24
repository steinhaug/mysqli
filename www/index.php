<?php
require '../vendor/autoload.php';
require '../credentials.php';

Mysqli2::isDev(true);
$mysqli = Mysqli2::getInstance($mysql_host, $mysql_port, $mysql_user, $mysql_password, $mysql_database);
$mysqli->set_charset("utf8");
if ($mysqli->connect_errno) {
    echo 'Failed to connect to MySQL: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error; 
    exit();
}

#if(!function_exists('sqlError__alertAndStop')){ function sqlError__alertAndStop($sql_error, $sql_query, $reference = '', $UserID = 0, $trace = null){
#    return time();
#} }

/*
$result = $mysqli->query('SHOW TABLES');
while($item = $result->fetch_row()) {
    echo $item[0] . '<br>';
}

*/

#    $collate = $mysqli->return_charset_and_collate([['utf8' => 'utf8_swedish_ci', 'utf8mb4' => 'utf8mb4_swedish_ci']]);
#var_dump($collate);exit;

echo '
    <h2><a href="?run=def">[&lt;]</a> Quick menu</h2>  
    <a href="?run=tests">1. Run Mysqli tests</a><br>  
    <a href="?run=testsClaude">2. Run Mysqli tests - Claude</a><br>  
    <a href="?run=testsChatGPT">2. Run Mysqli tests - ChatGPT</a><br>  

    <a href="?run=testsV2">3. Run v2 tests</a><br>  
    <a href="?run=testsV2error">3. Run v2 error tests</a><br>  

    <a href="?run=query1">2. query1</a>
';

$testResult = '';

if( isset($_GET['run']) AND ($_GET['run']=='tests') ){
    echo '<h2>Running mysqli-tests</h2>';
    include 'test-mysqli.php';
}
if( isset($_GET['run']) AND ($_GET['run']=='testsV2') ){
    echo '<h2>Running mysqli-testsV2</h2><pre>';
    include 'test-mysqli2.php';
}
if( isset($_GET['run']) AND ($_GET['run']=='testsV2error') ){
    echo '<h2>Running mysqli-testsV2</h2><pre>';
    include 'test-mysqli2error.php';
}
if( isset($_GET['run']) AND ($_GET['run']=='testsClaude') ){
    echo '<h2>Running mysqli-tests - Claude</h2>';
    include 'test-mysqli-claude.php';
}
if( isset($_GET['run']) AND ($_GET['run']=='testsChatGPT') ){
    echo '<h2>Running mysqli-tests - ChatGPT</h2>';
    include 'test-mysqli-chatgpt.php';
}
if( isset($_GET['run']) AND ($_GET['run']=='query1') ){
    echo '<h2>Running mysqli-tests test-query1.php</h2>';
    include 'test-query1.php';
}

