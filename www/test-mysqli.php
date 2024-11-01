<?php

class MysqliFailure extends Exception
{
}

try {
    $collate = $mysqli->return_charset_and_collate([['utf8' => 'utf8_swedish_ci', 'utf8mb4' => 'utf8mb4_swedish_ci']]);

    if (!(!empty($collate['utf8']) and !empty($collate['utf8mb4']))) {
        throw new MysqliFailure('Mysql collate didnt return as expected');
    }

    $mysqli->query('DROP TABLE IF EXISTS `zzz_testtable`');
    $mysqli->query('CREATE TABLE `zzz_testtable` (
        `TestID` INT(10) NOT NULL AUTO_INCREMENT,
        `user_id` INT(10) UNSIGNED NOT NULL,
        `created` DATETIME NOT NULL,
        `email` VARCHAR(100) NOT NULL COLLATE \'' . $collate['utf8mb4'] . '\',
        `string` VARCHAR(100) NOT NULL COLLATE \'' . $collate['utf8mb4'] . '\',
        `hours` DECIMAL(5,2) NOT NULL,
        `validfrom` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
        `validto` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`TestID`) USING BTREE
    ) CHARSET=utf8mb4 COLLATE=\'' . $collate['utf8mb4'] . '\' ENGINE=InnoDB AUTO_INCREMENT=6');

    if (!$mysqli->table_exist('zzz_testtable')) {
        throw new MysqliFailure('zzz_testtable does not exist!');
    }

    $mysqli->query("INSERT INTO `zzz_testtable` (`TestID`, `user_id`, `created`, `email`, `string`, `hours`, `validfrom`, `validto`) VALUES (1, 1, '2021-10-14 11:00:52', 'testmail@yopmail.com', '\\', exit()', 12.50, '2021-10-14 11:01:06', NULL)");
    $mysqli->query("INSERT INTO `zzz_testtable` (`TestID`, `user_id`, `created`, `email`, `string`, `hours`, `validfrom`, `validto`) VALUES (2, 1, '2021-10-14 11:01:28', 'user-@example.org', '[{\"\\'', 0.00, '2021-10-14 11:02:52', NULL)");
    $mysqli->query("INSERT INTO `zzz_testtable` (`TestID`, `user_id`, `created`, `email`, `string`, `hours`, `validfrom`, `validto`) VALUES (3, 1, '2021-10-14 11:03:12', 'user%example.com@example.org', '\\'\\'\"@0,@1', 1.25, '2021-10-14 11:03:38', NULL)");
    $mysqli->query("INSERT INTO `zzz_testtable` (`TestID`, `user_id`, `created`, `email`, `string`, `hours`, `validfrom`, `validto`) VALUES (4, 1, '2021-10-14 11:03:59', 'mailhost!username@example.org', '\"#,=1', 2.75, '2021-10-14 11:04:07', NULL)");
    $mysqli->query("INSERT INTO `zzz_testtable` (`TestID`, `user_id`, `created`, `email`, `string`, `hours`, `validfrom`, `validto`) VALUES (5, 2, '2021-10-14 11:04:42', '1234567890123456789012345678901234567890123456789012345678901234+x@example.com', ';;\"\"\\';', 0.00, '2021-10-14 11:04:49', '2021-10-14 11:04:51')");

    $testResult = 'success';
} catch (MysqliFailure $e) {
    $testResult = 'fail';
    $testError = 'Failure: ' . $e->getMessage();
} catch (Exception $e) {
    $testResult = 'fail';
    $testError = 'Failure: ' . $e->getMessage();
} finally {
    echo '<div class="testblock">';
    echo '<h5>Phase1: ' . $testResult . '</h5>';
    echo '<p>Setting up the database table with 5 rows</p>';
    if (!empty($testError)) {
        echo '<p class="error">' . $testError . '</p>';
        unset($testError);
    }
    echo '</div>';
}

