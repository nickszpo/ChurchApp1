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

// List of required tables and their expected columns
$required_tables = [
    'users' => [
        'id', 'username', 'email', 'password', 'full_name', 
        'phone', 'bio', 'role', 'is_active', 'remember_token',
        'email_verified_at', 'created_at', 'updated_at'
    ],
    'appointments' => [
        'id', 'title', 'description', 'start_time', 'end_time',
        'status', 'service_id', 'user_id', 'priest_id',
        'created_at', 'updated_at'
    ],
    'services' => [
        'id', 'name', 'description', 'duration', 'is_active',
        'created_at', 'updated_at'
    ],
    'announcements' => [
        'id', 'title', 'content', 'is_pinned', 'user_id',
        'created_at', 'updated_at'
    ]
];

echo "=== Checking Database Structure ===\n\n";

try {
    // Check each required table
    foreach ($required_tables as $table => $columns) {
        echo "Checking table: $table\n";
        echo str_repeat("-", 20) . "\n";
        
        try {
            // Check if table exists
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
            if ($stmt->rowCount() === 0) {
                echo "ERROR: Table '$table' does not exist!\n\n";
                continue;
            }
            
            // Get table structure
            $stmt = $pdo->query("PRAGMA table_info($table)");
            $table_columns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $table_columns[] = $row['name'];
            }
            
            // Check for missing columns
            $missing_columns = array_diff($columns, $table_columns);
            
            if (empty($missing_columns)) {
                echo "✓ Table structure is correct\n";
            } else {
                echo "WARNING: Missing columns in '$table':\n";
                foreach ($missing_columns as $col) {
                    echo "  - $col\n";
                }
            }
            
            echo "\n";
            
        } catch (Exception $e) {
            echo "ERROR checking table '$table': " . $e->getMessage() . "\n\n";
        }
    }
    
    echo "=== Database Check Complete ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
