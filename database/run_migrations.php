<?php
require_once __DIR__ . '/../config/database.php';

class DatabaseMigrator {
    private $pdo;
    private $migrationsDir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->migrationsDir = __DIR__ . '/migrations';
        
        // Create migrations table if it doesn't exist
        $this->createMigrationsTable();
    }
    
    private function createMigrationsTable() {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration_name TEXT NOT NULL UNIQUE,
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    }
    
    public function runMigrations() {
        // Get all migration files (both .sql and .php)
        $sqlFiles = glob($this->migrationsDir . '/*.sql');
        $phpFiles = glob($this->migrationsDir . '/*.php');
        $migrationFiles = array_merge($sqlFiles, $phpFiles);
        sort($migrationFiles);
        
        $executedMigrations = $this->getExecutedMigrations();
        $newMigrations = [];
        
        // Begin transaction
        $this->pdo->beginTransaction();
        
        try {
            foreach ($migrationFiles as $file) {
                $migrationName = basename($file);
                
                // Skip already executed migrations
                if (in_array($migrationName, $executedMigrations)) {
                    continue;
                }
                
                // Handle SQL migrations
                if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                    $sql = file_get_contents($file);
                    $this->pdo->exec($sql);
                } 
                // Handle PHP migrations
                elseif (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    require_once $file;
                    $className = 'Migration_' . pathinfo($file, PATHINFO_FILENAME);
                    if (class_exists($className)) {
                        $migration = new $className();
                        if (method_exists($migration, 'up')) {
                            $migration->up($this->pdo);
                        } else {
                            throw new Exception("Migration $migrationName is missing the 'up' method");
                        }
                    } else {
                        throw new Exception("Migration class $className not found in $migrationName");
                    }
                }
                
                // Add to executed migrations
                $stmt = $this->pdo->prepare('INSERT INTO migrations (migration_name) VALUES (?)');
                $stmt->execute([$migrationName]);
                
                $newMigrations[] = $migrationName;
                echo "Applied migration: $migrationName\n";
            }
            
            $this->pdo->commit();
            
            if (empty($newMigrations)) {
                echo "No new migrations to apply.\n";
            } else {
                echo "Successfully applied " . count($newMigrations) . " migration(s).\n";
            }
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo "Migration failed: " . $e->getMessage() . "\n";
            return false;
        }
        
        return true;
    }
    
    private function getExecutedMigrations() {
        $stmt = $this->pdo->query('SELECT migration_name FROM migrations');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Run migrations
$db = Database::getInstance();
$migrator = new DatabaseMigrator($db->getConnection());
$migrator->runMigrations();
