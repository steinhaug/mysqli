**Filstruktur**
- Mysqli2.php  
- Traits/UtilityTrait.php  

**Initialize**  
```
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
```

**Needed dummy class hardkoda i klassen min (gammel "bug")**  
```
/* Origally function to email sqlError to admin and die(); Hard errror; */
function sqlError__alertAndStop($sql_error, $sql_query, $reference = '', $UserID = 0, $trace = null){
    return time();
}
```

# MySQLi2 Bruksanvisning for AI

## Grunnleggende syntaks

### INSERT (bruk prepared_insert)
```php
$sql = [
    'INSERT INTO `table_name` (`col1`, `col2`, `col3`) VALUES (?,?,?)',
    'iss',  // i=integer, s=string, d=double
    [$var1, $var2, $var3]
];
$lastInsertId = $mysqli->prepared_insert($sql);
// Returnerer: last_insert_id for INSERT, affected_rows for UPDATE
```

### SELECT - Flere resultater (bruk prepared_query)
```php
$sql = [
    'SELECT * FROM `table_name` WHERE `status`=?',
    's',
    ['active']
];
$results = $mysqli->prepared_query($sql);
// Returnerer: array av associative arrays
// Tom tabell returnerer: []

// Hent første rad hvis den finnes:
$firstRow = $results[0] ?? null;
```

### SELECT - Ett resultat (bruk prepared_query1)
```php
// Hent bare verdien (f.eks. COUNT)
$count = $mysqli->prepared_query1(
    "SELECT COUNT(*) FROM `table_name` WHERE `status`=?", 
    's', 
    ['active'], 
    0  // returnerer første kolonne direkte som verdi
);

// Hent hele raden som array
$user = $mysqli->prepared_query1(
    "SELECT * FROM `users` WHERE `id`=?", 
    'i', 
    [$id], 
    'default'  // returnerer første rad som associative array
);

// Hent rad med NULL-håndtering
$user = $mysqli->prepared_query1(
    "SELECT * FROM `users` WHERE `id`=?", 
    'i', 
    [$id], 
    true  // returnerer NULL hvis ingen treff, ellers første rad som array
);

// Praktisk NULL-sjekk pattern
if (($user = $mysqli->prepared_query1($sql, 'i', [$id], true)) === null) {
    // Bruker finnes ikke
    throw new Exception('User not found');
}
// $user inneholder nå brukerdata
```

### UPDATE (bruk prepared_insert)
```php
$sql = [
    'UPDATE `table_name` SET `name`=?, `email`=? WHERE `id`=?',
    'ssi',
    [$name, $email, $id]
];
$affectedRows = $mysqli->prepared_insert($sql);
// Returnerer: antall påvirkede rader
```

### DELETE (bruk prepared_query)
```php
$sql = [
    'DELETE FROM `table_name` WHERE `id`=?',
    'i',
    [$id]
];
$deletedRows = $mysqli->prepared_query($sql);
// Returnerer: antall slettede rader
```

## Feilhåndtering

```php
// Med try/catch
try {
    $sql = ['INSERT INTO `users` (`name`) VALUES (?)', 's', [$name]];
    $userId = $mysqli->prepared_insert($sql);
    if (!$userId) {
        throw new Exception('Insert feilet');
    }
} catch (Exception $e) {
    // Håndter feil
    error_log($e->getMessage());
}

// Eller enkel sjekk
$results = $mysqli->prepared_query($sql);
if (empty($results)) {
    // Ingen resultater funnet
}
```

## Type-definering
- `i` = integer
- `s` = string  
- `d` = double/decimal

## Returverdier oppsummering
- `prepared_insert()`: Returnerer last_insert_id (INSERT) eller affected_rows (UPDATE)
- `prepared_query()`: Returnerer array av associative arrays ([] hvis tomt)
- `prepared_query1()`: 
  - Med `0`: Returnerer første kolonne som verdi
  - Med `'default'`: Returnerer første rad som array
  - Med `true`: Returnerer NULL hvis ingen treff, ellers første rad som array

## Viktige regler
1. ALLTID bruk prepared statements med array-syntaksen
2. ALLTID wrap tabellnavn i backticks: `table_name`
3. ALDRI konkatenér variabler direkte i SQL
4. prepared_insert() brukes for både INSERT og UPDATE
5. Sjekk alltid returverdier for feilhåndtering