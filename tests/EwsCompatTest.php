<?php
// EWS (Mysqli2 v1.6) compatibility checks. Run: php tests/EwsCompatTest.php
chdir(__DIR__ . '/..');
require 'vendor/autoload.php'; require 'credentials.php';
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
$ok('query() INSERT returns true (filter skipped)', $db->result('array')->query("INSERT INTO $t (uid, name) VALUES (1, 'd')") === true);
$ok("query1 insert_id", $db->query1("INSERT INTO $t (uid, name) VALUES (5, 'e')", "insert_id") === 5);
$ok("query1 0", $db->query1("SELECT name FROM $t WHERE id = 3", 0) === "c");
$ok("query1 0 empty", $db->query1("SELECT name FROM $t WHERE id = 99", 0) === null);
$ok("query1 col 2", $db->query1("SELECT id, uid, name FROM $t WHERE id = 2", 2) === "b");
$ok("query1 col empty", $db->query1("SELECT id, name FROM $t WHERE id = 99", 1) === null);
$ok("query1 assoc", $db->query1("SELECT * FROM $t WHERE id = 1")["name"] === "a");
$ok("query1 assoc empty", $db->query1("SELECT * FROM $t WHERE id = 99") === null);
try { $db->query1("SELECT * FROM nope_xyz"); $ok("query1 throws", false); } catch (DatabaseException $e) { $ok("query1 throws", true); }
$ok("get_row_count", $db->get_row_count($t) === 5);

// v2.0.1
$ok('report mode OFF even in dev', (function () { Mysqli2::isDev(true); new Mysqli2(); Mysqli2::isDev(false); return (new mysqli_driver())->report_mode === MYSQLI_REPORT_OFF; })());
$ok('query() UPDATE returns true', $db->query("UPDATE $t SET name = name") === true);
$ok('execute UPDATE after INSERT gives affected rows, not insert_id', $db->execute("UPDATE $t SET uid = ? WHERE id = ?", 'ii', [8, 1]) === 1);
$ok('execute SHOW', count($db->execute("SHOW COLUMNS FROM $t")) === 3);
$ok('execute WITH', $db->execute("WITH x AS (SELECT name FROM $t WHERE id = ?) SELECT * FROM x", 'i', [1]) === [['name' => 'a']]);
$ok('execute leading comment', count($db->execute("/* c */ SELECT id FROM $t")) === 5);
$ok('execute DELETE', $db->execute("DELETE FROM $t WHERE id = ?", 'i', [5]) === 1);
$ok('doesCollationExist', $db->doesCollationExist('utf8mb4_general_ci') && !$db->doesCollationExist("x' OR '1'='1"));
$ok('table_exist injection-safe', $db->table_exist("x' OR '1'='1") === false);

$calls = [];
Mysqli2::setErrorHandler(function ($error, $query, $errno) use (&$calls, $db) {
    $calls[] = [$query, $errno];
    $db->query('SELECT * FROM handler_nope'); // fails inside the handler: must not recurse
});
try { $db->query("SELECT * FROM nope_a"); } catch (DatabaseException $e) {}
$ok('error handler: called once, no recursion', count($calls) === 1 && $calls[0] === ['SELECT * FROM nope_a', 1146]);
$ok('lastError is the original error, not the handler\'s', $db->getLastError()['query'] === 'SELECT * FROM nope_a');
Mysqli2::setErrorHandler(function ($e, $q, $n) use (&$calls) { $calls[] = $q; });
$calls = [];
Mysqli2::setUseExceptions(false);
$ok('error handler: called with exceptions off', $db->execute("SELECT * FROM nope_b") === false && $calls === ["SELECT * FROM nope_b"]);
$ok('execute1 reports a failed query once', $db->execute1("SELECT * FROM nope_c") === false && count($calls) === 2);
Mysqli2::setUseExceptions(true);
Mysqli2::setErrorHandler(null);

try { $db->query("SELECT * FROM nope_d"); } catch (DatabaseException $e) {}
$ok('prod: SQL not in message', strpos($e->getMessage(), 'SQL:') === false &&$e->getSqlQuery() === 'SELECT * FROM nope_d');
Mysqli2::isDev(true);
try { $db->query("SELECT * FROM nope_e"); } catch (DatabaseException $e) {}
Mysqli2::isDev(false);
$ok('dev: SQL in message, getSqlError raw', strpos($e->getMessage(), 'SQL: SELECT * FROM nope_e') !== false && strpos($e->getSqlError(), 'SQL:') === false);

$saved = (new ReflectionProperty('Mysqli2', 'options'))->getValue();
Mysqli2::setOptions(['dbname' => '']);
try { new Mysqli2(); $ok('missing config throws', false); } catch (DatabaseException $e) { $ok('missing config throws', strpos($e->getMessage(), 'must be set') !== false); }
Mysqli2::setOptions($saved);

// v2.0.2 helpers
Mysqli2::setPrefix('zzz_');
$ok('t()', $db->t('ews_check') === "`$t`");
$sql = new sqlbuddy;
$sql->que('uid', 11, 'int');
$sql->que('name', "it's", 'string');
$newId = $db->insert('ews_check', $sql);
$ok('insert returns insert_id', is_int($newId) && $newId > 0 && $db->row("SELECT * FROM $t WHERE id = $newId")['name'] === "it's");
$sql = new sqlbuddy;
$sql->que('name', 'upd', 'string');
$ok('update returns affected rows', $db->update('ews_check', $sql, 'id=? AND uid=?', [$newId, 11]) === 1);
$ok('update applied', $db->row("SELECT name FROM $t WHERE id = $newId") === ['name' => 'upd']);
$total = $db->get_row_count($t);
$ok('count all', $db->count('ews_check') === $total);
$ok('count where', $db->count('ews_check', 'uid = ?', 'i', [11]) === 1);
$ok('exists yes (scalar param)', $db->exists('ews_check', 'uid = ?', 'i', 11) === true);
$ok('exists no', $db->exists('ews_check', 'uid = ?', 'i', [999]) === false);
$ok('rows', count($db->rows("SELECT * FROM $t")) === $total && isset($db->rows("SELECT * FROM $t")[0]['name']));
$ok('row empty', $db->row("SELECT * FROM $t WHERE id = 999") === null);
Mysqli2::setPrefix('');

// v2.1.1: every parameter mismatch goes through handleError(), never a raw ArgumentCountError
$mismatch = function ($fn) { try { $fn(); return 'no error'; } catch (DatabaseException $e) { return str_contains($e->getMessage(), 'Parameter count mismatch') ? 'ok' : $e->getMessage(); } catch (Throwable $e) { return get_class($e); } };
$sql = new sqlbuddy; $sql->que('name', '?', 'raw');
$ok("unquoted ? in raw value -> DatabaseException", $mismatch(fn() => $db->update($t, $sql, 'id = ?', [1])) === 'ok');
$ok('placeholders but no values -> DatabaseException', $mismatch(fn() => $db->execute("SELECT * FROM $t WHERE id = ?")) === 'ok');
$ok('values but no placeholders -> DatabaseException', $mismatch(fn() => $db->execute("SELECT * FROM $t", 'i', [1])) === 'ok');
$ok('types shorter than values -> DatabaseException', $mismatch(fn() => $db->execute("SELECT * FROM $t WHERE id = ? AND uid = ?", 'i', [1, 2])) === 'ok');
$ok('v1.6 style false params still work', count($db->prepared_query("SELECT * FROM $t", false, false)) === $total);

$db->query("DROP TABLE $t");
