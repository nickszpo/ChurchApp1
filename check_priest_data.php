<?php
require_once 'config/database.php';

// Get database connection
$db = Database::getInstance();
$pdo = $db->getConnection();

// Test database connection
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<h1>Priest Data Check</h1>";
    
    // Check if users table exists
    $tableExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
    
    if (!$tableExists) {
        die("<p style='color: red;'>Error: 'users' table does not exist in the database.</p>");
    }
    
    // Check priest data
    $query = "SELECT id, full_name, role, is_active FROM users WHERE role = 'priest' AND is_active = 1 ORDER BY full_name";
    $stmt = $pdo->query($query);
    $priests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Query Results</h2>";
    echo "<p>Query: <code>" . htmlspecialchars($query) . "</code></p>";
    
    if (count($priests) > 0) {
        echo "<p>Found " . count($priests) . " active priests:</p>";
        echo "<ul>";
        foreach ($priests as $priest) {
            echo "<li>ID: " . htmlspecialchars($priest['id']) . " - " . 
                 htmlspecialchars($priest['full_name']) . " (Role: " . 
                 htmlspecialchars($priest['role']) . ", Active: " . 
                 ($priest['is_active'] ? 'Yes' : 'No') . ")</li>";
        }
        echo "</ul>";
        
        // Test dropdown
        echo "<h2>Test Dropdown</h2>";
        echo "<select style='padding: 8px; min-width: 250px;'>";
        echo "<option value=''>-- Select a priest --</option>";
        foreach ($priests as $p) {
            echo sprintf(
                '<option value="%s">%s</option>',
                htmlspecialchars($p['id']),
                htmlspecialchars($p['full_name'])
            );
        }
        echo "</select>";
    } else {
        echo "<p>No active priests found in the database.</p>";
        
        // Show all users for debugging
        echo "<h3>All Users in Database:</h3>";
        $allUsers = $pdo->query("SELECT id, full_name, role, is_active FROM users")->fetchAll(PDO::FETCH_ASSOC);
        if (count($allUsers) > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Name</th><th>Role</th><th>Active</th></tr>";
            foreach ($allUsers as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                echo "<td>" . ($user['is_active'] ? 'Yes' : 'No') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No users found in the database.</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
    echo "<h2>Database Error</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . htmlspecialchars($e->getLine()) . "</p>";
    echo "</div>";
}

// Show PHP info for debugging
echo "<h2>PHP Info</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>PDO Drivers: " . print_r(PDO::getAvailableDrivers(), true) . "</p>";
?>
