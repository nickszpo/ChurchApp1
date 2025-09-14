<?php
require_once __DIR__ . '/config/database.php';

// Get database connection
$database = Database::getInstance();
$pdo = $database->getConnection();

try {
    // Enable foreign key constraints
    // $pdo->exec('PRAGMA foreign_keys = ON'); // Commented out for PostgreSQL compatibility
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Create users table
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT "user",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    
    // Create a default admin user if it doesn't exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, email, password, role) 
                   VALUES ('admin', 'admin@example.com', '$hashedPassword', 'admin')");
    }
    
    // Create services table
    $pdo->exec('CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        duration_minutes INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    
    // Create appointments table
    $pdo->exec('CREATE TABLE IF NOT EXISTS appointments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        service_id INTEGER,
        date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        status TEXT DEFAULT "pending",
        notes TEXT,
        contact_name TEXT NOT NULL,
        contact_email TEXT NOT NULL,
        contact_phone TEXT,
        reference_number TEXT,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (service_id) REFERENCES services(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )');
    
    // Commit the transaction
    $pdo->commit();
    
    echo "Database initialized successfully!\n";
    echo "Admin username: admin\n";
    echo "Admin password: admin123\n";
    
} catch (PDOException $e) {
    // Roll back the transaction if something failed
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error initializing database: " . $e->getMessage() . "\n");
}
?>
