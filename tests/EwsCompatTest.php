<?php
// EWS (Mysqli2 v1.6) compatibility checks. Run: php tests/EwsCompatTest.php
chdir(__DIR__ . '/..');
require 'src/DatabaseException.php'; require 'src/Mysqli2.php'; require 'credentials.php';
$db = Mysqli2::getInstance($mysql_host, $mysql_port, $mysql_user, $mysql_password, $mysql_database);
$t = 'zzz_ews_check';
$db->query("DROP TABLE IF EXISTS $t");
$db->query("CREATE TABLE $t (id INT AUTO_INCREMENT PRIMARY KEY, uid INT, name VARCHAR(20), extra INT, KEY idx_uid (uid))");
$ok = fn($n, $c) => print(($c ? '[PASS] ' : '[FAIL] ') . "$n\n");
$ok('table_exist yes', $db->table_exist($t) === true);
$ok('table_exist no', $db->table_exist('nope_xyz') === false);
$ok('index_exist yes', $db->index_exist($t, 'idx_uid') === true);
$ok('index_exist no', $db->index_exist($t, 'idx_nope') === false);
$ok('col_exists yes', $db->col_exists($t, 'extra') === true);
$ok('drop_col_if_exists', $db->drop_col_if_exists($t, 'extra') === true && !$db->col_exists($t, 'extra'));
$ok('drop_col_if_exists missing', $db->drop_col_if_exists($t, 'extra') === false);
$id = $db->prepared_insert("INSERT INTO $t (uid, name) VALUES (?, ?)", 'is', [7, 'a']);
$db->prepared_query("INSERT INTO $t (uid, name) VALUES (?, ?)", 'is', [7, 'b']);
$db->prepared_insert([ "INSERT INTO $t (uid, name) VALUES (?, ?)", 'is', [9, 'c'] ]);
$ok('prepared_insert id', $id === 1);
$ok('prepared_query select', count($db->prepared_query("SELECT * FROM $t WHERE uid = ?", 'i', [7])) === 2);
$ok('prepared_query1 0', $db->prepared_query1("SELECT name FROM $t WHERE id = ?", 'i', 3, 0) === 'c');
$ok('prepared_query1 true', $db->prepared_query1("SELECT * FROM $t WHERE id = ?", 'i', [1], true)['name'] === 'a');
$ok('prepared_query1 onEmpty', $db->prepared_query1("SELECT * FROM $t WHERE id = ?", 'i', [99], true, 'EMPTY') === 'EMPTY');
Mysqli2::setUseExceptions(false);
$ok('prepared_query1 fail -> onEmpty', $db->prepared_query1("SELECT * FROM nope_xyz", '', [], 0, -1) === -1);
Mysqli2::setUseExceptions(true);
$ok('array [int]', $db->result('array', '[int]')->query("SELECT DISTINCT(uid) FROM $t ORDER BY uid") === [[7], [9]]);
$ok('array int', $db->result('array', 'int')->query("SELECT DISTINCT(uid) FROM $t ORDER BY uid") === [7, 9]);
$ok('array int=>string', $db->result('array', 'int=>string')->query("SELECT id, name FROM $t") === [1 => 'a', 2 => 'b', 3 => 'c']);
$ok('array null single col', $db->result('array')->query("SELECT name FROM $t ORDER BY id") === ['a', 'b', 'c']);
$ok('assoc int=>key::name', array_keys($db->result('assoc', 'int=>key::name')->query("SELECT * FROM $t")) === ['a', 'b', 'c']);
$ok('text col 1', $db->result('text', 1)->query("SELECT id, name FROM $t ORDER BY id") === "a\nb\nc\n");
$ok('filter resets', $db->query("SELECT 1") instanceof mysqli_result);
try { $db->result('array')->query("SELECT * FROM nope_xyz"); } catch (DatabaseException $e) {}
$ok('filter cleared after failed query', $db->query("SELECT 1") instanceof mysqli_result);
$ok('filter skipped on INSERT', $db->result('array')->query("INSERT INTO $t (uid, name) VALUES (1, 'd')") !== []);
$ok("query1 insert_id", $db->query1("INSERT INTO $t (uid, name) VALUES (5, 'e')", "insert_id") === 5);
$ok("query1 0", $db->query1("SELECT name FROM $t WHERE id = 3", 0) === "c");
$ok("query1 0 empty", $db->query1("SELECT name FROM $t WHERE id = 99", 0) === null);
$ok("query1 col 2", $db->query1("SELECT id, uid, name FROM $t WHERE id = 2", 2) === "b");
$ok("query1 col empty", $db->query1("SELECT id, name FROM $t WHERE id = 99", 1) === null);
$ok("query1 assoc", $db->query1("SELECT * FROM $t WHERE id = 1")["name"] === "a");
$ok("query1 assoc empty", $db->query1("SELECT * FROM $t WHERE id = 99") === null);
try { $db->query1("SELECT * FROM nope_xyz"); $ok("query1 throws", false); } catch (DatabaseException $e) { $ok("query1 throws", true); }
$db->query("DROP TABLE $t");
