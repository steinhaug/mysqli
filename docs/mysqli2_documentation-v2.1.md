# Mysqli2 v2.1 Documentation

Mysqli2 extends PHP's native `mysqli`. Every native method (`set_charset`, `begin_transaction`, `real_escape_string`, ...) and procedural `mysqli_*` call still works on the instance. On top of that it adds prepared statements in one call, short query helpers, a table prefix, and one place where all database errors end up.

Requires PHP 8.0+ and `steinhaug/sqlbuddy` ^1.4 (installed automatically).

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

    composer require "steinhaug/mysqli":"^2.1"

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

- **Types**, one letter per `?`: `i` integer, `d` double/decimal, `s` string, `b` blob. If the number of types and values differ, it fails with "Parameter count mismatch".
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

## 6.2 insert($table, $sqlbuddy) and update($table, $sqlbuddy, $where, $params = [])

These build the query from a [sqlbuddy](https://packagist.org/packages/steinhaug/sqlbuddy) object and run it as a prepared statement.

```php
$sql = new sqlbuddy;
$sql->que('title',   $title, 'string');
$sql->que('hits',    0, 'int');
$sql->que('created', 'NOW()', 'datetime');
$id = $mysqli->insert('eu_news', $sql);              // returns insert_id

$sql = new sqlbuddy;
$sql->que('title', $title, 'string');
$n = $mysqli->update('eu_news', $sql, 'id = ?', [$id]); // returns affected rows
```

- **Inline values.** `NULL`, `NOW()` (in date/datetime/raw mode), `raw` and `col` values are written into the SQL. Everything else is bound as a parameter.
- **WHERE values.** The values for `?` in `$where` are bound with types guessed from PHP: int becomes `i`, float becomes `d`, everything else `s`. Cast first if needed, e.g. `[(int)$_GET['id']]`.

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
| `"\`" . $sw_dp . "table\`"` | `$mysqli->t('table')` | after `Mysqli2::setPrefix($sw_dp)` |
| `mysqli_num_rows($mysqli->query('SELECT * ...'))` | `$mysqli->count('table', ...)` | |

Things to search for when upgrading:

    prepared_query(     with 4 arguments
    prepared_query1(    with 5 arguments
    ->query(            where the result of an INSERT/UPDATE is used as an object
    ->log( / ->echo(    remove

# 10. Migrating from v1.9 / v2.0.0

- **`isDev()`** no longer changes `mysqli_report`, which is always OFF. Procedural `mysqli_*` calls return `false` in dev too.
- **`query()`** returns `true` instead of a `mysqli_result` for statements without a result set.
- **`execute()`** returns rows for any statement with a result set, not only SQL starting with `SELECT`.
- **The constructor** throws if user or database is missing. It no longer falls back to `root` / `world`.
- **`table_exist()`** uses `DATABASE()` instead of the configured `dbname`.
- **`DatabaseException::getSqlError()`** is always the raw MySQL error. In dev, `getMessage()` also contains the SQL.
