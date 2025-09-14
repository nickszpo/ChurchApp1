<?php
header('Content-Type: application/json');

// Start session and check authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require_once '../config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

// Include the necessary managers
require_once '../includes/AppointmentManager.php';
require_once '../includes/ResourceManager.php';
require_once '../includes/NotificationManager.php';

$appointmentManager = new AppointmentManager($pdo);
$resourceManager = new ResourceManager($pdo);
$notificationManager = new NotificationManager($pdo);

// Get the HTTP method and appointment ID
$method = $_SERVER['REQUEST_METHOD'];
$appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$referenceNumber = $_GET['reference'] ?? null;

// Helper function to send JSON response
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

// Handle different HTTP methods
switch ($method) {
    case 'GET':
        // Get a single appointment by ID or reference number
        if ($appointmentId) {
            $appointment = $appointmentManager->getAppointmentById($appointmentId);
        } elseif ($referenceNumber) {
            $appointment = $appointmentManager->getAppointmentByReference($referenceNumber);
        } 
        // Get all appointments with filters
        else {
            $filters = [
                'user_id' => $_SESSION['role'] !== 'admin' ? $_SESSION['user_id'] : ($_GET['user_id'] ?? null),
                'status' => $_GET['status'] ?? null,
                'service_id' => $_GET['service_id'] ?? null,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null,
                'search' => $_GET['search'] ?? null,
                'include_resources' => true,
                'order_by' => $_GET['order_by'] ?? 'start_time',
                'order_dir' => $_GET['order_dir'] ?? 'ASC',
                'limit' => $_GET['limit'] ?? null,
                'offset' => $_GET['offset'] ?? null
            ];
            
            // Remove null values
            $filters = array_filter($filters, function($value) {
                return $value !== null;
            });
            
            $appointments = $appointmentManager->getAppointments($filters);
            sendResponse($appointments);
        }
        
        if ($appointment) {
            // Check if the user is authorized to view this appointment
            if ($_SESSION['role'] !== 'admin' && $appointment['user_id'] != $_SESSION['user_id']) {
                http_response_code(403);
                sendResponse(['error' => 'Not authorized to view this appointment']);
            }
            
            sendResponse($appointment);
        } else {
            http_response_code(404);
            sendResponse(['error' => 'Appointment not found']);
        }
        break;
        
    case 'POST':
        // Create a new appointment
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Basic validation
        $requiredFields = ['service_id', 'title', 'start_time', 'end_time'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                sendResponse(['error' => "$field is required"]);
            }
        }
        
        // Set user ID from session
        $data['user_id'] = $_SESSION['user_id'];
        
        // Set default status
        if (!isset($data['status'])) {
            $data['status'] = 'pending';
        }
        
        try {
            // Check for scheduling conflicts
            $conflicts = $appointmentManager->checkForConflicts(
                $data['start_time'],
                $data['end_time'],
                null,
                $data['resource_ids'] ?? []
            );
            
            if (!empty($conflicts)) {
                http_response_code(409); // Conflict
                sendResponse([
                    'error' => 'Scheduling conflict',
                    'conflicts' => $conflicts
                ]);
            }
            
            // Create the appointment
            $result = $appointmentManager->createAppointment($data);
            
            // Send confirmation notification
            if ($result && $data['status'] === 'confirmed') {
                $notificationManager->sendAppointmentConfirmation($result['id']);
            }
            
            http_response_code(201);
            sendResponse([
                'id' => $result['id'],
                'reference_number' => $result['reference_number'],
                'message' => 'Appointment created successfully'
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            sendResponse(['error' => $e->getMessage()]);
        }
        break;
        
    case 'PUT':
    case 'PATCH':
        // Update an existing appointment
        if (!$appointmentId && !$referenceNumber) {
            http_response_code(400);
            sendResponse(['error' => 'Appointment ID or reference number is required']);
        }
        
        // Get the appointment to check ownership
        $appointment = $appointmentId 
            ? $appointmentManager->getAppointmentById($appointmentId)
            : $appointmentManager->getAppointmentByReference($referenceNumber);
            
        if (!$appointment) {
            http_response_code(404);
            sendResponse(['error' => 'Appointment not found']);
        }
        
        // Check authorization
        if ($_SESSION['role'] !== 'admin' && $appointment['user_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            sendResponse(['error' => 'Not authorized to update this appointment']);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $oldStatus = $appointment['status'];
        
        try {
            // If updating status, check permissions
            if (isset($data['status']) && $data['status'] !== $oldStatus) {
                // Only admins can approve or reject appointments
                if (in_array($data['status'], ['confirmed', 'rejected']) && $_SESSION['role'] !== 'admin') {
                    http_response_code(403);
                    sendResponse(['error' => 'Only administrators can confirm or reject appointments']);
                }
                
                // Only admins and the owner can cancel an appointment
                if ($data['status'] === 'cancelled' && 
                    $_SESSION['role'] !== 'admin' && 
                    $appointment['user_id'] != $_SESSION['user_id']) {
                    http_response_code(403);
                    sendResponse(['error' => 'Not authorized to cancel this appointment']);
                }
            }
            
            // Check for scheduling conflicts (only if time is being updated)
            if (isset($data['start_time']) || isset($data['end_time'])) {
                $startTime = $data['start_time'] ?? $appointment['start_time'];
                $endTime = $data['end_time'] ?? $appointment['end_time'];
                $resourceIds = $data['resource_ids'] ?? array_column($appointment['resources'] ?? [], 'id');
                
                $conflicts = $appointmentManager->checkForConflicts(
                    $startTime,
                    $endTime,
                    $appointment['id'],
                    $resourceIds
                );
                
                if (!empty($conflicts)) {
                    http_response_code(409); // Conflict
                    sendResponse([
                        'error' => 'Scheduling conflict',
                        'conflicts' => $conflicts
                    ]);
                }
            }
            
            // Update the appointment
            $result = $appointmentManager->updateAppointment($appointment['id'], $data);
            
            // Send notification if status changed
            if (isset($data['status']) && $data['status'] !== $oldStatus) {
                $notificationManager->sendAppointmentStatusUpdate($appointment['id'], $oldStatus, $data['status']);
            }
            
            sendResponse([
                'message' => 'Appointment updated successfully',
                'appointment' => $appointmentManager->getAppointmentById($appointment['id'])
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            sendResponse(['error' => $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        // Delete an appointment
        if (!$appointmentId && !$referenceNumber) {
            http_response_code(400);
            sendResponse(['error' => 'Appointment ID or reference number is required']);
        }
        
        // Get the appointment to check ownership
        $appointment = $appointmentId 
            ? $appointmentManager->getAppointmentById($appointmentId)
            : $appointmentManager->getAppointmentByReference($referenceNumber);
            
        if (!$appointment) {
            http_response_code(404);
            sendResponse(['error' => 'Appointment not found']);
        }
        
        // Check authorization
        if ($_SESSION['role'] !== 'admin' && $appointment['user_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            sendResponse(['error' => 'Not authorized to delete this appointment']);
        }
        
        try {
            $appointmentManager->deleteAppointment($appointment['id']);
            
            // Send cancellation notification
            if ($appointment['status'] !== 'cancelled') {
                $notificationManager->sendAppointmentStatusUpdate($appointment['id'], $appointment['status'], 'cancelled');
            }
            
            sendResponse(['message' => 'Appointment deleted successfully']);
            
        } catch (Exception $e) {
            http_response_code(500);
            sendResponse(['error' => $e->getMessage()]);
        }
        break;
        
    default:
        http_response_code(405);
        header('Allow: GET, POST, PUT, PATCH, DELETE');
        sendResponse(['error' => 'Method not allowed']);
        break;
}
?>
