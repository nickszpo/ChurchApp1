<?php
require_once 'config/database.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get database connection
$db = Database::getInstance();
$pdo = $db->getConnection();

// Set PDO to throw exceptions
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Test query to get priests
$query = "SELECT id, full_name, role, is_active FROM users WHERE role = 'priest' AND is_active = 1 ORDER BY full_name";

echo "<h1>Priest Dropdown Test</h1>";

try {
    // Execute the query
    $stmt = $pdo->query($query);
    $priests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Query Results</h2>";
    echo "<p>Query: <code>" . htmlspecialchars($query) . "</code></p>";
    echo "<p>Number of priests found: " . count($priests) . "</p>";
    
    if (count($priests) > 0) {
        echo "<h3>Priests Data</h3>";
        echo "<pre>" . print_r($priests, true) . "</pre>";
        
        // Test the dropdown
        echo "<h3>Test Dropdown</h3>";
        echo "<select class='form-select' style='width: 300px;'>";
        echo "<option value=''>-- Select a priest --</option>";
        foreach ($priests as $priest) {
            echo sprintf(
                "<option value='%d'>%s</option>",
                $priest['id'],
                htmlspecialchars($priest['full_name'])
            );
        }
        echo "</select>";
    } else {
        echo "<p>No active priests found in the database.</p>";
    }
    
    // Check the users table structure
    echo "<h2>Users Table Structure</h2>";
    $stmt = $pdo->query("PRAGMA table_info(users)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($columns) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Not Null</th><th>Default Value</th><th>Primary Key</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>" . $col['cid'] . "</td>";
            echo "<td>" . htmlspecialchars($col['name']) . "</td>";
            echo "<td>" . htmlspecialchars($col['type']) . "</td>";
            echo "<td>" . $col['notnull'] . "</td>";
            echo "<td>" . ($col['dflt_value'] ?? 'NULL') . "</td>";
            echo "<td>" . $col['pk'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<div style='color: red;'>";
    echo "<h2>Database Error</h2>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}

// Show the raw output of the query
if (isset($priests)) {
    echo "<h2>Raw Query Output</h2>";
    echo "<pre>" . print_r($priests, true) . "</pre>";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
    .form-select { margin: 10px 0; padding: 8px; }
    table { border-collapse: collapse; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>
