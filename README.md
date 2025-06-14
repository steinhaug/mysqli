# Mysqli2

Mysqli Abstraction Layer v1.9.0


<div class="show_none">

# Table of Contents

- [Mysqli2](#mysqli2)
- [Table of Contents](#table-of-contents)
- [1. Description](#1-description)
- [2. Version History](#2-version-history)
  - [2.1 Log](#21-log)
    - [v1.9.0](#v190)
    - [V1.7.0](#v170)
    - [v1.6.6](#v166)
    - [v1.6.5](#v165)
    - [v1.6.4](#v164)
    - [v1.6.3](#v163)
    - [v1.6.2](#v162)
- [3. Install by composer](#3-install-by-composer)
- [4. Code Examples](#4-code-examples)
  - [4.1 Basic Init](#41-basic-init)
  - [4.2 Query](#42-query)
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

### v1.9.0

    - Ny refaktorert klasse, nye metoder. Se docs/mysqli2_documentation.md

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

To install the library use composer:

    composer require steinhaug/mysqli

# 4. Code Examples

## 4.1 Basic Init

```php
// Enable development mode (verbose errors)
Mysqli2::isDev(true);

// Get singleton instance with connection parameters
$mysqli = Mysqli2::getInstance($mysql_host, $mysql_port, $mysql_user, $mysql_password, $mysql_database);

// Set character encoding
$mysqli->set_charset("utf8");

// Check connection
if ($mysqli->connect_errno) {
    echo 'Failed to connect to MySQL: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error; 
    exit();
}
```

## 4.2 Query

Prepared query, quick query returns associated array:

    $TestID = 1;
    $row = $mysqli->prepared_query1('SELECT * FROM `zzz_testtable` WHERE `TestID`=?', 'i', [$TestID], true);
    if($row===null){
        throw new Exception('prepared_query1(sql,true) error');
    }

Prepared query, results comes in array

    $TestID = 5;
    $resultset = $mysqli->prepared_query('SELECT * FROM `zzz_testtable` WHERE `TestID`=?', 'i', [$TestID]);
    if( !count($resultset) ){
        throw new Exception('prepared_query() returned unexpected result');
    }
    // echo $resultset[0]

Prepared delete:

    $TestID = 1;
    $UserID = 1;
    $affected_rows = $mysqli->prepared_query('DELETE FROM `zzz_testtable` WHERE `TestID`=? AND `user_id`=?', 'ii', [$TestID, $UserID]);
    if (!$affected_rows) {
        throw new Exception('prepared_query(delete from...) reported 0 deletion');
    }

Prepared insert:

    $sql = [
        'INSERT INTO `table_name` (`col_name`, `col_name_two`, `col_name_three`, `col_name_four`, `col_name_five`) VALUES (?,?,?,?,?)',
        'issds',
        [$variable, '2020-01-01 00:00:00', 'test/test@test.com', 1.23, '2020-01-01 00:00:00'],
    ];
    $InsertId = $mysqli->prepared_insert($sql);
    if (!$InsertId) {
        throw new Exception('prepared_insert(insert into) inserted_id error');
    }

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
