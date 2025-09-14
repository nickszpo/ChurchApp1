<?php
require_once 'config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// Check if appointments table exists
$tableExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='appointments'")->fetch();

if (!$tableExists) {
    die("The 'appointments' table does not exist in the database.\n");
}

// Get all appointments
$stmt = $pdo->query('SELECT id, title, date, start_time, status FROM appointments ORDER BY date, start_time');
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Appointments in the database:\n";
echo str_repeat("-", 80) . "\n";

if (empty($appointments)) {
    echo "No appointments found in the database.\n";} else {
    printf("%-5s %-30s %-15s %-15s %-10s\n", "ID", "Title", "Date", "Time", "Status");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($appointments as $appt) {
        printf("%-5d %-30s %-15s %-15s %-10s\n", 
            $appt['id'], 
            substr($appt['title'] ?? 'No Title', 0, 28),
            $appt['date'] ?? 'N/A',
            $appt['start_time'] ?? 'N/A',
            $appt['status'] ?? 'N/A'
        );
    }
}
?>
