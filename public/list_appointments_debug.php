<?php
require_once 'config/database.php';

// Get database connection
$db = Database::getInstance();
$pdo = $db->getConnection();

// List all appointments
echo "<h2>Appointments in Database</h2>";
$stmt = $pdo->query('SELECT id, title, status, created_at FROM appointments ORDER BY created_at DESC');
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Created At</th><th>Actions</th></tr>";

foreach ($appointments as $appt) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($appt['id']) . "</td>";
    echo "<td>" . htmlspecialchars($appt['title']) . "</td>";
    echo "<td>" . htmlspecialchars($appt['status']) . "</td>";
    echo "<td>" . htmlspecialchars($appt['created_at']) . "</td>";
    echo "<td>";
    echo "<a href='appointment.php?id=" . $appt['id'] . "'>View</a> | ";
    echo "<a href='appointment-edit.php?id=" . $appt['id'] . "'>Edit</a> | ";
    echo "<form method='post' action='appointment-delete.php' style='display:inline;'>";
    echo "<input type='hidden' name='id' value='" . $appt['id'] . "'>";
    echo "<input type='hidden' name='csrf_token' value='" . ($_SESSION['csrf_token'] ?? '') . "'>";
    echo "<button type='submit' onclick='return confirm(\"Are you sure?\")'>Delete</button>";
    echo "</form>";
    echo "</td>";
    echo "</tr>";
}
echo "</table>";

// Show recent logs
echo "<h2>Recent Logs</h2>";
$logFile = ini_get('error_log');
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    $recentLogs = array_slice(explode("\n", $logs), -50); // Get last 50 lines
    echo "<pre>" . htmlspecialchars(implode("\n", $recentLogs)) . "</pre>";
} else {
    echo "Log file not found at: " . htmlspecialchars($logFile);
}
?>
