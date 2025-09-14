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

// Function to add a column if it doesn't exist
function addColumnIfNotExists($pdo, $table, $column, $definition) {
    try {
        // $stmt = $pdo->query("PRAGMA table_info($table)"); // Commented out for PostgreSQL compatibility
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[strtolower($row['name'])] = true;
        }
        
        if (!isset($columns[strtolower($column)])) {
            echo "Adding column $column...\n";
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            return true;
        }
        return false;
    } catch (Exception $e) {
        echo "Error adding column $column: " . $e->getMessage() . "\n";
        return false;
    }
}

try {
    echo "=== Checking Database Structure ===\n";
    
    // Check users table
    echo "\n=== Users Table ===\n";
    // $stmt = $pdo->query("PRAGMA table_info(users)"); // Commented out for PostgreSQL compatibility
    $users_columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $users_columns[strtolower($row['name'])] = $row;
        echo "- {$row['name']} ({$row['type']})\n";
    }
    
    // Add missing columns to users table
    $columns_to_add = [
        'username' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'full_name' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'email' => "VARCHAR(255) NOT NULL UNIQUE",
        'password' => "VARCHAR(255) NOT NULL",
        'phone' => "VARCHAR(20) DEFAULT ''",
        'bio' => 'TEXT',
        'role' => "VARCHAR(20) NOT NULL DEFAULT 'user'",
        'is_active' => 'BOOLEAN DEFAULT 1',
        'remember_token' => 'VARCHAR(100)',
        'email_verified_at' => 'TIMESTAMP NULL',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIM'
    ];
    
    $changes_made = false;
    foreach ($columns_to_add as $column => $definition) {
        if (addColumnIfNotExists($pdo, 'users', $column, $definition)) {
            $changes_made = true;
        }
    }
    
    // If we made changes, show the new structure
    if ($changes_made) {
        echo "\n=== Updated Users Table Structure ===\n";
        // $stmt = $pdo->query("PRAGMA table_info(users)"); // Commented out for PostgreSQL compatibility
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "- {$row['name']} ({$row['type']})\n";
        }
    } else {
        echo "\nNo changes needed to the users table.\n";
    }
    
    echo "\n=== Database Check Complete ===\n";
    
} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
