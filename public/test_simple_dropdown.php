<?php
session_start();
require_once 'config/database.php';

// Get database connection
$db = Database::getInstance();
$pdo = $db->getConnection();

// Get active priests
$priests = [
    ['id' => 1, 'full_name' => 'TEST PRIEST 1'],
    ['id' => 2, 'full_name' => 'TEST PRIEST 2'],
    ['id' => 3, 'full_name' => 'TEST PRIEST 3']
];

// Try getting from database
try {
    $query = "SELECT id, full_name FROM users WHERE role = 'priest' AND is_active = 1 ORDER BY full_name";
    $stmt = $pdo->query($query);
    $db_priests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<!-- Database Query: " . htmlspecialchars($query) . " -->\n";
    echo "<!-- Database Results: " . count($db_priests) . " priests found -->\n";
    
    if (!empty($db_priests)) {
        $priests = $db_priests;
        echo "<!-- Using database priests -->\n";
    } else {
        echo "<!-- Using hardcoded test data (no priests found in database) -->\n";
    }
} catch (PDOException $e) {
    echo "<!-- Database Error: " . htmlspecialchars($e->getMessage()) . " -->\n";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Dropdown Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        select { padding: 8px; font-size: 16px; min-width: 250px; }
    </style>
</head>
<body>
    <h1>Simple Dropdown Test</h1>
    
    <h2>Hardcoded Test Data</h2>
    <select id="test_dropdown1">
        <option value="">-- Select --</option>
        <option value="1">Test Option 1</option>
        <option value="2">Test Option 2</option>
        <option value="3">Test Option 3</option>
    </select>
    
    <h2>PHP-Generated Dropdown</h2>
    <select id="test_dropdown2">
        <option value="">-- Select --</option>
        <?php foreach ($priests as $priest): ?>
            <option value="<?php echo $priest['id']; ?>">
                <?php echo htmlspecialchars($priest['full_name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    
    <h2>Debug Info</h2>
    <pre><?php print_r($priests); ?></pre>
    
    <script>
    console.log('Page loaded');
    console.log('Dropdown 1 options:', document.getElementById('test_dropdown1').length);
    console.log('Dropdown 2 options:', document.getElementById('test_dropdown2').length);
    
    // Log all options for dropdown 2
    const dropdown2 = document.getElementById('test_dropdown2');
    console.log('Dropdown 2 HTML:', dropdown2.outerHTML);
    
    for (let i = 0; i < dropdown2.options.length; i++) {
        console.log(`Option ${i}:`, {
            value: dropdown2.options[i].value,
            text: dropdown2.options[i].text,
            selected: dropdown2.options[i].selected
        });
    }
    </script>
</body>
</html>
