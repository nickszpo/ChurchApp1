<?php
session_start();
require_once 'config/database.php';

// Get database connection
$db = Database::getInstance();
$pdo = $db->getConnection();

// Set PDO to throw exceptions
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get active priests
$priests = [];
try {
    $stmt = $pdo->query("SELECT id, full_name FROM users WHERE role = 'priest' AND is_active = 1 ORDER BY full_name");
    $priests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Priest Dropdown</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Test Priest Dropdown</h1>
        
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Simple Dropdown</h5>
                <select class="form-select" id="priest_id" name="priest_id" style="width: 300px;">
                    <option value="">-- Select a priest --</option>
                    <?php foreach ($priests as $priest): ?>
                        <option value="<?php echo $priest['id']; ?>">
                            <?php echo htmlspecialchars($priest['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Debug Information</h5>
                <h6>Priests Data (<?php echo count($priests); ?> found):</h6>
                <pre><?php print_r($priests); ?></pre>
                
                <h6 class="mt-3">PHP Version:</h6>
                <p><?php echo phpversion(); ?></p>
                
                <h6 class="mt-3">Database File:</h6>
                <p><?php echo realpath('database/st_thomas_aquinas_parish_events.db'); ?></p>
            </div>
        </div>
    </div>
</body>
</html>
