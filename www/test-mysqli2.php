<?php

    $mysqli->query('DROP TABLE IF EXISTS `zzz_testtable`');
    $mysqli->query('CREATE TABLE `zzz_testtable` (
        `TestID` INT(10) NOT NULL AUTO_INCREMENT,
        `user_id` INT(10) UNSIGNED NOT NULL,
        `created` DATETIME NOT NULL,
        `email` VARCHAR(100) NOT NULL COLLATE \'utf8mb4_swedish_ci\',
        `string` VARCHAR(100) NOT NULL COLLATE \'utf8mb4_swedish_ci\',
        `hours` DECIMAL(5,2) NOT NULL,
        `validfrom` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
        `validto` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`TestID`) USING BTREE
    ) CHARSET=utf8mb4 COLLATE=\'utf8mb4_swedish_ci\' ENGINE=InnoDB AUTO_INCREMENT=6');

    $mysqli->query("INSERT INTO `zzz_testtable` (`TestID`, `user_id`, `created`, `email`, `string`, `hours`, `validfrom`, `validto`) VALUES (1, 1, '2021-10-14 11:00:52', 'testmail@yopmail.com', '\\', exit()', 12.50, '2021-10-14 11:01:06', NULL)");
    $mysqli->query("INSERT INTO `zzz_testtable` (`TestID`, `user_id`, `created`, `email`, `string`, `hours`, `validfrom`, `validto`) VALUES (2, 1, '2021-10-14 11:01:28', 'user-@example.org', '[{\"\\'', 0.00, '2021-10-14 11:02:52', NULL)");
    $mysqli->query("INSERT INTO `zzz_testtable` (`TestID`, `user_id`, `created`, `email`, `string`, `hours`, `validfrom`, `validto`) VALUES (3, 1, '2021-10-14 11:03:12', 'user%example.com@example.org', '\\'\\'\"@0,@1', 1.25, '2021-10-14 11:03:38', NULL)");
    $mysqli->query("INSERT INTO `zzz_testtable` (`TestID`, `user_id`, `created`, `email`, `string`, `hours`, `validfrom`, `validto`) VALUES (4, 1, '2021-10-14 11:03:59', 'mailhost!username@example.org', '\"#,=1', 2.75, '2021-10-14 11:04:07', NULL)");
    $mysqli->query("INSERT INTO `zzz_testtable` (`TestID`, `user_id`, `created`, `email`, `string`, `hours`, `validfrom`, `validto`) VALUES (5, 2, '2021-10-14 11:04:42', '1234567890123456789012345678901234567890123456789012345678901234+x@example.com', ';;\"\"\\';', 0.00, '2021-10-14 11:04:49', '2021-10-14 11:04:51')");

echo '<h1>test</h1>';


/*
$mysqli->execute(
    "",
    "",
    "");
*/
/*
$testTable = 'zzz_testtable';

        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $mysqli->execute(
                "INSERT INTO {$testTable} (user_id, created, email, string, hours) VALUES (?, NOW(), ?, ?, ?)",
                'issd',
                [99, "multi$i@test.com", "multi delete $i", $i]
            );
        }
        
echo '<br>';

        // DELETE multiple
        $sql = "DELETE FROM {$testTable} WHERE user_id = ? AND hours < ?";
        $affected = $mysqli->execute($sql, 'id', [99, 2.5]);
    echo $affected;
        
echo '<br>';


        $affected = $mysqli->execute("DELETE FROM {$testTable} WHERE user_id = ?", 'i', [99]);
    echo $affected;
*/


$test = new Mysqli2Test($mysqli);
$test->runAllTests();

