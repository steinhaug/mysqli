<?php
/**
 * Error Handling Test & Demo - ROBUST VERSION
 * 
 * Kjør denne filen manuelt for å se hvordan feilhåndtering fungerer
 * Alle feil fanges uten at scriptet terminerer
 */

// Assumes $mysqli is already initialized
echo "=== MYSQLI2 ERROR HANDLING DEMO ===\n\n";

// Helper function for visual separation
function section($title) {
    echo "\n--- $title ---\n";
}

// Helper function to safely execute tests
function safeTest($testName, callable $testFunction) {
    try {
        $testFunction();
    } catch (DatabaseException $e) {
        echo "DatabaseException caught!\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo "Error Code: " . $e->getCode() . "\n";
        if (method_exists($e, 'getSqlQuery')) {
            echo "SQL Query: " . $e->getSqlQuery() . "\n";
        }
    } catch (Throwable $e) {
        echo "Other Error caught: " . get_class($e) . "\n";
        echo "Message: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

section("1. TABLE DOES NOT EXIST");
safeTest("Table not found", function() use ($mysqli) {
    $result = $mysqli->execute("SELECT * FROM non_existent_table");
});

section("2. COLUMN DOES NOT EXIST");
safeTest("Column not found", function() use ($mysqli) {
    $result = $mysqli->execute("SELECT non_existent_column FROM zzz_testtable");
});

section("3. SYNTAX ERROR IN SQL");
safeTest("SQL syntax error", function() use ($mysqli) {
    $result = $mysqli->execute("SELECT * FORM zzz_testtable"); // FORM instead of FROM
});

section("4. WRONG NUMBER OF PARAMETERS");
safeTest("Parameter count mismatch", function() use ($mysqli) {
    // SQL expects 2 parameters but we only provide 1
    $result = $mysqli->execute(
        "SELECT * FROM zzz_testtable WHERE user_id = ? AND email = ?",
        'is',
        [1] // Missing second parameter
    );
});

section("5. WRONG PARAMETER TYPES");
safeTest("Parameter type mismatch", function() use ($mysqli) {
    // Sending string where integer is expected
    $result = $mysqli->execute(
        "SELECT * FROM zzz_testtable WHERE TestID = ?",
        'i',
        ['not_a_number']
    );
    echo "Note: MySQL often converts types silently, result count: " . count($result) . "\n";
});

section("6. DUPLICATE KEY ERROR");
safeTest("Duplicate key insertion", function() use ($mysqli) {
    // First insert
    $mysqli->execute("INSERT INTO zzz_testtable (TestID, user_id, created, email, string, hours) VALUES (?, ?, NOW(), ?, ?, ?)",
                     'iissd',
                     [1, 1, 'duplicate@test.com', 'test', 0]);
});

section("7. FOREIGN KEY CONSTRAINT (if applicable)");
safeTest("Foreign key constraint", function() use ($mysqli) {
    // This assumes there might be FK constraints
    $result = $mysqli->execute(
        "DELETE FROM zzz_testtable WHERE TestID = ?",
        'i',
        [1]
    );
    echo "Deleted rows: $result\n";
});

section("8. CONNECTION LOST (simulated)");
safeTest("Connection lost", function() use ($mysqli) {
    // Close connection to simulate lost connection
    $mysqli->close();
    $result = $mysqli->execute("SELECT 1");
});

// Reconnect for remaining tests
try {
    $mysqli = Mysqli2::getInstance();
    echo "Reconnected successfully\n";
} catch (Throwable $e) {
    echo "Failed to reconnect: " . $e->getMessage() . "\n";
    echo "Remaining tests will be skipped\n";
    return;
}

section("9. TESTING WITHOUT EXCEPTIONS");
safeTest("Non-exception mode", function() use ($mysqli) {
    echo "Disabling exceptions...\n";
    $mysqli->setUseExceptions(false);

    // This should return false instead of throwing exception
    $result = $mysqli->execute("SELECT * FROM another_non_existent_table");
    if ($result === false) {
        $error = $mysqli->getLastError();
        echo "Query failed (no exception thrown)\n";
        echo "Last Error: " . $error['error'] . "\n";
        echo "Failed Query: " . $error['query'] . "\n";
        echo "Error Number: " . $error['errno'] . "\n";
    }

    // Re-enable exceptions
    $mysqli->setUseExceptions(true);
});

section("10. EXECUTE1 WITH NO RESULTS");
safeTest("Execute1 variations", function() use ($mysqli) {
    // With default - should throw error
    echo "Testing execute1 with 'default' (should error):\n";
    try {
        $result = $mysqli->execute1(
            "SELECT * FROM zzz_testtable WHERE TestID = ?",
            'i',
            [9999],
            'default'
        );
    } catch (Throwable $e) {
        echo "Error as expected: " . $e->getMessage() . "\n";
    }
    
    // With true - should return null
    echo "\nTesting execute1 with true (should return null):\n";
    $result = $mysqli->execute1(
        "SELECT * FROM zzz_testtable WHERE TestID = ?",
        'i',
        [9999],
        true
    );
    echo "Result: " . var_export($result, true) . "\n";
});

section("11. DATA TYPE MISMATCH IN RESULTS");
safeTest("Data type handling", function() use ($mysqli) {
    // Insert a decimal, retrieve as string
    $id = $mysqli->execute(
        "INSERT INTO zzz_testtable (user_id, created, email, string, hours) VALUES (?, NOW(), ?, ?, ?)",
        'issd',
        [99, 'decimal@test.com', 'decimal test', 123.456]
    );
    
    $hours = $mysqli->execute1(
        "SELECT hours FROM zzz_testtable WHERE TestID = ?",
        'i',
        [$id],
        0
    );
    
    echo "Inserted: 123.456\n";
    echo "Retrieved: $hours (type: " . gettype($hours) . ")\n";
    echo "Note: MySQL returns decimals as strings\n";
    
    // Cleanup
    $mysqli->execute("DELETE FROM zzz_testtable WHERE TestID = ?", 'i', [$id]);
});

section("12. TESTING BATCH ERRORS");
safeTest("Batch operation errors", function() use ($mysqli) {
    // One of the batch inserts will fail
    $results = $mysqli->executeBatch(
        "INSERT INTO zzz_testtable (TestID, user_id, created, email, string, hours) VALUES (?, ?, NOW(), ?, ?, ?)",
        'iissd',
        [
            [NULL, 1, 'batch1@test.com', 'batch 1', 1], // OK
            [1, 1, 'batch2@test.com', 'batch 2', 2],    // FAIL - duplicate key
            [NULL, 1, 'batch3@test.com', 'batch 3', 3]  // Should this run?
        ]
    );
    echo "Batch results: " . print_r($results, true) . "\n";
});

echo "\n=== END OF ERROR HANDLING DEMO ===\n";
echo "All tests completed - script did not terminate\n";