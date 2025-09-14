<?php
require_once __DIR__ . '/config/database.php';

// Get database connection
$database = Database::getInstance();
$pdo = $database->getConnection();

try {
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
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT \'user\',
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)');

// Create services table
$pdo->exec('CREATE TABLE IF NOT EXISTS services (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)');

// Create appointments table
$pdo->exec('CREATE TABLE IF NOT EXISTS appointments (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    service_id INTEGER,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status VARCHAR(50) DEFAULT \'pending\',
    notes TEXT,
    contact_name VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    contact_phone VARCHAR(20),
    reference_number VARCHAR(50),
    created_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    priest_id INTEGER,
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
)');

// Create resources table
$pdo->exec('CREATE TABLE IF NOT EXISTS resources (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    quantity INTEGER NOT NULL DEFAULT 1,
    is_available BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)');

// Create service_resources table
$pdo->exec('CREATE TABLE IF NOT EXISTS service_resources (
    id SERIAL PRIMARY KEY,
    service_id INTEGER NOT NULL,
    resource_id INTEGER NOT NULL,
    quantity_required INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (resource_id) REFERENCES resources(id),
    UNIQUE(service_id, resource_id)
)');

// Create announcements table
$pdo->exec('CREATE TABLE IF NOT EXISTS announcements (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
