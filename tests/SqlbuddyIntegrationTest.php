<?php
// Mysqli2 + sqlbuddy integration: every sqlbuddy example in docs §6.2. Run: php tests/SqlbuddyIntegrationTest.php
chdir(__DIR__ . '/..');
require 'vendor/autoload.php'; require 'credentials.php';
echo 'sqlbuddy ', sqlbuddy::version, "\n";

$db = Mysqli2::getInstance($mysql_host, $mysql_port, $mysql_user, $mysql_password, $mysql_database);
$db->set_charset('utf8mb4');
Mysqli2::setPrefix('zzsb_');
$fail = 0;
$ok = function ($label, $cond, $extra = '') use (&$fail) { if (!$cond) $fail++; echo ($cond ? '[PASS] ' : '[FAIL] '), $label, $extra !== '' ? "  -> $extra" : '', "\n"; };
$try = function ($fn) { try { return $fn(); } catch (Throwable $e) { return get_class($e) . ': ' . $e->getMessage(); } };

$db->query('DROP TABLE IF EXISTS zzsb_customers, zzsb_pages, zzsb_nokey');
$db->query("CREATE TABLE zzsb_customers (id INT AUTO_INCREMENT PRIMARY KEY, shop_id INT NOT NULL DEFAULT 1,
  email VARCHAR(100) NOT NULL DEFAULT '', name VARCHAR(100) NOT NULL DEFAULT '', phone VARCHAR(20) NULL,
  company VARCHAR(100) NULL, newsletter TINYINT NOT NULL DEFAULT 0, price DECIMAL(20,6) NOT NULL DEFAULT 0,
  created DATETIME NULL, updated DATETIME NULL)");
$db->query("CREATE TABLE zzsb_pages (id INT AUTO_INCREMENT PRIMARY KEY, views INT NOT NULL DEFAULT 0, last_seen DATETIME NULL)");
$db->query("CREATE TABLE zzsb_nokey (sku VARCHAR(32) NOT NULL, qty INT NOT NULL)");

// §6.2 main example
$sql = new sqlbuddy;
$sql->que('email', 'kim@example.com', 'email');
$sql->que('name', "Kim's", 'string:100');
$sql->que('phone', '', 'strornull:20');
$sql->que('created', 'NOW()', 'datetime');
$q = $sql->prepared_build('insert', 'zzsb_customers')[0];
$ok('insert SQL as documented', $q === 'INSERT INTO `zzsb_customers` (`email`, `name`, `phone`, `created`) VALUES (?, ?, NULL, NOW())', $q);
$id = $try(fn() => $db->insert('customers', $sql));
$ok('insert() returns insert_id', is_int($id) && $id > 0, var_export($id, true));
$row = $db->row("SELECT * FROM zzsb_customers WHERE id=" . (int) $id);
$ok('values stored', $row['name'] === "Kim's" && $row['phone'] === null && $row['created'] !== null, json_encode($row));

// update with ? in where, returns changed rows, second time 0
$sql = new sqlbuddy; $sql->que('name', 'Kim S');
$ok('update() returns 1', ($r = $try(fn() => $db->update('customers', $sql, 'id = ?', [$id]))) === 1, var_export($r, true));
$ok('same update returns 0', ($r = $try(fn() => $db->update('customers', $sql, 'id = ?', [$id]))) === 0, var_export($r, true));
$sql = new sqlbuddy; $sql->que('name', 'X');
$ok('update two where params', ($r = $try(fn() => $db->update('customers', $sql, 'shop_id = ? AND email = ?', [1, 'kim@example.com']))) === 1, var_export($r, true));
$ok('update where string id from $_GET', ($r = $try(fn() => $db->update('customers', $sql, 'id = ?', [(string) $id]))) === 0, var_export($r, true));

// only inline values
$pid = $db->insert('pages', (function () { $s = new sqlbuddy; $s->que('views', 0, 'int'); return $s; })());
$sql = new sqlbuddy; $sql->que('views', 'views+1', 'raw'); $sql->que('last_seen', 'NOW()', 'datetime');
$q = $sql->prepared_build('update', 'zzsb_pages', 'id = ?')[0];
$ok('inline-only SQL as documented', $q === 'UPDATE `zzsb_pages` SET `views`=views+1, `last_seen`=NOW() WHERE id = ?', $q);
$ok('inline-only update', ($r = $try(fn() => $db->update('pages', $sql, 'id = ?', [(int) $pid]))) === 1, var_export($r, true));
$sql = new sqlbuddy; $sql->que('views', 'views+1', 'raw');
$ok('inline-only insert (no params at all)', is_int($r = $try(fn() => $db->insert('pages', $sql))), var_export($r, true));

