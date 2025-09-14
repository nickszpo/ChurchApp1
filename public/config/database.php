<?php
class Database {
    private $pdo;
    private $isPostgreSQL;
    private static $instance = null;

    private function __construct() {
        try {
            // Check if we have PostgreSQL environment variables
            $dbHost = getenv('DB_HOST');
            $dbDatabase = getenv('DB_DATABASE');
            $dbUsername = getenv('DB_USERNAME');
            $dbPassword = getenv('DB_PASSWORD');
            
            // Debug: Log environment variables
            error_log("DB_HOST: " . ($dbHost ?: 'NOT SET'));
            error_log("DB_DATABASE: " . ($dbDatabase ?: 'NOT SET'));
            error_log("DB_USERNAME: " . ($dbUsername ?: 'NOT SET'));
            error_log("DB_PASSWORD: " . ($dbPassword ? 'SET' : 'NOT SET'));
            error_log("All environment variables: " . print_r($_ENV, true));
            
            // Force PostgreSQL if we're on Render (check for RENDER environment)
            $isRender = getenv('RENDER') || getenv('RENDER_EXTERNAL_URL');
            error_log("Is Render environment: " . ($isRender ? 'YES' : 'NO'));
            
            // Always use PostgreSQL on Render, even if env vars are not set
            if ($isRender || ($dbHost && $dbDatabase && $dbUsername && $dbPassword)) {
                // PostgreSQL configuration for production
                if ($dbHost && $dbDatabase && $dbUsername && $dbPassword) {
                    $dsn = "pgsql:host=" . $dbHost . 
                           ";port=" . getenv('DB_PORT', '5432') . 
                           ";dbname=" . $dbDatabase . 
                           ";user=" . $dbUsername . 
                           ";password=" . $dbPassword;
                    error_log("Using provided PostgreSQL connection");
                } else {
                    // Use Render's internal database connection
                    error_log("Using Render internal PostgreSQL connection");
                    // Try to get the database URL from Render's environment
                    $databaseUrl = getenv('DATABASE_URL');
                    if ($databaseUrl) {
                        $dsn = $databaseUrl;
                    } else {
                        // Fallback to default Render PostgreSQL
                        $dsn = "pgsql:host=localhost;port=5432;dbname=postgres;user=postgres;password=postgres";
                    }
                }
                
                $this->pdo = new PDO($dsn);
                $this->isPostgreSQL = true;
                error_log("Using PostgreSQL database");
            } else {
                // SQLite configuration for local development
                $db_file = __DIR__ . '/../database/st_thomas_aquinas_parish_events.db';
                $db_dir = dirname($db_file);
                
                if (!file_exists($db_dir)) {
                    mkdir($db_dir, 0755, true);
                }
                
                $this->pdo = new PDO('sqlite:' . $db_file);
                $this->isPostgreSQL = false;
                error_log("Using SQLite database");
            }
            
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Only execute PRAGMA for SQLite
            if (!$this->isPostgreSQL) {
                // $this->pdo->exec('PRAGMA foreign_keys = ON'); // Commented out for PostgreSQL compatibility
            }
            
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
            id ' . ($this->isPostgreSQL ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ',
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            full_name TEXT NOT NULL,
            first_name TEXT,
            last_name TEXT,
            email TEXT,
            phone TEXT,
            bio TEXT,
            is_active INTEGER DEFAULT 1,
            role TEXT NOT NULL DEFAULT ' . $this->pdo->quote('user') . ',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP' . 
            ($this->isPostgreSQL ? ',
            CONSTRAINT unique_username UNIQUE (username)' : '') . '
        )');

        // Services table
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS services (
            id ' . ($this->isPostgreSQL ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ',
            name TEXT NOT NULL UNIQUE,
            description TEXT,
            duration_minutes INTEGER DEFAULT 60,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP' .
            ($this->isPostgreSQL ? ',
            CONSTRAINT unique_service_name UNIQUE (name)' : '') . '
        )');

        // Resources table
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS resources (
            id ' . ($this->isPostgreSQL ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ',
            name TEXT NOT NULL UNIQUE,
            description TEXT,
            capacity INTEGER,
            location TEXT,
            color_code TEXT DEFAULT ' . $this->pdo->quote('#3b82f6') . ',
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP' .
            ($this->isPostgreSQL ? ',
            CONSTRAINT unique_resource_name UNIQUE (name)' : '') . '
        )');

        // Appointments table
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS appointments (
            id ' . ($this->isPostgreSQL ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ',
            reference_number TEXT NOT NULL UNIQUE,
            user_id INTEGER NOT NULL,
            service_id INTEGER NOT NULL,
            priest_id INTEGER,
            title TEXT NOT NULL,
            description TEXT,
            start_time TIMESTAMP NOT NULL,
            end_time TIMESTAMP NOT NULL,
            contact_name TEXT,
            contact_phone TEXT,
            contact_email TEXT,
            status TEXT NOT NULL DEFAULT ' . $this->pdo->quote('pending') . ',
            notes TEXT,
            is_recurring BOOLEAN DEFAULT FALSE,
            recurrence_pattern TEXT,
            recurrence_end_date TIMESTAMP,
            parent_appointment_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP' .
            ($this->isPostgreSQL ? ',
            CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
            CONSTRAINT fk_priest FOREIGN KEY (priest_id) REFERENCES users(id) ON DELETE SET NULL,' . "\n" . 
            '            CONSTRAINT unique_reference_number UNIQUE (reference_number)' : '') . '
        )');

        // Add indexes
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_appointments_user_id ON appointments(user_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_appointments_service_id ON appointments(service_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_appointments_start_time ON appointments(start_time)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_appointments_status ON appointments(status)');
        
        // For PostgreSQL, add additional indexes and constraints
        if ($this->isPostgreSQL) {
            // Add any PostgreSQL specific indexes or constraints here
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_appointments_dates ON appointments USING btree (start_time, end_time)');
        }
    }
}
