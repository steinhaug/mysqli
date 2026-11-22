# Mysqli2

Mysqli Abstraction Layer v2.1.1

Documentation: [docs/mysqli2_documentation-v2.1.md](docs/mysqli2_documentation-v2.1.md)


<div class="show_none">

# Table of Contents

- [Mysqli2](#mysqli2)
- [Table of Contents](#table-of-contents)
- [1. Description](#1-description)
- [2. Version History](#2-version-history)
  - [2.1 Log](#21-log)
    - [v2.1.1](#v211)
    - [v2.1.0](#v210)
    - [v2.0.2](#v202)
    - [v2.0.1](#v201)
    - [v2.0.0](#v200)
    - [v1.9.1](#v191)
    - [v1.9.0](#v190)
    - [V1.7.0](#v170)
    - [v1.6.6](#v166)
    - [v1.6.5](#v165)
    - [v1.6.4](#v164)
    - [v1.6.3](#v163)
    - [v1.6.2](#v162)
- [3. Install by composer](#3-install-by-composer)
- [4. Initialization](#4-initialization)
- [5. Information](#5-information)
  - [5.1 License](#51-license)
  - [5.2 Author](#52-author)
</div>

# 1. Description

Mysqli2 is an enhanced wrapper around PHP's native MySQLi extension that provides simplified prepared statement execution, better error handling, and development/production mode switching. The class extends mysqli, inheriting all native MySQLi methods while adding streamlined functionality.

**Key Features**  

- **Singleton Pattern**: Single database connection instance
- **Development/Production Modes**: Configurable error reporting
- **Simplified Prepared Statements**: Streamlined syntax for common operations
- **Smart Return Values**: Context-aware return types based on SQL operation
- **Exception Handling**: Optional exception throwing with detailed error information

# 2. Version History

## 2.1 Log

### v2.1.1

    - Requires steinhaug/sqlbuddy ^1.4.3 (null binds as '', decimal binds as an exact string, forgiving datetime).
    - insert() / update(): sqlbuddy errors (unknown type, missing WHERE) go through handleError(): error handler, getLastError(), DatabaseException or false.
    - execute(): checks the number of ? placeholders too, so every parameter mismatch becomes a DatabaseException instead of a raw ArgumentCountError.
    - Documentation: section 6.2 rewritten for sqlbuddy (rule of thumb, bind types, return values and $mysqli->info, empty queue, WHERE null/bool).
    - New test: tests/SqlbuddyIntegrationTest.php.

### v2.1.0

    - New documentation: docs/mysqli2_documentation-v2.1.md, including migration guides from v1.6 (EWS) and v1.9/v2.0.0.

### v2.0.2

    - steinhaug/sqlbuddy ^1.4 is now a dependency.
    - New: Mysqli2::setPrefix($prefix) and t($table), t('eu_news') gives `{prefix}eu_news`.
    - New: insert($table, $sqlbuddy) returns insert_id, update($table, $sqlbuddy, 'id=?', [$id]) returns affected rows. Both run as prepared statements.
    - New: count($table, $where, $types, $params) and exists($table, $where, $types, $params), without fetching rows.
    - New: rows($sql) and row($sql), short for result('assoc')->query($sql) and query1($sql).
    - insert, update, count and exists take the table name without prefix.

### v2.0.1

    - mysqli_report() is always MYSQLI_REPORT_OFF, all errors go through handleError(). Dev/prod only decides if the SQL is shown in the exception message.
    - New: Mysqli2::setErrorHandler(callable $fn), called with ($error, $query, $errno) for every database error, with or without exceptions.
    - New: get_row_count($table).
    - getInstance() uses new static(), the class can be extended.
    - query() returns true for queries without a result set (INSERT/UPDATE/DDL), like mysqli::query().
    - execute() detects result sets with result_metadata(), SHOW, WITH, (SELECT ...) and leading comments now work.
    - Constructor throws if user or database is missing, no more silent root/world defaults.
    - table_exist, index_exist, col_exists and doesCollationExist use DATABASE() and prepared statements.
    - DatabaseException: getSqlError() always holds the raw MySQL error.

### v2.0.0

    - EWS compliance

### v1.9.1

    - Updated documentation.
    - Added new methods for better handling of prepared statements and result sets.
    - Improved error handling and logging.

### v1.9.0

    - Ny refaktorert klasse, nye metoder. Se docs/mysqli2_documentation-v1.9.md

### V1.7.0

    - Breaking file into smaller files, better readability.

### v1.6.6

    - Updated readme.

### v1.6.5

    - Bugfix, error_number has to be int

### v1.6.4

    - buddy() updated, has prepared output aswell. echo $mysqli->buddy('table','insert','prepared');
    - parse_col_type, added prepared for type

### v1.6.3

    - Added mode for ->result('assoc') without using second parameter.

### v1.6.2

    - Updated for PHP 8.1  

# 3. Install by composer

To install the old deprecated library use composer:

    composer require "steinhaug/mysqli":"~1.6."

    // documentation:
    docs/mysqli2-documentation-v1.6.md

To install the v1.9 library use composer:

    composer require "steinhaug/mysqli":"^1.9.0"

    // documentation:
    docs/mysqli2_documentation-v1.9.md

To install the library use composer:

    composer require steinhaug/mysqli

    // documentation:
    docs/mysqli2_documentation-v2.1.md

Dump autoloaders:  

    composer dump-autoload --optimize

# 4. Initialization

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

- Exceptions are on by default: a failed connection or query throws `DatabaseException` (`getSqlError()`, `getSqlQuery()`, `getSqlErrno()`), so no `connect_errno` check is needed.
- `Mysqli2::setUseExceptions(false)` makes failing methods return `false` instead, read the error with `$mysqli->getLastError()`.
- The error handler runs for every error, also when exceptions are off or caught. If it throws, the original error still wins and the handler's error goes to `error_log()`.

# 5. Information

## 5.1 License

This project is licensed under the terms of the  [MIT](http://www.opensource.org/licenses/mit-license.php) License. Enjoy!

## 5.2 Author

Kim Steinhaug, steinhaug at gmail dot com.

**Sosiale lenker:**
[LinkedIn](https://www.linkedin.com/in/steinhaug/), [SoundCloud](https://soundcloud.com/steinhaug), [Instagram](https://www.instagram.com/steinhaug), [Youtube](https://www.youtube.com/@kimsteinhaug), [X](https://x.com/steinhaug), [Ko-Fi](https://ko-fi.com/steinhaug), [Github](https://github.com/steinhaug), [Gitlab](https://gitlab.com/steinhaug)

**Generative AI lenker:**
[Udio](https://www.udio.com/creators/Steinhaug), [Suno](https://suno.com/@steinhaug), [Huggingface](https://huggingface.co/steinhaug)

**Resurser og hjelpesider:**
[Linktr.ee/steinhaugai](https://linktr.ee/steinhaugai), [Linktr.ee/stainhaug](https://linktr.ee/stainhaug), [pinterest/steinhaug](https://no.pinterest.com/steinhaug/), [pinterest/stainhaug](https://no.pinterest.com/stainhaug/)
