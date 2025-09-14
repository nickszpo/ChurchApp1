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

echo "<h1>Debug Priests</h1>";

try {
    // 1. Check if users table exists and has data
    echo "<h2>1. Checking users table</h2>";
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    if ($stmt->fetch()) {
        echo "<p>✓ Users table exists</p>";
        
        // Check users table structure
        // $stmt = $pdo->query("PRAGMA table_info(users)"); // Commented out for PostgreSQL compatibility
        echo "<h3>Users table structure:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Not Null</th><th>Default Value</th><th>Primary Key</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $row['cid'] . "</td>";
            echo "<td>" . $row['name'] . "</td>";
            echo "<td>" . $row['type'] . "</td>";
            echo "<td>" . $row['notnull'] . "</td>";
            echo "<td>" . ($row['dflt_value'] ?? 'NULL') . "</td>";
            echo "<td>" . $row['pk'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if we have any priests
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'priest' AND is_active = 1");
        $count = $stmt->fetch()['count'];
        echo "<p>Found $count active priests in the database.</p>";
        
        if ($count > 0) {
            // Show the priests
            $stmt = $pdo->query("SELECT id, username, full_name, email, role, is_active FROM users WHERE role = 'priest' AND is_active = 1");
            echo "<h3>Active Priests:</h3>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Active</th></tr>";
            while ($priest = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . $priest['id'] . "</td>";
                echo "<td>" . htmlspecialchars($priest['username']) . "</td>";
                echo "<td>" . htmlspecialchars($priest['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($priest['email']) . "</td>";
                echo "<td>" . ($priest['is_active'] ? 'Yes' : 'No') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No active priests found in the database.</p>";
        }
    } else {
        echo "<p>❌ Users table does not exist!</p>";
    }
    
    // 2. Test the exact query used in new-appointment.php
    echo "<h2>2. Testing the priests query</h2>";
    try {
        $query = "SELECT id, full_name FROM users WHERE role = 'priest' AND is_active = 1 ORDER BY full_name";
        echo "<p>Running query: <code>" . htmlspecialchars($query) . "</code></p>";
        
        $stmt = $pdo->query($query);
        $priests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>Query executed successfully. Found " . count($priests) . " priests.</p>";
        
        if (count($priests) > 0) {
            echo "<h3>Priests from query:</h3>";
            echo "<pre>" . print_r($priests, true) . "</pre>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error executing query: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}
?>

<h2>3. Test Form with Priests</h2>
<form>
    <div class="mb-3">
        <label for="priest_id" class="form-label">Assigned Priest (Test)</label>
        <select class="form-select" id="priest_id" name="priest_id">
            <option value="">-- Select a priest --</option>
            <?php
            if (isset($priests) && !empty($priests)) {
                foreach ($priests as $p) {
                    echo '<option value="' . $p['id'] . '">' . htmlspecialchars($p['full_name']) . '</option>';
                }
            } else {
                echo '<option value="" disabled>No priests available</option>';
            }
            ?>
        </select>
    </div>
</form>
