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

    // Drop tables in reverse order of dependency
    $dropTables = [
        'service_resources',
        'appointments',
        'announcements',
        'resources',
        'services',
        'users'
    ];
    
    foreach ($dropTables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS $table");
    }

    // Create users table
    $pdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT "user",
    phone TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Create services table
$db->exec('CREATE TABLE IF NOT EXISTS services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    duration_minutes INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Create appointments table
$db->exec('CREATE TABLE IF NOT EXISTS appointments (
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
    priest_id INTEGER,
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
)');

// Create resources table
$db->exec('CREATE TABLE IF NOT EXISTS resources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    quantity INTEGER NOT NULL DEFAULT 1,
    is_available BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Create service_resources table
$db->exec('CREATE TABLE IF NOT EXISTS service_resources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_id INTEGER NOT NULL,
    resource_id INTEGER NOT NULL,
    quantity_required INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (resource_id) REFERENCES resources(id),
    UNIQUE(service_id, resource_id)
)');

// Create announcements table
$db->exec('CREATE TABLE IF NOT EXISTS announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
)');

    // Create a default admin user
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@example.com', $hashedPassword, 'admin']);
    
    // Commit the transaction
    $pdo->commit();
    
    echo "Database setup completed successfully!\n";
    
} catch (PDOException $e) {
    // Roll back the transaction if something failed
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error setting up database: " . $e->getMessage() . "\n");
}
?>
