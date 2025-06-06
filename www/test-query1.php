<?php





$res = $mysqli->prepared_query1("SELECT * FROM `userarpc__permissions` WHERE `PermissionID`=?", "i", [1]);
var_dump($res);

