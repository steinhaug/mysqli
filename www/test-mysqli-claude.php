<?php

# https://chatgpt.com/c/6842b4a4-c574-8012-81a8-c5d54a8e030c

class Mysqli2TestSuite 
{
    private $mysqli;
    private $testTable = 'zzz_testtable';
    private $results = [];
    
    public function __construct($mysqli) 
    {
        $this->mysqli = $mysqli;
    }
    
    public function run() 
    {
        echo '<style>
            .test-suite { font-family: monospace; margin: 20px; }
            .test-block { margin: 10px 0; padding: 10px; border: 1px solid #ddd; }
            .test-success { background: #d4edda; border-color: #c3e6cb; }
            .test-fail { background: #f8d7da; border-color: #f5c6cb; }
            .test-title { font-weight: bold; margin-bottom: 5px; }
            .test-description { color: #666; font-size: 0.9em; }
        </style>';
        
        echo '<div class="test-suite">';
        echo '<h3>Mysqli2 Test Suite</h3>';
        
        $this->runPhase1();
        $this->runPhase2();
        $this->runPhase3();
        $this->runPhase4();
        
        $this->cleanup();
        
        echo '</div>';
    }
    
    private function runPhase1() 
    {
        $this->startTest('Phase 1: Database Setup');
        
        try {
            // Test charset/collate function
            $collate = $this->mysqli->return_charset_and_collate([
                'utf8' => 'utf8_swedish_ci', 
                'utf8mb4' => 'utf8mb4_swedish_ci'
            ]);
            
            if (empty($collate['utf8']) || empty($collate['utf8mb4'])) {
                throw new Exception('Charset/collate function failed');
            }
            
            // Create test table
            $this->mysqli->query('DROP TABLE IF EXISTS `' . $this->testTable . '`');
            $this->mysqli->query('CREATE TABLE `' . $this->testTable . '` (
                `TestID` INT(10) NOT NULL AUTO_INCREMENT,
                `user_id` INT(10) UNSIGNED NOT NULL,
                `created` DATETIME NOT NULL,
                `email` VARCHAR(100) NOT NULL,
                `string` VARCHAR(100) NOT NULL,
                `hours` DECIMAL(5,2) NOT NULL,
                `validfrom` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                `validto` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`TestID`)
            ) ENGINE=InnoDB');
            
            // Verify table exists
            if (!$this->mysqli->table_exist($this->testTable)) {
                throw new Exception('Table creation failed');
            }
            
            // Insert test data
            $this->insertTestData();
            
            $this->endTest(true, 'Database setup completed successfully');
            
        } catch (Exception $e) {
            $this->endTest(false, $e->getMessage());
        }
    }
    
    private function runPhase2() 
    {
        $this->startTest('Phase 2: Prepared Statement Testing');
        
        try {
            // Test prepared_query1 with return mode 0
            $count = $this->mysqli->prepared_query1(
                'SELECT COUNT(*) FROM `' . $this->testTable . '` WHERE `user_id`=?', 
                'i', 
                [1], 
                0
            );
            $this->assert($count === 4, 'prepared_query1(..., 0) should return scalar value');
            
            // Test prepared_query1 with return mode true
            $row = $this->mysqli->prepared_query1(
                'SELECT * FROM `' . $this->testTable . '` WHERE `TestID`=?', 
                'i', 
                [5], 
                true
            );
            $this->assert(is_array($row) && count($row) === 8, 'prepared_query1(..., true) should return assoc array');
            
            // Test prepared_query1 with NULL result
            $nullRow = $this->mysqli->prepared_query1(
                'SELECT * FROM `' . $this->testTable . '` WHERE `TestID`=?', 
                'i', 
                [999], 
                true
            );
            $this->assert($nullRow === null, 'prepared_query1(..., true) should return NULL for no results');
            
            // Test prepared_query for SELECT
            $resultSet = $this->mysqli->prepared_query(
                'SELECT * FROM `' . $this->testTable . '` WHERE `user_id`=?', 
                'i', 
                [1]
            );
            $this->assert(is_array($resultSet) && count($resultSet) === 4, 'prepared_query should return array of rows');
            
            // Test prepared_query for DELETE
            $deletedRows = $this->mysqli->prepared_query(
                'DELETE FROM `' . $this->testTable . '` WHERE `TestID`=? AND `user_id`=?', 
                'ii', 
                [1, 1]
            );
            $this->assert($deletedRows === 1, 'prepared_query(DELETE) should return affected rows');
            
            $this->endTest(true, 'All prepared statement tests passed');
            
        } catch (Exception $e) {
            $this->endTest(false, $e->getMessage());
        }
    }
    
    private function runPhase3() 
    {
        $this->startTest('Phase 3: INSERT and UPDATE Testing');
        
        try {
            // Test prepared_insert for INSERT
            $sql = [
                'INSERT INTO `' . $this->testTable . '` (`user_id`, `created`, `email`, `string`, `hours`) VALUES (?,?,?,?,?)',
                'isssd',
                [3, '2023-01-01 12:00:00', 'test@example.com', 'Test String ÆØÅ', 12.34]
            ];
            $insertId = $this->mysqli->prepared_insert($sql);
            $this->assert($insertId > 0, 'prepared_insert(INSERT) should return insert_id');
            
            // Verify INSERT
            $check = $this->mysqli->prepared_query1(
                'SELECT `string` FROM `' . $this->testTable . '` WHERE `TestID`=?',
                'i',
                [$insertId],
                0
            );
            $this->assert($check === 'Test String ÆØÅ', 'INSERT data integrity check');
            
            // Test prepared_insert for UPDATE
            $sql = [
                'UPDATE `' . $this->testTable . '` SET `email`=?, `hours`=? WHERE `TestID`=?',
                'sdi',
                ['updated@example.com', 99.99, $insertId]
            ];
            $affectedRows = $this->mysqli->prepared_insert($sql);
            $this->assert($affectedRows === 1, 'prepared_insert(UPDATE) should return affected rows');
            
            // Verify UPDATE
            $updated = $this->mysqli->prepared_query1(
                'SELECT `email`, `hours` FROM `' . $this->testTable . '` WHERE `TestID`=?',
                'i',
                [$insertId],
                true
            );
            $this->assert(
                $updated['email'] === 'updated@example.com' && $updated['hours'] == 99.99, 
                'UPDATE data integrity check'
            );
            
            $this->endTest(true, 'INSERT and UPDATE tests passed');
            
        } catch (Exception $e) {
            $this->endTest(false, $e->getMessage());
        }
    }
    
    private function runPhase4() 
    {
        $this->startTest('Phase 4: Edge Cases and Error Handling');
        
        try {
            // Test empty result set
            $emptyResult = $this->mysqli->prepared_query(
                'SELECT * FROM `' . $this->testTable . '` WHERE 1=0',
                '',
                []
            );
            $this->assert($emptyResult === [], 'Empty result set should return empty array');
            
            // Test NULL values
            $sql = [
                'INSERT INTO `' . $this->testTable . '` (`user_id`, `created`, `email`, `string`, `hours`, `validto`) VALUES (?,?,?,?,?,?)',
                'isssds',
                [4, '2023-01-01 00:00:00', 'null@test.com', 'NULL test', 0.00, null]
            ];
            $nullId = $this->mysqli->prepared_insert($sql);
            
            $nullCheck = $this->mysqli->prepared_query1(
                'SELECT `validto` FROM `' . $this->testTable . '` WHERE `TestID`=?',
                'i',
                [$nullId],
                true
            );
            $this->assert($nullCheck['validto'] === null, 'NULL values should be preserved');
            
            // Test special characters
            $specialChars = "Test ' \" \\ ; -- /* */ String";
            $sql = [
                'INSERT INTO `' . $this->testTable . '` (`user_id`, `created`, `email`, `string`, `hours`) VALUES (?,?,?,?,?)',
                'isssd',
                [5, '2023-01-01 00:00:00', 'special@test.com', $specialChars, 0.00]
            ];
            $specialId = $this->mysqli->prepared_insert($sql);
            
            $specialCheck = $this->mysqli->prepared_query1(
                'SELECT `string` FROM `' . $this->testTable . '` WHERE `TestID`=?',
                'i',
                [$specialId],
                0
            );
            $this->assert($specialCheck === $specialChars, 'Special characters should be handled correctly');
            
            $this->endTest(true, 'Edge case tests passed');
            
        } catch (Exception $e) {
            $this->endTest(false, $e->getMessage());
        }
    }
    
    private function insertTestData() 
    {

        $testData = [
            [1, 1, '2021-10-14 11:00:52', 'testmail@yopmail.com', "\\', exit()", 12.50, '2021-10-14 11:01:06', null],
            [2, 1, '2021-10-14 11:01:28', 'user-@example.org', '[{"\\\'', 0.00, '2021-10-14 11:02:52', null],
            [3, 1, '2021-10-14 11:03:12', 'user%example.com@example.org', "\\'\\'\"@0,@1", 1.25, '2021-10-14 11:03:38', null],
            [4, 1, '2021-10-14 11:03:59', 'mailhost!username@example.org', "\"#,=1", 2.75, '2021-10-14 11:04:07', null],
            [5, 2, '2021-10-14 11:04:42', '1234567890123456789012345678901234567890123456789012345678901234+x@example.com', ";;\"\"\\';", 0.00, '2021-10-14 11:04:49', '2021-10-14 11:04:51']
        ];

        foreach ($testData as $row) {
            $this->mysqli->query(sprintf(
                "INSERT INTO `%s` (`TestID`, `user_id`, `created`, `email`, `string`, `hours`) VALUES (%d, %d, '%s', '%s', '%s', %.2f)",
                $this->testTable,
                $row[0],
                $row[1],
                $row[2],
                $this->mysqli->real_escape_string($row[3]),
                $this->mysqli->real_escape_string($row[4]),
                $row[5]
            ));
        }
    }
    
    private function cleanup() 
    {
        $this->mysqli->query('DROP TABLE IF EXISTS `' . $this->testTable . '`');
        
        echo '<div class="test-block">';
        echo '<div class="test-title">Cleanup</div>';
        echo '<div class="test-description">Test table dropped</div>';
        echo '</div>';
    }
    
    private function startTest($name) 
    {
        $this->currentTest = $name;
        echo '<div class="test-block">';
        echo '<div class="test-title">' . $name . '</div>';
    }
    
    private function endTest($success, $message) 
    {
        $class = $success ? 'test-success' : 'test-fail';
        echo '<div class="test-description ' . $class . '">' . $message . '</div>';
        echo '</div>';
    }
    
    private function assert($condition, $message) 
    {
        if (!$condition) {
            throw new Exception('Assertion failed: ' . $message);
        }
    }
}

// Usage:
$tester = new Mysqli2TestSuite($mysqli);
$tester->run();