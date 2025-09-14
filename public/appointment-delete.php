<?php
session_start();

error_log('Delete request received: ' . print_r($_POST, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /ChurchApp1/login.php');
    exit();
}

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'You do not have permission to perform this action';
    header('Location: /ChurchApp1/appointments.php');
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = 'Invalid request. Please try again.';
    header('Location: /ChurchApp1/appointments.php');
    exit();
}

// Validate appointment ID
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    $_SESSION['error'] = 'Invalid appointment ID';
    header('Location: /ChurchApp1/appointments.php');
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
    if (!$stmt->execute([$appointment_id])) {
        error_log('Failed to fetch appointment: ' . print_r($stmt->errorInfo(), true));
        throw new Exception('Failed to fetch appointment details');
    }
    
    $appointment = $stmt->fetch();
    error_log('Appointment to delete: ' . print_r($appointment, true));
    
    if (!$appointment) {
        error_log('Appointment not found with ID: ' . $appointment_id);
        throw new Exception('Appointment not found');
    }
    
    // Delete appointment resources first
    $stmt = $pdo->prepare('DELETE FROM appointment_resources WHERE appointment_id = ?');
    if (!$stmt->execute([$appointment_id])) {
        error_log('Failed to delete appointment resources: ' . print_r($stmt->errorInfo(), true));
        throw new Exception('Failed to delete appointment resources');
    }
    error_log('Deleted appointment resources for appointment ID: ' . $appointment_id);
    
    // Delete the appointment
    $stmt = $pdo->prepare('DELETE FROM appointments WHERE id = ?');
    if (!$stmt->execute([$appointment_id])) {
        error_log('Failed to delete appointment: ' . print_r($stmt->errorInfo(), true));
        throw new Exception('Failed to delete appointment');
    }
    error_log('Successfully deleted appointment ID: ' . $appointment_id);
    
    // Log the action
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $_SESSION['user_id'],
        'delete',
        'appointments',
        $appointment_id,
        json_encode($appointment)
    ]);
    
    // Log the action
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values) VALUES (?, ?, ?, ?, ?)');
    $logResult = $stmt->execute([
        $_SESSION['user_id'],
        'delete',
        'appointments',
        $appointment_id,
        json_encode($appointment)
    ]);
    
    if (!$logResult) {
        error_log('Failed to log deletion: ' . print_r($stmt->errorInfo(), true));
        // Don't throw exception for logging failure, just log it
    }
    
    $pdo->commit();
    
    $_SESSION['success'] = 'Appointment deleted successfully';
    error_log('Appointment deletion completed successfully');
    
} catch (Exception $e) {
    try {
        $pdo->rollBack();
        error_log('Rollback successful after error');
    } catch (Exception $rollbackEx) {
        error_log('Rollback failed: ' . $rollbackEx->getMessage());
    }
    
    $errorMsg = 'Error deleting appointment: ' . $e->getMessage();
    $_SESSION['error'] = $errorMsg;
    error_log($errorMsg);
    error_log('Error details: ' . $e->getTraceAsString());
}

// Redirect back to appointments list
header('Location: /ChurchApp1/appointments.php');
exit();
?>
