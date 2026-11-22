# Mysqli2 v2.1 Documentation

Mysqli2 extends PHP's native `mysqli`. Every native method (`set_charset`, `begin_transaction`, `real_escape_string`, ...) and procedural `mysqli_*` call still works on the instance. On top of that it adds prepared statements in one call, short query helpers, a table prefix, and one place where all database errors end up.

Requires PHP 8.0+ and `steinhaug/sqlbuddy` ^1.4.3 (installed automatically). INSERT and UPDATE are written with sqlbuddy and run by Mysqli2, see [6.2](#62-insert-and-update-with-sqlbuddy).

# Table of Contents

- [1. Installation](#1-installation)
- [2. Initialization](#2-initialization)
- [3. Error handling](#3-error-handling)
- [4. Prepared statements](#4-prepared-statements)
- [5. Plain queries](#5-plain-queries)
- [6. Helpers](#6-helpers)
- [7. Schema utilities](#7-schema-utilities)
- [8. Method reference](#8-method-reference)
- [9. Migrating from v1.6 (EWS)](#9-migrating-from-v16-ews)
- [10. Migrating from v1.9 / v2.0.0](#10-migrating-from-v19--v200)

# 1. Installation

    composer require steinhaug/mysqli

Composer picks the latest stable version and saves it as `^2.x` in composer.json.

The class is in the global namespace: `Mysqli2` and `DatabaseException`.

# 2. Initialization

```php
require 'vendor/autoload.php';

Mysqli2::isDev(true); // dev: SQL in exception messages. Leave out in production.
Mysqli2::setErrorHandler(function ($error, $query, $errno) {
    error_log("Mysqli2 [$errno] $error | $query");
});
$mysqli = Mysqli2::getInstance($mysql_host, $mysql_port, $mysql_user, $mysql_password, $mysql_database);
$mysqli->set_charset('utf8mb4');
Mysqli2::setPrefix($sw_dp); // optional: table prefix for t(), insert(), update(), count(), exists()
```

- **No `connect_errno` check needed.** A failed connection throws `DatabaseException`.
- **User and database are required.** If either is missing, the constructor always throws, even with exceptions off. There are no silent defaults.
- **Always use `utf8mb4`.** `utf8` in MySQL is 3-byte only and breaks on emoji and some characters.
- **Singleton.** `getInstance()` returns the same connection every time. Later calls can omit the arguments: `$mysqli = Mysqli2::getInstance();`
- **Socket.** The 6th argument of `getInstance()` is a socket path.
- **Options without getInstance arguments.** `Mysqli2::setOptions(['host' => ..., 'user' => ..., 'pass' => ..., 'dbname' => ..., 'port' => ..., 'sock' => ...])` before the first `getInstance()`.
- **Subclassing.** `getInstance()` uses `new static()`, so `MyDb::getInstance()` returns a `MyDb`, as long as it is the first `getInstance()` call.

## 2.1 Dev and prod

| | Dev (`isDev(true)`) | Prod (default) |
|---|---|---|
| Exception message | MySQL error + ` \| SQL: <query>` | MySQL error only |
| `getSqlQuery()` | the SQL | the SQL |
| Throws / returns false | same | same |

The mode only controls what the message shows. Code behaves the same in both. `isProd(true)` is the same as `isDev(false)`.

**Note:** the constructor sets `mysqli_report(MYSQLI_REPORT_OFF)`. That setting is process-wide, so procedural `mysqli_query()` and any other mysqli connection in the same request return `false` on error instead of throwing.

# 3. Error handling

Every failing Mysqli2 method goes through the same path:

1. The error handler is called, if one is set.
2. `getLastError()` is updated.
3. A `DatabaseException` is thrown (default), or the method returns `false` if exceptions are off.

## 3.1 Exceptions (default)

```php
try {
    $rows = $mysqli->execute('SELECT * FROM users WHERE id = ?', 'i', [$id]);
} catch (DatabaseException $e) {
    $e->getMessage();   // message (includes SQL in dev)
    $e->getSqlError();  // raw MySQL error, always without SQL
    $e->getSqlQuery();  // the query
    $e->getSqlErrno();  // MySQL error number, e.g. 1146 (table doesn't exist)
}
```

## 3.2 Return false instead

```php
Mysqli2::setUseExceptions(false);

$rows = $mysqli->execute('SELECT * FROM nope');
if ($rows === false) {
    $err = $mysqli->getLastError(); // ['error' => ..., 'query' => ..., 'errno' => ...]
}
```

Compare with `=== false`: an UPDATE that changes nothing returns `0`, and a SELECT without matches returns `[]`.

## 3.3 Error handler: log everything in one place

```php
Mysqli2::setErrorHandler(function ($error, $query, $errno) {
    // write to file, errorlog table, Sentry ...
});
Mysqli2::setErrorHandler(null); // remove
```

- **Always called.** It runs for every error: with exceptions on or off, and also when the calling code catches the exception.
- **Safe to log to the database.** If a query inside the handler fails, the handler is not called again, so it cannot loop.
- **The original error wins.** If the handler throws, its error goes to `error_log()` and the original error is still thrown or returned. Log to file as a fallback, because a database error can mean the log table can't be written either.

## 3.4 Catch-all at the top level

With exceptions on, an uncaught `DatabaseException` ends the request. Add a global handler so it is shown nicely and nothing is lost:

```php
set_exception_handler(function (Throwable $e) {
    if ($e instanceof DatabaseException) {
        // already logged by the error handler
    } else {
        error_log((string)$e);
    }
    http_response_code(500);
    echo Mysqli2::isDev() ? '<pre>' . htmlspecialchars((string)$e) . '</pre>' : 'Something went wrong.';
});
```

# 4. Prepared statements

## 4.1 execute($sql, $types = '', $params = [])

Runs a prepared statement. What it returns depends on whether the statement produces a result set. It checks the statement itself, not the first word of the SQL, so `SHOW`, `WITH`, `(SELECT ...)` and leading comments work.

| Statement | Returns |
|---|---|
| Produces rows (SELECT, SHOW, WITH, ...) | array of assoc rows, `[]` if none |
| INSERT into a table with AUTO_INCREMENT | the new id |
| Everything else (UPDATE, DELETE, INSERT without AUTO_INCREMENT, DDL) | affected rows |
| Error with exceptions off | `false` |

```php
$id   = $mysqli->execute('INSERT INTO users (name, email) VALUES (?, ?)', 'ss', [$name, $email]);
$rows = $mysqli->execute('SELECT * FROM users WHERE active = ? AND age > ?', 'ii', [1, 18]);
$n    = $mysqli->execute('UPDATE users SET name = ? WHERE id = ?', 'si', [$name, $id]);
$n    = $mysqli->execute('DELETE FROM users WHERE id = ?', 'i', [$id]);

// Array form, e.g. from sqlbuddy::prepared_build()
$id = $mysqli->execute(['INSERT INTO users (name) VALUES (?)', 's', [$name]]);
```

- **Types**, one letter per `?`: `i` integer, `d` double, `s` string, `b` blob. For exact DECIMAL values, bind them as `s` (a number string), like sqlbuddy does. If the number of `?`, types and values don't all match, it fails with "Parameter count mismatch".
- **No parameters.** If `$types` or `$params` is empty, nothing is bound. Use this only for SQL that contains no outside input.
- **UPDATE counts changed rows.** MySQL's affected rows only counts rows that actually changed, so an UPDATE that sets the same value returns `0`.

## 4.2 execute1($sql, $types = '', $params = [], $return = 'default')

Same as `execute()`, but returns one row or one value.

| `$return` | Returns | No rows |
|---|---|---|
| `'default'` | first row | error (throws / `false`) |
| `true` | first row | `null` |
| `0` | first column of the first row | error (throws / `false`) |

```php
$user  = $mysqli->execute1('SELECT * FROM users WHERE id = ?', 'i', $id);          // single value: no array needed
$user  = $mysqli->execute1('SELECT * FROM users WHERE email = ?', 's', [$email], true); // null if not found
$total = $mysqli->execute1('SELECT COUNT(*) FROM users', '', [], 0);
```

Use `true` when "not found" is a normal outcome. Use the default when a missing row is a bug.

## 4.3 Transactions

These are inherited from mysqli:

```php
$mysqli->begin_transaction();
try {
    $orderId = $mysqli->execute('INSERT INTO orders (user_id) VALUES (?)', 'i', [$userId]);
    $mysqli->execute('INSERT INTO order_lines (order_id, sku) VALUES (?, ?)', 'is', [$orderId, $sku]);
    $mysqli->commit();
} catch (DatabaseException $e) {
    $mysqli->rollback();
    throw $e;
}
```

# 5. Plain queries

These are for SQL without outside input. For anything containing user data, use [prepared statements](#4-prepared-statements).

## 5.1 query($sql)

| Statement | Returns |
|---|---|
| Produces rows | `mysqli_result` |
| No result set (INSERT, UPDATE, DDL) | `true` |
| Error with exceptions off | `false` |

```php
$res = $mysqli->query('SHOW TABLES');
while ($row = $res->fetch_row()) { ... }
```

## 5.2 result($mode, $type = null)->query($sql)

`result()` sets the shape of the next `query()` result. It applies to that one call only, also if the query fails. It is ignored for queries without a result set.

**`array`**

| `$type` | Example result |
|---|---|
| `null` | one column: `['a', 'b']`, several columns: `[[1, 'a'], [2, 'b']]` |
| `'int'` | `[7, 9]`, first column cast to int |
| `'[int]'` | `[[7], [9]]` |
| `'int=>string'` | `[1 => 'a', 2 => 'b']`, key = column 0 as int, value = column 1 |
| `'string=>string'` | `['no' => 'Norway']`, key = column 0 as string, value = column 1 |
| `'int=>assoc'` | `[1 => [1, 'a']]`, key = column 0, value = the whole row (numeric) |
| other `'int=>...'` / `'string=>...'` | key = column 0, value = column 0 |

**`assoc`**

| `$type` | Example result |
|---|---|
| `null` | `[['id' => 1, 'name' => 'a'], ...]` |
| `'int=>assoc'` | `[1 => ['id' => 1, 'name' => 'a']]`, key = first column |
| `'int=>key::name'` | `['a' => ['id' => 1, 'name' => 'a']]`, key = the named column |

**`text`**

| `$type` | Example result |
|---|---|
| `null` | `"1, a\n2, b\n"` |
| column index, e.g. `1` | `"a\nb\n"` |

```php
$userIds = $mysqli->result('array', '[int]')->query('SELECT DISTINCT(UserID) FROM timeclock_events');
$names   = $mysqli->result('array', 'int=>string')->query('SELECT id, name FROM users');
$byEmail = $mysqli->result('assoc', 'int=>key::email')->query('SELECT * FROM users');
```

## 5.3 query1($sql, $return = null)

Returns a single value or row.

| `$return` | Returns |
|---|---|
| `null` | first row as assoc array |
| `0` | first column of the first row |
| integer > 0 | that column of the first row |
| `'insert_id'` | the last insert id |

It returns `null` if there are no rows. It is meant for queries that return one result.

```php
$name = $mysqli->query1('SELECT name FROM users WHERE id = 1', 0);
$user = $mysqli->query1('SELECT * FROM users ORDER BY id DESC LIMIT 1');
```

# 6. Helpers

## 6.1 Table prefix: setPrefix() and t()

```php
Mysqli2::setPrefix('ews_nettbutikk_');

$mysqli->t('eu_news'); // `ews_nettbutikk_eu_news` (with backticks)

$rows = $mysqli->rows('SELECT * FROM ' . $mysqli->t('eu_news') . ' ORDER BY id DESC');
```

`insert()`, `update()`, `count()` and `exists()` take the table name without the prefix and add it themselves. The [schema utilities](#7-schema-utilities) and `get_row_count()` take the full table name.

## 6.2 insert() and update() with sqlbuddy

Writing data is split between two classes:

- **[sqlbuddy](https://packagist.org/packages/steinhaug/sqlbuddy)** interprets and queues the values with `que()` / `post()`. Types, NULL handling, `NOW()` and length limits all work as described in the sqlbuddy manual.
- **Mysqli2** takes over at the build point. It builds the query with sqlbuddy's `prepared_build()`, adds the table prefix and runs it as a prepared statement.

So you never call `build()` or `prepared_build()` yourself, and no global `$mysqli` is needed (only sqlbuddy's `build()` uses it).

### Rule of thumb

```php
$sql = new sqlbuddy;
$sql->que('id', $id, 'int');

// insert: ->insert(table, sqlbuddy)
$sid = $mysqli->insert('table_name', $sql);

// update: ->update(table, sqlbuddy, where, [values])
$rows = $mysqli->update('table_name', $sql, 'id = ?', [$file_id]);
```

This replaces the old way:

```php
$mysqli->query($sql->build('insert', 'table_name'));
$sid = $mysqli->insert_id;
$success = $mysqli->query($sql->build('update', 'table_name', 'id=' . $file_id));
```

### What they do

| | `insert($table, $sql)` | `update($table, $sql, $where, $params = [])` |
|---|---|---|
| Builds with | `prepared_build('insert', prefix . $table)` | `prepared_build('update', prefix . $table, $where)` |
| WHERE values | – | `?` in `$where`, bound from `$params` |
| Returns | `insert_id` (affected rows if the table has no AUTO_INCREMENT) | number of **changed** rows |
| On error | throws `DatabaseException` (or returns `false` with exceptions off), also for sqlbuddy errors | same |

```php
$sql = new sqlbuddy;
$sql->que('email',   $email, 'email');
$sql->que('name',    $name, 'string:100');
$sql->que('phone',   $phone, 'strornull:20');   // empty → NULL
$sql->que('created', 'NOW()', 'datetime');      // SQL NOW(), not bound
$id = $mysqli->insert('customers', $sql);
// INSERT INTO `ews_customers` (`email`, `name`, `phone`, `created`) VALUES (?, ?, NULL, NOW())
```

sqlbuddy picks the bind types for the values:

| sqlbuddy type | Bound as |
|---|---|
| `int`, `tinyint`, `boolean` | `i` |
| `float` | `d` |
| `decimal`, `dec` | `s`: an exact number string, so `DECIMAL(20,6)` keeps every digit |
| strings, `email`, `date`, `time`, `datetime` | `s` |

**Errors from sqlbuddy** go through the normal error handling. An unknown type (`que('c', 'x', 'bogus')`) or an `update()` without WHERE calls the error handler, updates `getLastError()`, and throws `DatabaseException` (or returns `false` with exceptions off).

### Table name and prefix

Pass the table name **without** the prefix. With `Mysqli2::setPrefix('ews_')`:

```php
$mysqli->insert('customers', $sql);          // INSERT INTO `ews_customers` ...
$mysqli->insert($sw_dp . 'customers', $sql); // ❌ INSERT INTO `ews_ews_customers` ...
```

Without `setPrefix()` the prefix is empty and the name is used as given.

### WHERE values

Put `?` in the WHERE clause and pass the values in `$params`, instead of gluing them into the string:

```php
$mysqli->update('customers', $sql, 'id = ?', [$id]);
$mysqli->update('customers', $sql, 'shop_id = ? AND email = ?', [$shopId, $email]);
```

- **Types are guessed from PHP:** int becomes `i`, float becomes `d`, everything else `s`. A string id like `'1'` from `$_GET` works, but cast it yourself to be explicit: `[(int)$_GET['id']]`.
- **`null` never matches.** `id = ?` with `[null]` becomes `id = NULL`, which is never true, so the update silently returns `0`. Write `IS NULL` in `$where` instead.
- **Booleans** are sent as `'1'` / `''`. Against a numeric column that works like 1 / 0, but cast to int to be explicit: `[(int)$active]`.
- **The structure is still raw.** Column names and operators in `$where` go into the SQL as written, so they must never come from user input.

### Return value: 0 means nothing changed

`update()` returns the number of rows MySQL actually **changed**. Running the same update twice gives `1` and then `0`. `0` is not an error, because errors throw.

If you need to know why it was `0`, read `$mysqli->info` right after the update, before the next query overwrites it:

```php
if ($mysqli->update('customers', $sql, 'id = ?', [$id]) === 0) {
    preg_match('/Rows matched: (\d+)/', $mysqli->info, $m);
    if ((int)$m[1] === 0) {
        // no row with that id
    } else {
        // the row was already up to date
    }
}
```

| `$mysqli->info` | Meaning |
|---|---|
| `Rows matched: 1  Changed: 1  Warnings: 0` | updated |
| `Rows matched: 1  Changed: 0  Warnings: 0` | row found, values were already the same |
| `Rows matched: 0  Changed: 0  Warnings: 0` | no row matched the WHERE |

With exceptions off, check failure with `=== false` (then read `getLastError()`).

### Empty queue: check status()

- **Empty `update()`** gives `UPDATE ... SET  WHERE id = ?`, which fails with an SQL syntax error.
- **Empty `insert()` does not fail.** It runs `INSERT INTO t () VALUES ()` and silently creates a row with only default values.

When columns are added conditionally, check `$sql->status()` before `insert()` and `update()`:

```php
$sql = new sqlbuddy;
if ($name !== $old['name'])   $sql->que('name', $name);
if ($price !== $old['price']) $sql->que('price', $price, 'decimal');

if ($sql->status()) {
    $mysqli->update('products', $sql, 'id = ?', [(int)$id]);
}
```

### Only inline values

`raw`, `col`, `NOW()` and `NULL` are written into the SQL and not bound. That works fine, and only the WHERE values are bound:

```php
$sql = new sqlbuddy;
$sql->que('views', 'views+1', 'raw');
$sql->que('last_seen', 'NOW()', 'datetime');
$mysqli->update('pages', $sql, 'id = ?', [(int)$id]);
// UPDATE `ews_pages` SET `views`=views+1, `last_seen`=NOW() WHERE id = ?
```

A `?` inside a string literal in a `raw` value (`CONCAT('a', '?')`) is fine. An unquoted `?` in a `raw` value counts as a placeholder and fails with "Parameter count mismatch".

### PHP null without nullable

`$sql->que('name', null)` binds an empty string `''` (same as sqlbuddy's `build()`), so it works on `NOT NULL` columns. Use a nullable type (`strornull`, `intornull`, ...) or `que('name', 'null', 'raw')` when NULL is what you mean.

### Forms and loops

```php
$sql = new sqlbuddy;
$sql->post('newsletter', 'newsletter', 'checkbox');
$sql->post('company',    'company',    'string:100', true); // empty → NULL
$mysqli->update('customers', $sql, 'id = ?', [(int)$id]);

$sql = new sqlbuddy;
foreach ($rows as $r) {
    $sql->flush(); // reuse the object
    $sql->que('sku', $r['sku'], 'string:32');
    $sql->que('qty', $r['qty'], 'int');
    $mysqli->insert('stock', $sql);
}
```

### The older prepared variant

`$mysqli->prepared_insert($sql->prepared_build('insert', 'ews_customers'))` still works, but only use it in old code. It does not add the prefix, so you pass the full table name, and WHERE values have to be glued into the string instead of bound.

## 6.3 count($table, $where = '', $types = '', $params = [])

Counts rows with `COUNT(*)` without fetching them. Returns an int, or `false` on error with exceptions off.

```php
$all    = $mysqli->count('eu_news');
$active = $mysqli->count('eu_news', 'active = ? AND lang = ?', 'is', [1, 'no']);
```

Use this instead of `mysqli_num_rows($mysqli->query('SELECT * ...'))`, which fetches every row just to count them.

## 6.4 exists($table, $where, $types = '', $params = [])

Returns `true` if at least one row matches. It uses `LIMIT 1`.

```php
if ($mysqli->exists('users', 'email = ?', 's', $email)) { ... } // single value: no array needed
```

## 6.5 rows($sql) and row($sql)

| Call | Same as | Returns |
|---|---|---|
| `rows($sql)` | `result('assoc')->query($sql)` | all rows as assoc arrays, `[]` if none |
| `row($sql)` | `query1($sql)` | first row as assoc array, `null` if none |

```php
foreach ($mysqli->rows('SELECT * FROM ' . $mysqli->t('eu_news')) as $news) { ... }
$latest = $mysqli->row('SELECT * FROM ' . $mysqli->t('eu_news') . ' ORDER BY id DESC LIMIT 1');
```

These replace loops built on `mysqli_num_rows` + `mysqli_fetch_*`. They take plain SQL with no parameters, so for user input use `execute()` / `execute1(..., true)`.

# 7. Schema utilities

All of these take the full table name and work on the current database (`DATABASE()`). Names are bound as parameters or quoted, so they are safe for any string.

```php
$mysqli->table_exist('ews_users');                 // bool
$mysqli->col_exists('ews_users', 'phone');         // bool
$mysqli->index_exist('ews_users', 'idx_email');    // bool
$mysqli->drop_col_if_exists('ews_users', 'fax');   // true if dropped, false if it did not exist
$mysqli->get_row_count('ews_users');               // int
$mysqli->doesCollationExist('utf8mb4_danish_ci');  // bool
$mysqli->return_charset_and_collate();             // ['utf8' => ..., 'utf8mb4' => ...]
```

- **`index_exist()`** looks for an index by name, e.g. `PRIMARY` or `idx_email`.
- **`return_charset_and_collate($c = [])`** picks a Swedish collation if the server has one, then Danish, then `*_general_ci`. To force one, pass `['utf8mb4' => 'utf8mb4_danish_ci']`. It is only used if the server has that collation.

Example: a migration step that is safe to run twice:

```php
if (!$mysqli->col_exists('ews_users', 'phone')) {
    $mysqli->query('ALTER TABLE `ews_users` ADD `phone` VARCHAR(20) NULL');
}
```

# 8. Method reference

**Setup (static)**

| Method | Description |
|---|---|
| `getInstance($host, $port, $user, $password, $database, $sock = false)` | Connect once, return the shared instance |
| `setOptions(array $opt)` | Set connection options before the first `getInstance()` |
| `isDev($enable = null)` / `isProd($enable = null)` | Get or set the mode |
| `setUseExceptions(bool $use)` | Throw (default) or return `false` |
| `setErrorHandler(?callable $fn)` | `fn($error, $query, $errno)`, called for every error |
| `setPrefix(string $prefix)` | Table prefix for the helpers |

**Queries**

| Method | Returns |
|---|---|
| `execute($sql, $types, $params)` | rows / insert_id / affected rows |
| `execute1($sql, $types, $params, $return)` | one row or value |
| `query($sql)` | `mysqli_result` / `true` |
| `result($mode, $type)->query($sql)` | shaped array or string |
| `query1($sql, $return)` | one row or value, `null` if none |
| `rows($sql)` / `row($sql)` | all rows / first row |
| `insert($table, $sqlbuddy)` | insert_id |
| `update($table, $sqlbuddy, $where, $params)` | affected rows |
| `count($table, $where, $types, $params)` | int |
| `exists($table, $where, $types, $params)` | bool |
| `t($table)` | `` `prefix_table` `` |

**Schema:** `table_exist`, `col_exists`, `index_exist`, `drop_col_if_exists`, `get_row_count`, `doesCollationExist`, `return_charset_and_collate`

**Other:** `getLastError()`, `getVersion()`

**v1.6 aliases:** `prepared_query`, `prepared_insert`, `prepared_query1` (see below)

# 9. Migrating from v1.6 (EWS)

| v1.6 | v2.1 | Note |
|---|---|---|
| `prepared_query($sql, $types, $vars)` | same, alias for `execute()` | INSERT now returns **insert_id**, not affected rows. The 4th argument (`'group:'` / `'flatten:'`) is gone and is silently ignored. |
| `prepared_insert(...)` | same, alias for `execute()` | unchanged |
| `prepared_query1($sql, $types, $vars, $return, $onEmpty)` | same | `$onEmpty` is now **returned** when there are no rows (or on error with exceptions off). In v1.6 a non-null value made it throw. `$return`: `0` value, `true` first row, default all rows. |
| `query1($sql, $return)` | same | |
| `result(...)->query(...)` | same | the only chaining left |
| `table_exist`, `index_exist`, `col_exists`, `drop_col_if_exists`, `get_row_count` | same | `drop_col_if_exists` now reports a failed ALTER |
| `query()` on INSERT/UPDATE | returns `true` | v1.6 returned a `mysqli_result` |
| `log()`, `echo()`, `set_log_what_queries()`, `debug_queries()`, `verbose()` | removed | use `setErrorHandler()` for logging |
| `process_error()` / `sqlError__alertAndStop` | removed | use `setErrorHandler()` + a global exception handler |
| `error_message()` | removed | `getLastError()['error']` |
| `insert_multi_query`, `return_full_columns`, `return_columns`, `parse_col_type`, `considered_null`, `_bool` | removed | |
| `"INSERT INTO ... (" . $sql->output('keys') . ") VALUES (" . $sql->output('values') . ")"` | `$mysqli->insert('table', $sql)` | prepared, prefix added |
| `$mysqli->query($sql->build('insert', 'table'))` + `$mysqli->insert_id` | `$id = $mysqli->insert('table', $sql)` | see [6.2](#62-insert-and-update-with-sqlbuddy) |
| `$mysqli->query($sql->build('update', 'table', 'id=' . $id))` | `$mysqli->update('table', $sql, 'id = ?', [$id])` | returns changed rows, not `true` |
| `$mysqli->prepared_insert($sql->prepared_build(...))` | `$mysqli->insert()` / `update()` | prefix added, WHERE values bound |
| `"\`" . $sw_dp . "table\`"` | `$mysqli->t('table')` | after `Mysqli2::setPrefix($sw_dp)` |
| `mysqli_num_rows($mysqli->query('SELECT * ...'))` | `$mysqli->count('table', ...)` | |

Things to search for when upgrading:

    prepared_query(     with 4 arguments
    prepared_query1(    with 5 arguments
    ->query(            where the result of an INSERT/UPDATE is used as an object
    ->log( / ->echo(    remove
    ->build(            sqlbuddy: replace with $mysqli->insert() / update()

# 10. Migrating from v1.9 / v2.0.0

- **`isDev()`** no longer changes `mysqli_report`, which is always OFF. Procedural `mysqli_*` calls return `false` in dev too.
- **`query()`** returns `true` instead of a `mysqli_result` for statements without a result set.
- **`execute()`** returns rows for any statement with a result set, not only SQL starting with `SELECT`.
- **The constructor** throws if user or database is missing. It no longer falls back to `root` / `world`.
- **`table_exist()`** uses `DATABASE()` instead of the configured `dbname`.
- **`DatabaseException::getSqlError()`** is always the raw MySQL error. In dev, `getMessage()` also contains the SQL.
