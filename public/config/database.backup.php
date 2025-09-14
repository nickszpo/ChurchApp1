<?php
class Database {
    private $db_file = __DIR__ . '/../database/st_thomas_aquinas_parish_events.db';
    private $pdo;
    private static $instance = null;

    private function __construct() {
        try {
            // Create database directory if it doesn't exist
            $db_dir = dirname($this->db_file);
            if (!file_exists($db_dir)) {
                mkdir($db_dir, 0755, true);
            }

            // Connect to SQLite database
            $this->pdo = new PDO('sqlite:' . $this->db_file);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Enable foreign key constraints
            // $this->pdo->exec('PRAGMA foreign_keys = ON'); // Commented out for PostgreSQL compatibility
            
            // Create tables if they don't exist
            $this->initializeDatabase();
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    private function initializeDatabase() {
        // Users table
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            full_name TEXT NOT NULL,
            first_name TEXT,
            last_name TEXT,
            email TEXT,
            phone TEXT,
            bio TEXT,
            is_active INTEGER DEFAULT 1,
            role TEXT NOT NULL DEFAULT "user",
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        // Services table
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT,
            duration_minutes INTEGER DEFAULT 60,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        // Resources table
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS resources (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT,
            capacity INTEGER,
            location TEXT,
            color_code TEXT DEFAULT "#3b82f6",
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        // Appointments table
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS appointments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reference_number TEXT NOT NULL UNIQUE,
            user_id INTEGER NOT NULL,
            service_id INTEGER NOT NULL,
            priest_id INTEGER,
            title TEXT NOT NULL,
            description TEXT,
            start_time DATETIME NOT NULL,
            end_time DATETIME NOT NULL,
            contact_name TEXT,
            contact_phone TEXT,
            contact_email TEXT,
            status TEXT NOT NULL DEFAULT "pending",
            notes TEXT,
            is_recurring BOOLEAN DEFAULT 0,
            recurrence_pattern TEXT,
            recurrence_end_date DATETIME,
            parent_appointment_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
            FOREIGN KEY (priest_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (parent_appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
        )');

        // Appointment resources (many-to-many relationship)
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS appointment_resources (
            appointment_id INTEGER NOT NULL,
            resource_id INTEGER NOT NULL,
            notes TEXT,
            status TEXT DEFAULT "confirmed",
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (appointment_id, resource_id),
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
            FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE
        )');

        // Resource availability table
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS resource_availability (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            resource_id INTEGER NOT NULL,
            day_of_week INTEGER NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            is_available BOOLEAN DEFAULT 1,
            FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
            UNIQUE(resource_id, day_of_week, start_time, end_time)
        )');

        // Notifications table
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            type TEXT NOT NULL,
            is_read BOOLEAN DEFAULT 0,
            related_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )');

        // Settings table
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key TEXT NOT NULL UNIQUE,
            setting_value TEXT,
            setting_group TEXT,
            is_public BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        // Announcements table
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            is_pinned BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )');

        // Audit log table
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            table_name TEXT NOT NULL,
            record_id INTEGER,
            old_values TEXT,
            new_values TEXT,
            ip_address TEXT,
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )');

        // Create default admin user if not exists
        $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM users WHERE username = "admin"');
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $default_password = password_hash('admin123', PASSWORD_DEFAULT);
            $this->pdo->exec("INSERT INTO users (username, password, full_name, role) 
                VALUES ('admin', '$default_password', 'System Administrator', 'admin')");
        }

        // Insert default services if not exist
        $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM services');
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $this->pdo->exec("INSERT INTO services (name, description, duration_minutes) VALUES 
                ('Mass', 'Sunday Mass Service', 60),
                ('Confession', 'Confession Service', 30),
                ('Baptism', 'Baptism Ceremony', 45),
                ('Wedding', 'Wedding Ceremony', 90),
                ('Funeral', 'Funeral Service', 60),
                ('Prayer Meeting', 'Weekly Prayer Meeting', 60)");
        }

        // Insert default resources if not exist
        $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM resources');
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $this->pdo->exec("INSERT INTO resources (name, description, capacity, location, color_code) VALUES 
                ('Main Altar', 'Main church altar for ceremonies', 1, 'Sanctuary', '#8B4513'),
                ('Confession Booth', 'Private confession area', 1, 'Side Chapel', '#4B0082'),
                ('Baptismal Font', 'Baptism ceremony font', 1, 'Baptistry', '#87CEEB'),
                ('Organ', 'Church organ for music', 1, 'Choir Loft', '#DDA0DD'),
                ('Sound System', 'Audio equipment', 1, 'Control Room', '#32CD32'),
                ('Flowers', 'Decorative flowers', 10, 'Storage', '#FF69B4')");
        }

        // Insert default settings if not exist
        $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM settings');
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $this->pdo->exec("INSERT INTO settings (setting_key, setting_value, setting_group, is_public) VALUES
                ('app_name', 'St. Thomas Aquinas Parish', 'general', 1),
                ('timezone', 'UTC', 'general', 1),
                ('appointment_lead_time_hours', '24', 'appointments', 1),
                ('appointment_max_days_ahead', '90', 'appointments', 1),
                ('email_notifications_enabled', '1', 'notifications', 0),
                ('default_appointment_duration', '60', 'appointments', 0)");
        }

        // Create indexes for better performance
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_appointments_dates ON appointments(start_time, end_time)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_appointments_user ON appointments(user_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_appointments_status ON appointments(status)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, is_read)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_resource_availability_resource ON resource_availability(resource_id)');
    }
}
