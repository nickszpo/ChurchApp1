<?php

class ResourceManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get all active resources
     */
    public function getAllResources($includeInactive = false) {
        $sql = "SELECT * FROM resources";
        $params = [];
        
        if (!$includeInactive) {
            $sql .= " WHERE is_active = 1";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get resource by ID
     */
    public function getResourceById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM resources WHERE id = ?");
        $stmt->execute([$id]);
        
        return $stmt->fetch();
    }
    
    /**
     * Add a new resource
     */
    public function addResource($data) {
        $sql = "INSERT INTO resources (name, description, capacity, location, color_code, is_active) 
                VALUES (:name, :description, :capacity, :location, :color_code, :is_active)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':capacity' => $data['capacity'] ?? null,
            ':location' => $data['location'] ?? null,
            ':color_code' => $data['color_code'] ?? '#3b82f6',
            ':is_active' => $data['is_active'] ?? 1
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Update a resource
     */
    public function updateResource($id, $data) {
        $sql = "UPDATE resources SET 
                name = :name, 
                description = :description,
                capacity = :capacity,
                location = :location,
                color_code = :color_code,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':capacity' => $data['capacity'] ?? null,
            ':location' => $data['location'] ?? null,
            ':color_code' => $data['color_code'] ?? '#3b82f6',
            ':is_active' => $data['is_active'] ?? 1
        ]);
    }
    
    /**
     * Delete a resource
     */
    public function deleteResource($id) {
        // First delete resource availability
        $this->pdo->prepare("DELETE FROM resource_availability WHERE resource_id = ?")->execute([$id]);
        
        // Then delete the resource
        $stmt = $this->pdo->prepare("DELETE FROM resources WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Set resource availability
     */
    public function setResourceAvailability($resourceId, $dayOfWeek, $startTime, $endTime, $isAvailable = true) {
        // First remove any existing entry for this day and time
        $this->pdo->prepare("
            DELETE FROM resource_availability 
            WHERE resource_id = ? AND day_of_week = ? AND start_time = ? AND end_time = ?
        ")->execute([$resourceId, $dayOfWeek, $startTime, $endTime]);
        
        // If setting as available, we don't need to do anything else (just remove the restriction)
        if ($isAvailable) {
            return true;
        }
        
        // Add the unavailability
        $stmt = $this->pdo->prepare("
            INSERT INTO resource_availability (resource_id, day_of_week, start_time, end_time, is_available)
            VALUES (?, ?, ?, ?, 0)
        ");
        
        return $stmt->execute([$resourceId, $dayOfWeek, $startTime, $endTime]);
    }
    
    /**
     * Get resource availability for a specific day
     */
    public function getResourceAvailability($resourceId, $dayOfWeek = null) {
        $sql = "SELECT * FROM resource_availability WHERE resource_id = ?";
        $params = [$resourceId];
        
        if ($dayOfWeek !== null) {
            $sql .= " AND day_of_week = ?";
            $params[] = $dayOfWeek;
        }
        
        $sql .= " ORDER BY day_of_week, start_time";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Check if a resource is available at a given time
     */
    public function isResourceAvailable($resourceId, $startDateTime, $endDateTime) {
        // Convert to DateTime objects if strings
        if (is_string($startDateTime)) $startDateTime = new DateTime($startDateTime);
        if (is_string($endDateTime)) $endDateTime = new DateTime($endDateTime);
        
        $dayOfWeek = (int)$startDateTime->format('w'); // 0 (Sunday) to 6 (Saturday)
        $startTime = $startDateTime->format('H:i:s');
        $endTime = $endDateTime->format('H:i:s');
        
        // Check if there are any explicit unavailability entries that overlap with the requested time
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM resource_availability 
            WHERE resource_id = ? 
            AND day_of_week = ? 
            AND is_available = 0
            AND (
                (start_time <= ? AND end_time > ?) OR  -- Starts before and ends during
                (start_time < ? AND end_time >= ?) OR  -- Starts during and ends after
                (start_time >= ? AND end_time <= ?)    -- Completely within
            )
        ");
        
        $stmt->execute([
            $resourceId,
            $dayOfWeek,
            $startTime, $startTime,
            $endTime, $endTime,
            $startTime, $endTime
        ]);
        
        $result = $stmt->fetch();
        
        // If there are any matching unavailability entries, the resource is not available
        return $result['count'] === 0;
    }
    
    /**
     * Get all available resources for a given time slot
     */
    public function getAvailableResources($startDateTime, $endDateTime, $excludeResourceId = null) {
        $resources = $this->getAllResources(true);
        $availableResources = [];
        
        foreach ($resources as $resource) {
            // Skip excluded resource if specified
            if ($excludeResourceId !== null && $resource['id'] == $excludeResourceId) {
                continue;
            }
            
            if ($this->isResourceAvailable($resource['id'], $startDateTime, $endDateTime)) {
                $availableResources[] = $resource;
            }
        }
        
        return $availableResources;
    }
    
    /**
     * Get resources assigned to an appointment
     */
    public function getAppointmentResources($appointmentId) {
        $sql = "SELECT r.*, ar.notes, ar.status 
                FROM resources r
                JOIN appointment_resources ar ON r.id = ar.resource_id
                WHERE ar.appointment_id = ?";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$appointmentId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Assign resources to an appointment
     */
    public function assignResourcesToAppointment($appointmentId, array $resourceIds, $status = 'confirmed') {
        // First remove any existing assignments
        $this->pdo->prepare("DELETE FROM appointment_resources WHERE appointment_id = ?")
                 ->execute([$appointmentId]);
        
        // Add new assignments
        $stmt = $this->pdo->prepare("
            INSERT INTO appointment_resources (appointment_id, resource_id, status)
            VALUES (?, ?, ?)
        ");
        
        $this->pdo->beginTransaction();
        try {
            foreach ($resourceIds as $resourceId) {
                $stmt->execute([$appointmentId, $resourceId, $status]);
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error assigning resources: " . $e->getMessage());
            return false;
        }
    }
}
