<?php

class Migration_20240914_add_username_column {
    public function up($pdo) {
        // Add username column if it doesn't exist
        $stmt = $pdo->query("PRAGMA table_info(users)");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[strtolower($row['name'])] = true;
        }

        if (!isset($columns['username'])) {
            // Add username column with a default value based on email
            $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(255) NOT NULL DEFAULT ''");
            
            // Update existing records to set username based on email
            $pdo->exec("UPDATE users SET username = substr(email, 1, instr(email, '@') - 1) WHERE username = ''");
        }

        // Add any other missing columns
        $this->addMissingColumns($pdo);
        
        return true;
    }
    
    private function addMissingColumns($pdo) {
        $requiredColumns = [
            'phone' => "VARCHAR(20) DEFAULT ''",
            'bio' => 'TEXT',
            'role' => "VARCHAR(20) DEFAULT 'user'",
            'is_active' => 'BOOLEAN DEFAULT 1',
            'remember_token' => 'VARCHAR(100)',
            'email_verified_at' => 'TIMESTAMP NULL',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ];
        
        $stmt = $pdo->query("PRAGMA table_info(users)");
        $existingColumns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[strtolower($row['name'])] = true;
        }
        
        foreach ($requiredColumns as $column => $definition) {
            if (!isset($existingColumns[strtolower($column)])) {
                $pdo->exec("ALTER TABLE users ADD COLUMN $column $definition");
            }
        }
    }
    
    public function down($pdo) {
        // This is a schema update, so we can't reliably roll back
        return true;
    }
}
