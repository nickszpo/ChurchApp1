<?php
header('Content-Type: application/json');

// Start session and check authentication
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

require_once '../config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

// Include the ResourceManager
require_once '../includes/ResourceManager.php';
$resourceManager = new ResourceManager($pdo);

// Get the HTTP method and resource ID
$method = $_SERVER['REQUEST_METHOD'];
$resourceId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Handle different HTTP methods
switch ($method) {
    case 'GET':
        // Get a single resource
        if ($resourceId) {
            $resource = $resourceManager->getResourceById($resourceId);
            if ($resource) {
                // Get availability for this resource
                $resource['availability'] = $resourceManager->getResourceAvailability($resourceId);
                echo json_encode($resource);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Resource not found']);
            }
        } 
        // Get all resources
        else {
            $resources = $resourceManager->getAllResources();
            echo json_encode($resources);
        }
        break;
        
    case 'POST':
        // Create a new resource
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Resource name is required']);
            exit();
        }
        
        try {
            $resourceId = $resourceManager->addResource($data);
            
            // Handle resource availability
            if (!empty($data['availability'])) {
                foreach ($data['availability'] as $day => $slots) {
                    if (is_array($slots)) {
                        foreach ($slots as $slot) {
                            list($startTime, $endTime) = explode(' - ', $slot);
                            $resourceManager->setResourceAvailability(
                                $resourceId,
                                $day,
                                $startTime,
                                $endTime,
                                true
                            );
                        }
                    }
                }
            }
            
            http_response_code(201);
            echo json_encode([
                'id' => $resourceId,
                'message' => 'Resource created successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'PUT':
    case 'PATCH':
        // Update an existing resource
        if (!$resourceId) {
            http_response_code(400);
            echo json_encode(['error' => 'Resource ID is required']);
            exit();
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $resourceManager->updateResource($resourceId, $data);
            
            // Update resource availability
            if (isset($data['availability'])) {
                // First, clear existing availability
                $pdo->prepare("DELETE FROM resource_availability WHERE resource_id = ?")->execute([$resourceId]);
                
                // Add new availability
                if (is_array($data['availability'])) {
                    foreach ($data['availability'] as $day => $slots) {
                        if (is_array($slots)) {
                            foreach ($slots as $slot) {
                                list($startTime, $endTime) = explode(' - ', $slot);
                                $resourceManager->setResourceAvailability(
                                    $resourceId,
                                    $day,
                                    $startTime,
                                    $endTime,
                                    true
                                );
                            }
                        }
                    }
                }
            }
            
            echo json_encode(['message' => 'Resource updated successfully']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        // Delete a resource
        if (!$resourceId) {
            http_response_code(400);
            echo json_encode(['error' => 'Resource ID is required']);
            exit();
        }
        
        try {
            $resourceManager->deleteResource($resourceId);
            echo json_encode(['message' => 'Resource deleted successfully']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
