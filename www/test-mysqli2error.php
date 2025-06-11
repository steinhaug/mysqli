<?php
/**
 * Error Handling Test & Demo
 * 
 * Kjør denne filen manuelt for å se hvordan feilhåndtering fungerer
 */


// Assumes $mysqli is already initialized
echo "=== MYSQLI2 ERROR HANDLING DEMO ===\n\n";

// Helper function for visual separation
function section($title) {
    echo "\n--- $title ---\n";
}

section("1. TABLE DOES NOT EXIST");
try {
    $result = $mysqli->execute("SELECT * FROM non_existent_table");
} catch (DatabaseException $e) {
    echo "Exception caught!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "SQL Query: " . $e->getSqlQuery() . "\n";
}

section("2. COLUMN DOES NOT EXIST");
try {
    $result = $mysqli->execute("SELECT non_existent_column FROM zzz_testtable");
} catch (DatabaseException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "SQL: " . $e->getSqlQuery() . "\n";
}

section("3. SYNTAX ERROR IN SQL");
try {
    $result = $mysqli->execute("SELECT * FORM zzz_testtable"); // FORM instead of FROM
} catch (DatabaseException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "SQL: " . $e->getSqlQuery() . "\n";
}

section("4. WRONG NUMBER OF PARAMETERS");
try {
    // SQL expects 2 parameters but we only provide 1
    $result = $mysqli->execute(
        "SELECT * FROM zzz_testtable WHERE user_id = ? AND email = ?",
        'is',
        [1] // Missing second parameter
    );
} catch (DatabaseException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "This happens when parameter count doesn't match type string\n";
}

section("5. WRONG PARAMETER TYPES");
try {
    // Sending string where integer is expected
    $result = $mysqli->execute(
        "SELECT * FROM zzz_testtable WHERE TestID = ?",
        'i',
        ['not_a_number']
    );
    echo "Note: MySQL often converts types silently, result count: " . count($result) . "\n";
} catch (DatabaseException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

section("6. DUPLICATE KEY ERROR");
try {
    // First insert
    $mysqli->execute("INSERT INTO zzz_testtable (TestID, user_id, created, email, string, hours) VALUES (?, ?, NOW(), ?, ?, ?)",
                     'iissd',
                     [1, 1, 'duplicate@test.com', 'test', 0]);
} catch (DatabaseException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . " (1062 = Duplicate entry)\n";
}

section("7. FOREIGN KEY CONSTRAINT (if applicable)");
try {
    // This assumes there might be FK constraints
    $result = $mysqli->execute(
        "DELETE FROM zzz_testtable WHERE TestID = ?",
        'i',
        [1]
    );
    echo "Deleted rows: $result\n";
} catch (DatabaseException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "This would show FK constraint errors if any exist\n";
}

section("8. CONNECTION LOST (simulated)");
try {
    // Close connection to simulate lost connection
    $mysqli->close();
    $result = $mysqli->execute("SELECT 1");
} catch (DatabaseException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "This shows what happens when connection is lost\n";
}

// Reconnect for remaining tests
$mysqli = Mysqli2::getInstance();

section("9. TESTING WITHOUT EXCEPTIONS");
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

section("10. EXECUTE1 WITH NO RESULTS");
try {
    // This should handle empty results based on return parameter
    
    // With default - should throw error
    echo "Testing execute1 with 'default' (should error):\n";
    try {
        $result = $mysqli->execute1(
            "SELECT * FROM zzz_testtable WHERE TestID = ?",
            'i',
            [9999],
            'default'
        );
    } catch (DatabaseException $e) {
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
    
} catch (DatabaseException $e) {
    echo "Unexpected error: " . $e->getMessage() . "\n";
}

section("11. DATA TYPE MISMATCH IN RESULTS");
try {
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
    
} catch (DatabaseException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

section("12. TESTING BATCH ERRORS");
try {
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
} catch (DatabaseException $e) {
    echo "Batch operation failed: " . $e->getMessage() . "\n";
    echo "Note: Batch stops on first error\n";
}

echo "\n=== END OF ERROR HANDLING DEMO ===\n";
