CREATE TABLE IF NOT EXISTS `zzz_testtable` (
  `TestID` int(10) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_swedish_ci NOT NULL,
  `string` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_swedish_ci NOT NULL,
  `hours` decimal(5,2) NOT NULL,
  `validfrom` datetime DEFAULT CURRENT_TIMESTAMP,
  `validto` datetime DEFAULT NULL,
  PRIMARY KEY (`TestID`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

INSERT INTO `zzz_testtable` (`TestID`, `user_id`, `created`, `email`, `string`, `hours`, `validfrom`, `validto`) VALUES
	(1, 1, '2021-10-14 11:00:52', 'testmail@yopmail.com', '\', exit()', 12.50, '2021-10-14 11:01:06', NULL),
	(2, 1, '2021-10-14 11:01:28', 'user-@example.org', '[{"\'', 0.00, '2021-10-14 11:02:52', NULL),
	(3, 1, '2021-10-14 11:03:12', 'user%example.com@example.org', '\'\'"@0,@1', 1.25, '2021-10-14 11:03:38', NULL),
	(4, 1, '2021-10-14 11:03:59', 'mailhost!username@example.org', '"#,=1', 2.75, '2021-10-14 11:04:07', NULL),
	(5, 2, '2021-10-14 11:04:42', '1234567890123456789012345678901234567890123456789012345678901234+x@example.com', ';;""\';', 0.00, '2021-10-14 11:04:49', '2021-10-14 11:04:51');
