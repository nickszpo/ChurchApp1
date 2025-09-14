<?php
session_start();

// Log the logout action
if (isset($_SESSION['user_id'])) {
    require_once 'config/database.php';
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id']]);
    } catch (Exception $e) {
        error_log('Logout logging error: ' . $e->getMessage());
    }
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit();
?>