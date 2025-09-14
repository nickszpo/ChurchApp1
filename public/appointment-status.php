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
$status = $_POST['status'] ?? '';
$rejection_reason = $_POST['rejection_reason'] ?? '';

// Validate status
$allowed_statuses = ['pending', 'approved', 'confirmed', 'cancelled', 'completed', 'rejected'];
if (!in_array($status, $allowed_statuses)) {
    $_SESSION['error'] = 'Invalid status';
    header('Location: /appointments.php');
    exit();
}

require_once 'config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

try {
    $pdo->beginTransaction();
    
    // Get appointment details
    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ?');
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        throw new Exception('Appointment not found');
    }
    
    // Update appointment status
    $update_sql = 'UPDATE appointments SET status = ?, updated_at = CURRENT_TIMESTAMP';
    $params = [$status, $appointment_id];
    
    // Add rejection reason if provided
    if ($status === 'rejected' && !empty($rejection_reason)) {
        $update_sql .= ', notes = ?';
        $params = [$status, $rejection_reason, $appointment_id];
    }
    
    $update_sql .= ' WHERE id = ?';
    
    $stmt = $pdo->prepare($update_sql);
    $stmt->execute($params);
    
    // Log the action
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $_SESSION['user_id'],
        'update_status',
        'appointments',
        $appointment_id,
        json_encode(['status' => $status, 'rejection_reason' => $rejection_reason])
    ]);
    
    $pdo->commit();
    
    $_SESSION['success'] = 'Appointment status updated successfully';
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'Error updating appointment status: ' . $e->getMessage();
    error_log('Appointment status update error: ' . $e->getMessage());
}

// Redirect back to appointment detail page
header('Location: /appointment.php?id=' . $appointment_id);
exit();
?>
