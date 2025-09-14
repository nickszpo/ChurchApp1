<?php
require_once __DIR__ . '/config/database.php';

// Get database connection
$database = Database::getInstance();
$pdo = $database->getConnection();

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Create users table
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(255) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(255),
        first_name VARCHAR(255),
        last_name VARCHAR(255),
        phone VARCHAR(20),
        bio TEXT,
        is_active INTEGER DEFAULT 1,
        role VARCHAR(50) NOT NULL DEFAULT \'user\',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
    
    // Create services table
    $pdo->exec('CREATE TABLE IF NOT EXISTS services (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        duration_minutes INTEGER DEFAULT 60,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
    
    // Create resources table
    $pdo->exec('CREATE TABLE IF NOT EXISTS resources (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        capacity INTEGER,
        location VARCHAR(255),
        color_code VARCHAR(7) DEFAULT \'#3b82f6\',
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
    
    // Create appointments table
    $pdo->exec('CREATE TABLE IF NOT EXISTS appointments (
        id SERIAL PRIMARY KEY,
        reference_number VARCHAR(50) NOT NULL UNIQUE,
        user_id INTEGER NOT NULL,
        service_id INTEGER NOT NULL,
        priest_id INTEGER,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        start_time TIMESTAMP NOT NULL,
        end_time TIMESTAMP NOT NULL,
        contact_name VARCHAR(255),
        contact_phone VARCHAR(20),
        contact_email VARCHAR(255),
        status VARCHAR(50) NOT NULL DEFAULT \'pending\',
        notes TEXT,
        is_recurring BOOLEAN DEFAULT FALSE,
        recurrence_pattern VARCHAR(50),
        recurrence_end_date TIMESTAMP,
        parent_appointment_id INTEGER,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        CONSTRAINT fk_priest FOREIGN KEY (priest_id) REFERENCES users(id) ON DELETE SET NULL
    )');
    
    // Create indexes
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_appointments_user_id ON appointments(user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_appointments_service_id ON appointments(service_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_appointments_start_time ON appointments(start_time)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_appointments_status ON appointments(status)');
    
    // Create a default admin user if it doesn't exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, email, password, role, full_name) 
                   VALUES ('admin', 'admin@example.com', '$hashedPassword', 'admin', 'Administrator')");
    }
    
    // Insert default services if they don't exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM services");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        $pdo->exec("INSERT INTO services (name, description, duration_minutes) VALUES 
                   ('Mass', 'Sunday Mass Service', 60),
                   ('Confession', 'Confession Service', 30),
                   ('Baptism', 'Baptism Ceremony', 45),
                   ('Wedding', 'Wedding Ceremony', 90),
                   ('Funeral', 'Funeral Service', 60)");
    }
    
    // Insert default resources if they don't exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM resources");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        $pdo->exec("INSERT INTO resources (name, description, capacity, location, color_code) VALUES 
                   ('Main Church', 'Main church building', 200, 'Ground Floor', '#3b82f6'),
                   ('Chapel', 'Small chapel for intimate services', 50, 'First Floor', '#10b981'),
                   ('Meeting Room', 'Meeting room for consultations', 20, 'Second Floor', '#f59e0b'),
                   ('Garden', 'Outdoor garden area', 100, 'Outside', '#8b5cf6')");
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "Database initialized successfully!\n";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollback();
    echo "Error initializing database: " . $e->getMessage() . "\n";
    throw $e;
}
?>