// PHP null without nullable: sqlbuddy >= 1.4.3 binds ''
$sql = new sqlbuddy; $sql->que('name', null);
$r = $try(fn() => $db->update('customers', $sql, 'id = ?', [$id]));
$ok("que('name', null) on NOT NULL column", is_int($r), var_export($r, true));

// forms
$_POST = ['newsletter' => 'on', 'company' => ''];
$sql = new sqlbuddy; $sql->post('newsletter', 'newsletter', 'checkbox'); $sql->post('company', 'company', 'string:100', true);
$ok('post() checkbox + nullable', ($r = $try(fn() => $db->update('customers', $sql, 'id = ?', [$id]))) === 1, var_export($r, true));
$row = $db->row("SELECT newsletter, company FROM zzsb_customers WHERE id=" . (int) $id);
$ok('post() values stored', $row['newsletter'] == 1 && $row['company'] === null, json_encode($row));

// loop, table without AUTO_INCREMENT -> affected rows
$sql = new sqlbuddy; $res = [];
foreach ([['sku' => 'A1', 'qty' => '5'], ['sku' => 'B2', 'qty' => 7]] as $rr) {
    $sql->flush(); $sql->que('sku', $rr['sku'], 'string:32'); $sql->que('qty', $rr['qty'], 'int');
    $res[] = $try(fn() => $db->insert('nokey', $sql));
}
$ok('loop insert, no AUTO_INCREMENT returns affected rows 1', $res === [1, 1], json_encode($res));

// empty queue
$sql = new sqlbuddy;
$r = $try(fn() => $db->update('customers', $sql, 'id = ?', [$id]));
$ok('empty update fails (docs: check status())', is_string($r) && str_contains($r, 'DatabaseException'), $r);
$before = $db->get_row_count('zzsb_customers');
$r = $try(fn() => $db->insert('customers', new sqlbuddy));
$ok('empty insert silently adds a default row (document it)', $db->get_row_count('zzsb_customers') === $before + 1, var_export($r, true));

// decimal + dates via Mysqli2
$sql = new sqlbuddy; $sql->que('price', '12345678901234.123456', 'decimal'); $sql->que('updated', '2026-11-22 3:32:05', 'datetime');
$try(fn() => $db->update('customers', $sql, 'id = ?', [$id]));
$row = $db->row("SELECT price, updated FROM zzsb_customers WHERE id=" . (int) $id);
$ok('decimal exact + G-hour datetime', $row['price'] === '12345678901234.123456' && $row['updated'] === '2026-11-22 03:32:05', json_encode($row));

// sqlbuddy errors through Mysqli2 error handling (task 2)
Mysqli2::setUseExceptions(false);
$sql = new sqlbuddy; $sql->que('name', 'x', 'bogus');
$r = $try(fn() => $db->insert('customers', $sql));
$ok('unknown type with exceptions off returns false', $r === false, var_export($r, true));
$sql = new sqlbuddy; $sql->que('nope', 'x');
$r = $try(fn() => $db->insert('customers', $sql));
$ok('unknown column with exceptions off returns false', $r === false, var_export($r, true));
Mysqli2::setUseExceptions(true);

// legacy variant
$sql = new sqlbuddy; $sql->que('name', 'legacy');
$ok('prepared_insert(prepared_build()) legacy', is_int($r = $try(fn() => $db->prepared_insert($sql->prepared_build('insert', 'zzsb_customers')))), var_export($r, true));

// '?' inside a string literal in a raw value is not a placeholder
$sql = new sqlbuddy; $sql->que('name', "CONCAT('a', '?')", 'raw');
$ok("raw containing '?' in a string literal", is_int($r = $try(fn() => $db->update('customers', $sql, 'id = ?', [$id]))), var_export($r, true));

$db->query('DROP TABLE IF EXISTS zzsb_customers, zzsb_pages, zzsb_nokey');
Mysqli2::setPrefix('');
echo $fail ? "$fail failed\n" : "all passed\n";
exit($fail ? 1 : 0);
