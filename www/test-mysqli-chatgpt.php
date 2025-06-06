<?php
// Forutsetter at $mysqli allerede er en gyldig mysqli-tilkobling med korrekt charset

$mysqli->set_charset('utf8mb4');

// Slett og opprett testtabell
$mysqli->query("DROP TABLE IF EXISTS `zzz_testtable`");
$mysqli->query("
    CREATE TABLE `zzz_testtable` (
        `TestID` int NOT NULL,
        `user_id` int NOT NULL,
        `created` datetime NOT NULL,
        `email` varchar(255) NOT NULL,
        `string` text NOT NULL,
        `hours` decimal(5,2) NOT NULL,
        `validfrom` datetime NOT NULL,
        `validto` datetime DEFAULT NULL,
        PRIMARY KEY (`TestID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Testdata med edge case-strenger
$testData = [
    [1, 1, '2021-10-14 11:00:52', 'testmail@yopmail.com', '\\\', exit()', 12.50, '2021-10-14 11:01:06', null],
    [2, 1, '2021-10-14 11:01:28', 'user-@example.org', '[{"\\\'', 0.00, '2021-10-14 11:02:52', null],
    [3, 1, '2021-10-14 11:03:18', 'test@junk.net', '\\\\\\\'\\\\\\\'"@0,@1', 12.00, '2021-10-14 11:04:13', null],
    [4, 1, '2021-10-14 11:04:48', 'tull@fjols.no', '"#,=1', 0.00, '2021-10-14 11:05:40', null],
    [5, 1, '2021-10-14 11:06:16', 'abc@d.ef', ';;""\\\';', 12.00, '2021-10-14 11:06:43', null]
];

// Sett inn radene
foreach ($testData as $row) {
    $sql = [
        'INSERT INTO `zzz_testtable`
            (`TestID`, `user_id`, `created`, `email`, `string`, `hours`, `validfrom`, `validto`)
         VALUES (?,?,?,?,?,?,?,?)',
        'iisssdsd',
        $row
    ];
    $mysqli->prepared_insert($sql);
}

// Hent ut og vis alle rader
$rows = $mysqli->prepared_query([
    'SELECT * FROM `zzz_testtable` ORDER BY `TestID`',
    '', []
]);

echo "Testresultat:\n";
foreach ($rows as $r) {
    echo "- TestID {$r['TestID']}: {$r['string']}\n";
}