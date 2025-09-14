<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'You do not have permission to perform this action';
    header('Location: /appointments.php');
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = 'Invalid request. Please try again.';
    header('Location: /appointments.php');
    exit();
}

// Validate appointment ID
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    $_SESSION['error'] = 'Invalid appointment ID';
    header('Location: /appointments.php');
    exit();
}

$appointment_id = (int)$_POST['id'];

require_once 'config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

try {
    $pdo->beginTransaction();
    
    // Get appointment details before deletion
    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ?');
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        throw new Exception('Appointment not found');
    }
    
    // Delete appointment resources first
    $stmt = $pdo->prepare('DELETE FROM appointment_resources WHERE appointment_id = ?');
    $stmt->execute([$appointment_id]);
    
    // Delete the appointment
    $stmt = $pdo->prepare('DELETE FROM appointments WHERE id = ?');
    $stmt->execute([$appointment_id]);
    
    // Log the action
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $_SESSION['user_id'],
        'delete',
        'appointments',
        $appointment_id,
        json_encode($appointment)
    ]);
    
    $pdo->commit();
    
    $_SESSION['success'] = 'Appointment deleted successfully';
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'Error deleting appointment: ' . $e->getMessage();
    error_log('Appointment deletion error: ' . $e->getMessage());
}

// Redirect back to appointments list
header('Location: /appointments.php');
exit();
?>
