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
                        // Parse the DATABASE_URL properly
                        $parsedUrl = parse_url($databaseUrl);
                        if ($parsedUrl && isset($parsedUrl['host'], $parsedUrl['user'], $parsedUrl['pass'], $parsedUrl['path'])) {
                            $dsn = "pgsql:host=" . $parsedUrl['host'] . 
                                   ";port=" . ($parsedUrl['port'] ?? '5432') . 
                                   ";dbname=" . ltrim($parsedUrl['path'], '/') . 
                                   ";user=" . $parsedUrl['user'] . 
                                   ";password=" . $parsedUrl['pass'];
                            error_log("Parsed DATABASE_URL: " . $dsn);
                        } else {
                            // Fallback to using the URL directly
                            $dsn = $databaseUrl;
                            error_log("Using DATABASE_URL directly: " . $databaseUrl);
                        }
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
            
            // Database initialization is handled by init_db.php
            // $this->initializeDatabase();
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

    // REMOVED: Database initialization is now handled entirely by init_db.php
    // This method has been completely removed to prevent SQLite syntax conflicts
}
