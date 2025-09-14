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
    // Check users table structure
    echo "=== Users Table Structure ===\n";
    $stmt = $pdo->query("PRAGMA table_info(users)");
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['name'] . ' (' . $row['type'] . ')';
    }
    echo "Columns: " . implode(', ', $columns) . "\n\n";
    
    // Check if we have any priests
    echo "=== Active Priests ===\n";
    $stmt = $pdo->query("SELECT id, username, full_name, email, role, is_active FROM users WHERE role = 'priest'");
    $priests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($priests) > 0) {
        echo "Found " . count($priests) . " priests in the database:\n";
        foreach ($priests as $priest) {
            echo "- ID: {$priest['id']}, Name: {$priest['full_name']}, Email: {$priest['email']}, Active: " . ($priest['is_active'] ? 'Yes' : 'No') . "\n";
        }
    } else {
        echo "No priests found in the database.\n";
        
        // Let's add a test priest
        echo "\nAdding a test priest...\n";
        $default_password = password_hash('priest123', PASSWORD_DEFAULT);
        $email = 'priest' . time() . '@example.com';
        $username = 'priest' . time();
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, full_name, email, password, role, is_active)
            VALUES (?, ?, ?, ?, 'priest', 1)
        ");
        
        $stmt->execute([
            $username,
            'Test Priest',
            $email,
            $default_password
        ]);
        
        echo "Added test priest with username: $username, password: priest123\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
