<?php

class Migration_20240914_fix_users_schema {
    public function up($pdo) {
        // First, check the current structure
        $columns = [];
        $stmt = $pdo->query("PRAGMA table_info(users)");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[strtolower($row['name'])] = true;
        }

        // Add missing columns
        if (!isset($columns['phone'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT ''");
        }
        if (!isset($columns['bio'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT");
        }
        if (!isset($columns['role'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'user'");
        }
        if (!isset($columns['is_active'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT 1");
        }
        if (!isset($columns['remember_token'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(100)");
        }
        if (!isset($columns['email_verified_at'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMP NULL");
        }
        if (!isset($columns['created_at'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }
        if (!isset($columns['updated_at'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }

        return true;
    }
    
    public function down($pdo) {
        // This is a schema update, so we can't reliably roll back
        return true;
    }
}
