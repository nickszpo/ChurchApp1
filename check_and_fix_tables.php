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

// SQL to create tables if they don't exist
$tables_sql = [
    'services' => "
        CREATE TABLE IF NOT EXISTS services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            duration INTEGER NOT NULL DEFAULT 60,
            is_active BOOLEAN NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ",
    'appointments' => "
        CREATE TABLE IF NOT EXISTS appointments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            start_time DATETIME NOT NULL,
            end_time DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            service_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            priest_id INTEGER,
            full_name VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (service_id) REFERENCES services(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (priest_id) REFERENCES users(id)
        )
    ",
    'announcements' => "
        CREATE TABLE IF NOT EXISTS announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            is_pinned BOOLEAN NOT NULL DEFAULT 0,
            user_id INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ",
    'resources' => "
        CREATE TABLE IF NOT EXISTS resources (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            color VARCHAR(20) DEFAULT '#007bff',
            is_active BOOLEAN NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ",
    'appointment_resources' => "
        CREATE TABLE IF NOT EXISTS appointment_resources (
            appointment_id INTEGER NOT NULL,
            resource_id INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (appointment_id, resource_id),
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
            FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE
        )
    "
];

try {
    // Enable foreign keys
    $pdo->exec('PRAGMA foreign_keys = ON');
    
    // Create tables
    foreach ($tables_sql as $table_name => $sql) {
        try {
            $pdo->exec($sql);
            echo "✓ Table '$table_name' checked/created successfully\n";
        } catch (PDOException $e) {
            echo "Error creating table '$table_name': " . $e->getMessage() . "\n";
        }
    }
    
    // Check if we have at least one admin user
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $admin_count = $stmt->fetch()['count'];
    
    if ($admin_count == 0) {
        echo "\nNo admin user found. Creating default admin...\n";
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT INTO users (username, full_name, email, password, role, is_active)
            VALUES ('admin', 'Administrator', 'admin@example.com', '$password_hash', 'admin', 1)
        ");
        echo "Default admin created with username: admin, password: admin123\n";
    }
    
    // Check if we have at least one service
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM services");
    $service_count = $stmt->fetch()['count'];
    
    if ($service_count == 0) {
        echo "\nNo services found. Adding default services...\n";
        $default_services = [
            ['name' => 'Baptism', 'description' => 'Baptism service', 'duration' => 60],
            ['name' => 'Marriage', 'description' => 'Wedding ceremony', 'duration' => 120],
            ['name' => 'Confession', 'description' => 'Sacrament of Reconciliation', 'duration' => 30],
            ['name' => 'Mass', 'description' => 'Sunday Mass', 'duration' => 60],
            ['name' => 'Funeral', 'description' => 'Funeral service', 'duration' => 90]
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO services (name, description, duration, is_active)
            VALUES (:name, :description, :duration, 1)
        ");
        
        foreach ($default_services as $service) {
            $stmt->execute($service);
            echo "Added service: {$service['name']}\n";
        }
    }
    
    echo "\nDatabase setup complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    
    if (strpos($e->getMessage(), 'no such table') !== false) {
        echo "\nIt seems some tables are missing. Please run the database migrations first.\n";
    }
}
