<?php
require_once 'config/database.php';

try {
    // Get database connection
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Test connection
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>Database Connection Test</h1>";
    echo "<p>✓ Connected to database successfully</p>";
    
    // Check if users table exists
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "<p>✓ Users table exists</p>";
        
        // Check if we have any priests
        $stmt = $pdo->query("SELECT id, full_name, email, is_active FROM users WHERE role = 'priest' AND is_active = 1");
        $priests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>Active Priests in Database</h2>";
        if (count($priests) > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Active</th></tr>";
            foreach ($priests as $priest) {
                echo "<tr>";
                echo "<td>" . $priest['id'] . "</td>";
                echo "<td>" . htmlspecialchars($priest['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($priest['email']) . "</td>";
                echo "<td>" . ($priest['is_active'] ? 'Yes' : 'No') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No active priests found in the database.</p>";
        }
        
        // Show the exact query being used in new-appointment.php
        echo "<h2>Test Query from new-appointment.php</h2>";
        $query = "SELECT id, full_name FROM users WHERE role = 'priest' AND is_active = 1 ORDER BY full_name";
        echo "<p>Query: <code>" . htmlspecialchars($query) . "</code></p>";
        
        $stmt = $pdo->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>Found " . count($results) . " priests with this query.</p>";
        echo "<pre>" . print_r($results, true) . "</pre>";
        
    } else {
        echo "<p style='color: red;'>❌ Users table does not exist!</p>";
    }
    
} catch (PDOException $e) {
    echo "<h1>Database Error</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}
?>
