# Mysqli2 v2.2 – AI reference

`class Mysqli2 extends mysqli` + `DatabaseException`, global namespace, PHP 8. All native mysqli methods/procedural calls work. sqlbuddy (^1.4.3) is a separate package, used only by insert()/update().

## Init
```php
Mysqli2::isDev(true);                       // dev: exception message gets " | SQL: <query>"
Mysqli2::setErrorHandler(fn($error, $query, $errno) => error_log("$errno $error | $query"));
$mysqli = Mysqli2::getInstance($host, $port, $user, $pass, $db); // singleton, later: getInstance()
$mysqli->set_charset('utf8mb4');
Mysqli2::setPrefix($sw_dp);                 // used by t/insert/update/count/exists
```
- Connect failure → DatabaseException. Missing user/db → always throws. No connect_errno check.
- Constructor sets mysqli_report(OFF) process-wide: procedural mysqli_* return false, never throw.
- setOptions([host,user,pass,dbname,port,sock]) before first getInstance(). getInstance uses `new static`.

## Errors
- Default: throw DatabaseException: getMessage(), getSqlError() (raw), getSqlQuery(), getSqlErrno().
- setUseExceptions(false): failing methods return false, getLastError() = [error, query, errno]. Test `=== false` (0 and [] are valid results).
- Error handler runs for every error (exceptions on/off/caught). Recursion-safe; if it throws → error_log, original error wins.

## Prepared
`execute($sql, $types = '', $params = [])`, $sql may be `[$sql, $types, $params]`
- result set → array of assoc rows ([] if none) | INSERT with AUTO_INCREMENT → insert_id | else → affected rows (UPDATE counts changed rows only)
- types `i d s b`. Count of `?`, strlen($types), count($params) must match, else DatabaseException "Parameter count mismatch". Exact DECIMAL → bind as `s`.

`execute1($sql, $types, $params, $return = 'default')`, scalar $params allowed
- 'default' → first row, none = error | true → first row, none = null | 0 → first column, none = error

Transactions: native begin_transaction()/commit()/rollback().

## Plain SQL (no user input)
- `query($sql)` → mysqli_result, or true when no result set.
- `query1($sql, $return = null)` → null: assoc row | 0: col 0 | n>0: col n | 'insert_id'. No rows → null.
- `rows($sql)` = result('assoc')->query($sql) → [] if none. `row($sql)` = query1($sql).
- `result($mode, $type = null)->query($sql)`, applies to that one call:

| mode | type → result (c0 = first column) |
|---|---|
| array | null → 1 col `[v,..]`, more `[[..],..]` · `'int'` → `[(int)c0]` · `'[int]'` → `[[(int)c0]]` · `'int=>string'` → `[(int)c0 => c1]` · `'string=>string'` → `[c0 => c1]` · `'int=>assoc'` → `[(int)c0 => numeric row]` |
| assoc | null → `[assoc,..]` · `'int=>assoc'` → `[c0 => assoc]` · `'int=>key::col'` → `[row[col] => assoc]` |
| text | null → `"c0, c1\n.."` · n → `"cn\n.."` |

## Helpers (table name WITHOUT prefix)
- `t('x')` → `` `{prefix}x` ``
- `count($table, $where = '', $types = '', $params = [])` → int
- `exists($table, $where, $types = '', $params = [])` → bool
- `insert($table, sqlbuddy $sql)` → insert_id (affected rows if no AUTO_INCREMENT)
- `update($table, sqlbuddy $sql, $where, array $params = [])` → changed rows. WHERE `?` types guessed: int→i, float→d, else s.

```php
$sql = new sqlbuddy;                 // queue with que()/post(), never call build()/prepared_build()
$sql->que('name', $name, 'string:100');
$sql->que('created', 'NOW()', 'datetime');
$id = $mysqli->insert('customers', $sql);
$mysqli->update('customers', $sql, 'id = ?', [(int)$id]);
```
- update() 0 = nothing changed, not an error (errors throw). Reason: read `$mysqli->info` right after: "Rows matched: 0" = no row, ≥1 = values already equal.
- Empty queue: update → SQL error, insert → silent row of defaults. Check `$sql->status()`.
- WHERE param null never matches (use IS NULL). bool → '1'/''.
- que($c, null) binds ''. NULL: nullable type (strornull, intornull..) or `que($c, 'null', 'raw')`. decimal binds as exact `s`.
- Unquoted `?` in a raw value = placeholder → mismatch error.
- sqlbuddy errors (unknown type, missing WHERE) → handler + DatabaseException/false.

## Schema (FULL table name, current DATABASE())
table_exist($t), col_exists($t, $c), index_exist($t, $idx), doesCollationExist($c) → bool · drop_col_if_exists($t, $c) → true dropped / false absent · get_row_count($t) → int · return_charset_and_collate($c = []) → ['utf8' => .., 'utf8mb4' => ..] (swedish > danish > general)

Other: getLastError(), getVersion(), isDev()/isProd().

## Legacy (v1.6/EWS), rewrite when seen
| old | new |
|---|---|
| `query($sql->build('insert', $t))` + insert_id | `insert($t, $sql)` |
| `query($sql->build('update', $t, 'id=' . $id))` | `update($t, $sql, 'id = ?', [$id])` (returns int, not bool) |
| `prepared_insert($sql->prepared_build(..))` | insert()/update() |
| `prepared_query/prepared_insert(a,b,c)` | `execute(a,b,c)` |
| `prepared_query1(a,b,c,true)` | `execute1(a,b,c,true)` |
| `prepared_query1(a,b,c,0,null)` | `execute1(a,b,c,true)['col'] ?? null` |
| `prepared_query1(a,b,c)` | `execute(a,b,c) ?: null` |
| `` "`" . $sw_dp . "t`" `` | `t('t')` |
| `mysqli_num_rows(query("SELECT * .."))` | `count()` |
| num_rows + fetch loops | `rows()` / `row()` |
| `error_message()` | `getLastError()['error']` |

Removed (v2.2.0): prepared_query(), prepared_insert(), prepared_query1() (see table). Removed earlier: log(), echo(), process_error(), return_full_columns(), return_columns(), parse_col_type().
