<?php
require_once 'config/database.php';

// Set PDO to throw exceptions on error
$db = Database::getInstance();
$pdo = $db->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get current table structure
    // $stmt = $pdo->query("PRAGMA table_info(users)"); // Commented out for PostgreSQL compatibility
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[strtolower($row['name'])] = $row;
    }
    
    echo "Current table structure:\n";
    print_r($columns);
    
    // Add username column if it doesn't exist
    if (!isset($columns['username'])) {
        echo "Adding username column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(255) NOT NULL DEFAULT ''");
        
        // Set username based on email (everything before @)
        $pdo->exec("UPDATE users SET username = substr(email, 1, instr(email, '@') - 1) WHERE username = ''");
    }
    
    // Add other required columns if they don't exist
    $requiredColumns = [
        'full_name' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'phone' => "VARCHAR(20) DEFAULT ''",
        'bio' => 'TEXT',
        'role' => "VARCHAR(20) NOT NULL DEFAULT 'user'",
        'is_active' => 'BOOLEAN DEFAULT 1',
        'remember_token' => 'VARCHAR(100)',
        'email_verified_at' => 'TIMESTAMP NULL',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ];
    
    foreach ($requiredColumns as $column => $definition) {
        if (!isset($columns[strtolower($column)])) {
            echo "Adding $column column...\n";
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN $column $definition");
            } catch (PDOException $e) {
                echo "Error adding $column: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Commit changes
    $pdo->commit();
    echo "Users table structure updated successfully!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}

// Show final table structure
echo "\nFinal table structure:\n";
$stmt = $pdo->query("PRAGMA table_info(users)");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- {$row['name']} ({$row['type']})\n";
}
