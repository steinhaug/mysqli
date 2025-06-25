<?php

require_once 'Mysqli2.php';

class Mysqli2Test {
    private $mysqli;
    private $testTable = 'zzz_testtable';
    private $passedTests = 0;
    private $failedTests = 0;
    
    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }
    
    public function runAllTests() {
        echo "Starting Mysqli2 Test Suite\n";
        echo "==========================\n\n";
        
        // Test basic connectivity
        $this->testConnection();
        
        // Test SELECT variations
        $this->testSelectAll();
        $this->testSelectWithWhere();
        $this->testSelectSingleRow();
        $this->testSelectCount();
        $this->testSelectWithEscaping();
        
        // Test INSERT
        $this->testInsert();
        $this->testInsertWithSpecialChars();
        
        // Test UPDATE
        $this->testUpdate();
        $this->testUpdateWithSpecialChars();
        
        // Test DELETE
        $this->testDelete();
        $this->testDeleteMultiple();
        
        // Test batch operations
        #$this->testBatchInsert();
        #this->testBatchSelect();
        
        // Test error handling
        $this->testErrorHandling();
        
        // Summary
        echo "\n==========================\n";
        echo "Test Summary:\n";
        echo "Passed: {$this->passedTests}\n";
        echo "Failed: {$this->failedTests}\n";
        echo "Total: " . ($this->passedTests + $this->failedTests) . "\n";
    }
    
    private function test($name, $condition, $expected = null, $actual = null) {
        if ($condition) {
            echo "[PASS] $name\n";
            $this->passedTests++;
        } else {
            echo "[FAIL] $name\n";
            if ($expected !== null && $actual !== null) {
                echo "       Expected: " . var_export($expected, true) . "\n";
                echo "       Actual: " . var_export($actual, true) . "\n";
            }
            $this->failedTests++;
        }
    }
    
    private function testConnection() {
        $this->test("Database connection", $this->mysqli->ping());
    }
    
    private function testSelectAll() {
        $sql = "SELECT * FROM {$this->testTable} ORDER BY TestID";
        $result = $this->mysqli->execute($sql);
        
        $this->test("SELECT all records", is_array($result) && count($result) == 5);
        $this->test("First record ID", $result[0]['TestID'] == 1);
        $this->test("Last record ID", $result[4]['TestID'] == 5);
    }
    
    private function testSelectWithWhere() {
        $sql = "SELECT * FROM {$this->testTable} WHERE user_id = ?";
        $result = $this->mysqli->execute($sql, 'i', [1]);
        
        $this->test("SELECT with WHERE", is_array($result) && count($result) == 4);
        
        // Test multiple conditions
        $sql = "SELECT * FROM {$this->testTable} WHERE user_id = ? AND hours > ?";
        $result = $this->mysqli->execute([$sql, 'id', [1, 2.0]]);
        
        $this->test("SELECT with multiple WHERE", is_array($result) && count($result) == 2);
    }
    
    private function testSelectSingleRow() {
        // Test with execute1 - return full row
        $sql = "SELECT * FROM {$this->testTable} WHERE TestID = ?";
        $result = $this->mysqli->execute1($sql, 'i', 3);
        
        $this->test("execute1 returns row", is_array($result) && $result['TestID'] == 3);
        
        // Test with execute1 - return single value
        $sql = "SELECT email FROM {$this->testTable} WHERE TestID = ?";
        $email = $this->mysqli->execute1($sql, 'i', 3, 0);
        
        $this->test("execute1 returns single value", $email == 'user%example.com@example.org');
        
        // Test with execute1 - null handling
        $sql = "SELECT * FROM {$this->testTable} WHERE TestID = ?";
        $result = $this->mysqli->execute1($sql, 'i', 999, true);
        
        $this->test("execute1 returns null for missing row", $result === null);
    }
    
    private function testSelectCount() {
        $sql = "SELECT COUNT(*) FROM {$this->testTable}";
        $count = $this->mysqli->execute1($sql, '', [], 0);
        
        $this->test("SELECT COUNT(*)", $count == 5);
        
        // Count with WHERE
        $sql = "SELECT COUNT(*) FROM {$this->testTable} WHERE user_id = ?";
        $count = $this->mysqli->execute1($sql, 'i', 2, 0);
        
        $this->test("SELECT COUNT(*) with WHERE", $count == 1);
    }
    
    private function testSelectWithEscaping() {
        // Test selecting records with special characters
        $testStrings = [
            "', exit()",
            '[{"\'',
            '\'\'"@0,@1',
            '"#,=1',
            ';;""\';'
        ];
        
        foreach ($testStrings as $index => $testString) {
            $sql = "SELECT * FROM {$this->testTable} WHERE string = ?";
            $result = $this->mysqli->execute($sql, 's', [$testString]);
            
            $this->test("SELECT with special chars: " . substr($testString, 0, 10) . "...", 
                       count($result) == 1 && $result[0]['string'] == $testString);
        }
    }
    
    private function testInsert() {
        // Basic INSERT
        $sql = "INSERT INTO {$this->testTable} (user_id, created, email, string, hours) VALUES (?, NOW(), ?, ?, ?)";
        $insertId = $this->mysqli->execute([$sql, 'issd', [99, 'test@example.com', 'normal string', 5.5]]);
        
        $this->test("INSERT returns ID", $insertId > 5);
        
        // Verify INSERT
        $sql = "SELECT * FROM {$this->testTable} WHERE TestID = ?";
        $result = $this->mysqli->execute1($sql, 'i', $insertId);
        
        $this->test("INSERT data verification", 
                   $result['user_id'] == 99 && 
                   $result['email'] == 'test@example.com' && 
                   $result['hours'] == '5.50');
        
        // Cleanup
        $this->mysqli->execute("DELETE FROM {$this->testTable} WHERE TestID = ?", 'i', [$insertId]);
    }
    
    private function testInsertWithSpecialChars() {
        $specialStrings = [
            "'; DROP TABLE users; --",
            '\\\'"; SELECT * FROM users; --',
            "Robert'); DROP TABLE students;--",
            '{"key": "value", "quote": "\\""}',
            "Line1\nLine2\rLine3\tTab"
        ];
        
        $insertedIds = [];
        
        foreach ($specialStrings as $special) {
            $sql = "INSERT INTO {$this->testTable} (user_id, created, email, string, hours) VALUES (?, NOW(), ?, ?, ?)";
            $insertId = $this->mysqli->execute($sql, 'issd', [99, 'special@test.com', $special, 0]);
            
            $this->test("INSERT with special chars: " . substr($special, 0, 20) . "...", $insertId > 0);
            
            if ($insertId) {
                $insertedIds[] = $insertId;
                
                // Verify data integrity
                $verify = $this->mysqli->execute1("SELECT string FROM {$this->testTable} WHERE TestID = ?", 'i', $insertId, 0);
                $this->test("Verify special chars stored correctly", $verify === $special);
            }
        }
        
        // Cleanup
        foreach ($insertedIds as $id) {
            $this->mysqli->execute("DELETE FROM {$this->testTable} WHERE TestID = ?", 'i', [$id]);
        }
    }
    
    private function testUpdate() {
        // Basic UPDATE
        $sql = "UPDATE {$this->testTable} SET hours = ? WHERE TestID = ?";
        $affected = $this->mysqli->execute($sql, 'di', [99.99, 1]);
        
        $this->test("UPDATE affected rows", $affected == 1);
        
        // Verify UPDATE
        $hours = $this->mysqli->execute1("SELECT hours FROM {$this->testTable} WHERE TestID = ?", 'i', 1, 0);
        $this->test("UPDATE data verification", $hours == '99.99');
        
        // Reset
        $this->mysqli->execute("UPDATE {$this->testTable} SET hours = ? WHERE TestID = ?", 'di', [12.50, 1]);
    }
    
    private function testUpdateWithSpecialChars() {
        $special = "'; UPDATE users SET admin=1; --";
        
        $sql = "UPDATE {$this->testTable} SET string = ? WHERE TestID = ?";
        $affected = $this->mysqli->execute($sql, 'si', [$special, 1]);
        
        $this->test("UPDATE with SQL injection attempt", $affected == 1);
        
        // Verify only intended row was updated
        $count = $this->mysqli->execute1("SELECT COUNT(*) FROM {$this->testTable} WHERE string = ?", 's', $special, 0);
        $this->test("UPDATE affected only target row", $count == 1);
        
        // Reset
        $this->mysqli->execute("UPDATE {$this->testTable} SET string = ? WHERE TestID = ?", 'si', ["', exit()", 1]);
    }
    
    private function testDelete() {
        // Insert test record
        $insertId = $this->mysqli->execute(
            "INSERT INTO {$this->testTable} (user_id, created, email, string, hours) VALUES (?, NOW(), ?, ?, ?)",
            'issd',
            [99, 'delete@test.com', 'to be deleted', 0]
        );
        
        // DELETE
        $sql = "DELETE FROM {$this->testTable} WHERE TestID = ?";
        $affected = $this->mysqli->execute($sql, 'i', [$insertId]);
        
        $this->test("DELETE affected rows", $affected == 1);
        
        // Verify deletion
        $result = $this->mysqli->execute1("SELECT * FROM {$this->testTable} WHERE TestID = ?", 'i', $insertId, true);
        $this->test("DELETE verification", $result === null);
    }
    
    private function testDeleteMultiple() {
        // INSERT test records
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->mysqli->execute(
                "INSERT INTO {$this->testTable} (user_id, created, email, string, hours) VALUES (?, NOW(), ?, ?, ?)",
                'issd',
                [99, "multi$i@test.com", "multi delete $i", $i]
            );
        }
        
        // DELETE multiple - only hours 0 and 1 should be deleted (< 2)
        $sql = "DELETE FROM {$this->testTable} WHERE user_id = ? AND hours < ?";
        $affected = $this->mysqli->execute($sql, 'id', [99, 2]);
        
        $this->test("DELETE multiple rows", $affected == 2);
        
        // Cleanup remaining
        $this->mysqli->execute("DELETE FROM {$this->testTable} WHERE user_id = ?", 'i', [99]);
    }
    
    private function testBatchInsert() {
        $sql = "INSERT INTO {$this->testTable} (user_id, created, email, string, hours) VALUES (?, NOW(), ?, ?, ?)";
        $paramSets = [
            [88, 'batch1@test.com', 'batch 1', 1.1],
            [88, 'batch2@test.com', 'batch 2', 2.2],
            [88, 'batch3@test.com', 'batch 3', 3.3]
        ];
        
        $results = $this->mysqli->executeBatch($sql, 'issd', $paramSets);
        
        $this->test("Batch INSERT count", count($results) == 3);
        $this->test("Batch INSERT IDs", $results[0] > 0 && $results[1] > 0 && $results[2] > 0);
        
        // Verify
        $count = $this->mysqli->execute1("SELECT COUNT(*) FROM {$this->testTable} WHERE user_id = ?", 'i', 88, 0);
        $this->test("Batch INSERT verification", $count == 3);
        
        // Cleanup
        $this->mysqli->execute("DELETE FROM {$this->testTable} WHERE user_id = ?", 'i', [88]);
    }
    
    private function testBatchSelect() {
        $sql = "SELECT * FROM {$this->testTable} WHERE TestID = ?";
        $paramSets = [[1], [3], [5]];
        
        $results = $this->mysqli->executeBatch($sql, 'i', $paramSets);
        
        $this->test("Batch SELECT count", count($results) == 3);
        $this->test("Batch SELECT results", 
                   $results[0][0]['TestID'] == 1 && 
                   $results[1][0]['TestID'] == 3 && 
                   $results[2][0]['TestID'] == 5);
    }
    
    private function testErrorHandling() {
        // Test with exceptions enabled (default)
        $exceptionThrown = false;
        try {
            $this->mysqli->execute("SELECT * FROM non_existent_table");
        } catch (DatabaseException $e) {
            //echo "Error: " . $e->getMessage() . "\n";
            //echo "SQL: " . $e->getSqlQuery() . "\n";
            $exceptionThrown = true;
            $this->test("Exception contains SQL query", strpos($e->getSqlQuery(), 'non_existent_table') !== false);
        }
        $this->test("Exception thrown for bad query", $exceptionThrown);
        
        // Test with exceptions disabled
        $this->mysqli->setUseExceptions(false);
        $result = $this->mysqli->execute("SELECT * FROM another_non_existent_table");
        $this->test("Returns false when exceptions disabled", $result === false);
        
        $lastError = $this->mysqli->getLastError();
        $this->test("Last error captured", 
                   is_array($lastError) && 
                   !empty($lastError['error']) && 
                   strpos($lastError['query'], 'another_non_existent_table') !== false);
        
        // Re-enable exceptions
        $this->mysqli->setUseExceptions(true);
    }
}

/*
// Run tests
try {
    $test = new Mysqli2Test($mysqli);
    $test->runAllTests();
} catch (Exception $e) {
    echo "Test suite failed with exception: " . $e->getMessage() . "\n";
    echo "In: " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
*/