try {
    // selects
    $UserID = 1;
    $count = $mysqli->prepared_query1('SELECT count(*) FROM `zzz_testtable` WHERE `user_id`=?', 'i', [$UserID], 0);
    if (4 !== $count) {
        throw new Exception('prepared_query1(sql,0) error');
    }

    $TestID = 5;
    $row = $mysqli->prepared_query1('SELECT * FROM `zzz_testtable` WHERE `TestID`=?', 'i', [$TestID], true);
    if (!(!empty($row) and is_array($row) and 8 == count($row))) {
        throw new Exception('prepared_query1(sql,true) error');
    }

    $TestID = 5;
    $row = $mysqli->prepared_query1('SELECT * FROM `zzz_testtable` WHERE `TestID`=?', 'i', [$TestID]);
    if(count($row) == 1){
        if (!(!empty($row[0]) and is_array($row[0]) and 8 == count($row[0]))) {
            throw new Exception('prepared_query1(sql) default parameters error');
        }
    } else if (!(!empty($row) and is_array($row) and 8 == count($row))) {
        throw new Exception('prepared_query1(sql) default parameters error');
    }

    $TestID = 1;
    $set = $mysqli->prepared_query('SELECT * FROM `zzz_testtable` WHERE `TestID`=?', 'i', [$TestID]);
    if (!(isset($set[0]) and !empty($set[0]) and is_array($set[0]) and 8 == count($set[0]))) {
        throw new Exception('prepared_query() returned unexpected result');
    }

    // delete
    $TestID = 1;
    $UserID = 1;
    $affected_rows = $mysqli->prepared_query('DELETE FROM `zzz_testtable` WHERE `TestID`=? AND `user_id`=?', 'ii', [$TestID, $UserID]);
    if (!$affected_rows) {
        throw new Exception('prepared_query(delete from...) reported 0 deletion');
    }

    $UserID = 1;
    $count = $mysqli->prepared_query1('SELECT count(*) FROM `zzz_testtable` WHERE `user_id`=?', 'i', [$UserID], 0);
    if (3 !== $count) {
        throw new Exception('prepared_query(delete from...) database still has all records');
    }

    // inserts
    $UserID = 2;
    $string = 'æøåinstittuttet';
    $sql = [
        'INSERT INTO `zzz_testtable` (`user_id`, `created`, `email`, `string`, `hours`, `validfrom`, `validto`) VALUES (?,?,?,?,?,?,?)',
        'isssdss',
        [$UserID, '2020-01-01 00:00:00', 'test/test@test.com', $string, 1.23, '2020-01-01 00:00:00', '2021-01-01 00:00:00'],
    ];
    $TestID = $mysqli->prepared_insert($sql);
    if (!$TestID) {
        throw new Exception('prepared_insert(insert into) inserted_id error');
    }

    $TestID_check = $mysqli->prepared_query1('SELECT `TestID` FROM `zzz_testtable` WHERE `string`=?', 's', [$string], 0);
    if ($TestID != $TestID_check) {
        throw new Exception('prepared_query1(check insert_id from insert into) mismatch ');
    }

    // update
    $sql = [
        'UPDATE `zzz_testtable` SET `email`=?, `string`=? WHERE `TestID`=?',
        'ssi',
        ['updated@email.com', 'UPDATED', $TestID],
    ];
    $affected_rows = $mysqli->prepared_insert($sql);
    if (!$affected_rows) {
        throw new Exception('prepared_insert(update..) affected_rows error ');
    }

    $row = $mysqli->prepared_query1('SELECT `TestID`, `email`, `string` FROM `zzz_testtable` WHERE `TestID`=?', 'i', [$TestID], true);
    if (!(isset($row['string']) and 'UPDATED' == $row['string'])) {
        throw new Exception('prepared_insert(update..) data integrity fail');
    }

    $testResult = 'success';
} catch (Exception $e) {
    $testResult = 'fail';
    $testError = 'Failure: ' . $e->getMessage();
} finally {
    echo '<div class="testblock">';
    echo '<h5>Phase2: ' . $testResult . '</h5>';
    echo '<p>Checking all the prepared statement functions</p>';
    if (!empty($testError)) {
        echo '<p class="error">' . $testError . '</p>';
        unset($testError);
    }
    echo '</div>';
}
