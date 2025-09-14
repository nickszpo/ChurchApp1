<?php
require_once 'config/database.php';

// Get database connection
$database = Database::getInstance();
$pdo = $database->getConnection();

echo "=== Database Structure Check ===\n\n";

// List all tables
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations'")->fetchAll(PDO::FETCH_COLUMN);

echo "Tables in database: " . implode(', ', $tables) . "\n\n";

// Check each table
foreach ($tables as $table) {
    echo "Table: $table\n";
    
    // Get table info
    $stmt = $pdo->query("PRAGMA table_info($table)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columns:\n";
    foreach ($columns as $col) {
        echo "- {$col['name']} ({$col['type']})\n";
    }
    
    // Count rows
    $count = $pdo->query("SELECT COUNT(*) as count FROM $table")->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Total rows: $count\n\n";
}

// Check if appointments table exists and show sample data
if (in_array('appointments', $tables)) {
    echo "=== Sample Appointment Data ===\n";
    $appointments = $pdo->query("SELECT * FROM appointments LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($appointments)) {
        echo "No appointments found in the database.\n";
    } else {
        foreach ($appointments as $appt) {
            echo "\nAppointment ID: " . ($appt['id'] ?? 'N/A') . "\n";
            echo "Title: " . ($appt['title'] ?? 'N/A') . "\n";
            echo "Date: " . ($appt['date'] ?? $appt['appointment_date'] ?? 'N/A') . "\n";
            echo "Status: " . ($appt['status'] ?? 'N/A') . "\n";
            echo "---\n";
        }
    }
}

echo "\n=== Database Check Complete ===\n";
?>
