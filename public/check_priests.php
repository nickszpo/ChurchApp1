<?php
require_once 'config/database.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get database connection
$db = Database::getInstance();
$pdo = $db->getConnection();

// Set PDO to throw exceptions
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // 1. Check if users table has the required columns
    echo "=== Checking Users Table ===\n";
    $stmt = $pdo->query("PRAGMA table_info(users)");
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[strtolower($row['name'])] = $row;
        echo "- {$row['name']} ({$row['type']})\n";
    }
    
    // 2. Check if we have any priests in the database
    echo "\n=== Checking Priests ===\n";
    $stmt = $pdo->query("SELECT id, username, full_name, email, is_active FROM users WHERE role = 'priest'");
    $priests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($priests) > 0) {
        echo "Found " . count($priests) . " priests in the database:\n";
        foreach ($priests as $priest) {
            echo "- ID: {$priest['id']}, Name: {$priest['full_name']}, Email: {$priest['email']}, Active: " . ($priest['is_active'] ? 'Yes' : 'No') . "\n";
        }
    } else {
        echo "No priests found in the database.\n";
    }
    
    // 3. Check if we can add a test priest
    echo "\n=== Testing Priest Addition ===\n";
    try {
        $test_email = 'test_priest_' . time() . '@example.com';
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, full_name, email, password, role, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'priest', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        
        $username = 'testpriest' . time();
        $full_name = 'Test Priest';
        $password = password_hash('test123', PASSWORD_DEFAULT);
        
        $stmt->execute([$username, $full_name, $test_email, $password]);
        $priest_id = $pdo->lastInsertId();
        
        echo "Successfully added test priest with ID: $priest_id\n";
        
        // Rollback the transaction to not leave test data
        $pdo->rollBack();
        echo "Test transaction rolled back. No changes were made to the database.\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Error adding test priest: " . $e->getMessage() . "\n";
        echo "SQL Error Code: " . $e->getCode() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
