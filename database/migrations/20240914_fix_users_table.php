<?php

class Migration_20240914_fix_users_table {
    public function up($pdo) {
        // Rename existing users table if it exists
        $pdo->exec("ALTER TABLE users RENAME TO old_users");
        
        // Create new users table with all required columns
        $pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                phone VARCHAR(20) DEFAULT '',
                bio TEXT,
                role VARCHAR(20) NOT NULL DEFAULT 'user',
                is_active BOOLEAN NOT NULL DEFAULT 1,
                remember_token VARCHAR(100),
                email_verified_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Copy data from old table if it exists
        $pdo->exec("
            INSERT INTO users (id, full_name, email, password, is_active, created_at, updated_at)
            SELECT id, full_name, email, password, is_active, created_at, updated_at 
            FROM old_users
        ");
        
        // Drop the old table
        $pdo->exec("DROP TABLE IF EXISTS old_users");
        
        return true;
    }
    
    public function down($pdo) {
        // This is a destructive migration, so we'll just leave this empty
        // as we can't reliably revert to the previous state
        return true;
    }
}
