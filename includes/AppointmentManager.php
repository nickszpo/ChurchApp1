<?php

class AppointmentManager {
    private $pdo;
    private $resourceManager;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->resourceManager = new ResourceManager($pdo);
    }
    
    /**
     * Create a new appointment
     */
    public function createAppointment($data) {
        $this->pdo->beginTransaction();
        
        try {
            // Generate a unique reference number
            $referenceNumber = 'APP-' . strtoupper(uniqid());
            
            // Calculate end time if not provided
            if (empty($data['end_time'])) {
                $startDateTime = new DateTime($data['start_time']);
                $durationMinutes = $data['duration_minutes'] ?? 60; // Default to 60 minutes
                $endDateTime = (clone $startDateTime)->modify("+{$durationMinutes} minutes");
                $endTime = $endDateTime->format('Y-m-d H:i:s');
            } else {
                $endTime = $data['end_time'];
            }
            
            // Insert the appointment
            $sql = "INSERT INTO appointments (
                reference_number, user_id, service_id, title, description, 
                start_time, end_time, status, is_recurring, recurrence_pattern, recurrence_end_date
            ) VALUES (
                :reference_number, :user_id, :service_id, :title, :description,
                :start_time, :end_time, :status, :is_recurring, :recurrence_pattern, :recurrence_end_date
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':reference_number' => $referenceNumber,
                ':user_id' => $data['user_id'],
                ':service_id' => $data['service_id'],
                ':title' => $data['title'],
                ':description' => $data['description'] ?? null,
                ':start_time' => $data['start_time'],
                ':end_time' => $endTime,
                ':status' => $data['status'] ?? 'pending',
                ':is_recurring' => $data['is_recurring'] ?? 0,
                ':recurrence_pattern' => $data['recurrence_pattern'] ?? null,
                ':recurrence_end_date' => $data['recurrence_end_date'] ?? null
            ]);
            
            $appointmentId = $this->pdo->lastInsertId();
            
            // Handle recurring appointments
            if (($data['is_recurring'] ?? false) && !empty($data['recurrence_pattern'])) {
                $this->createRecurringAppointments($appointmentId, $data);
            }
            
            // Assign resources if any
            if (!empty($data['resource_ids'])) {
                $this->resourceManager->assignResourcesToAppointment(
                    $appointmentId, 
                    $data['resource_ids'],
                    $data['status'] ?? 'confirmed'
                );
            }
            
            $this->pdo->commit();
            
            return [
                'id' => $appointmentId,
                'reference_number' => $referenceNumber
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error creating appointment: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update an existing appointment
     */
    public function updateAppointment($appointmentId, $data) {
        $this->pdo->beginTransaction();
        
        try {
            // Get existing appointment
            $appointment = $this->getAppointmentById($appointmentId);
            if (!$appointment) {
                throw new Exception("Appointment not found");
            }
            
            // Calculate end time if duration is provided
            if (isset($data['duration_minutes']) && !empty($data['start_time'])) {
                $startDateTime = new DateTime($data['start_time']);
                $endDateTime = (clone $startDateTime)->modify("+{$data['duration_minutes']} minutes");
                $data['end_time'] = $endDateTime->format('Y-m-d H:i:s');
            }
            
            // Build update query
            $updates = [];
            $params = [':id' => $appointmentId];
            
            $fields = [
                'service_id', 'title', 'description', 'start_time', 'end_time',
                'status', 'is_recurring', 'recurrence_pattern', 'recurrence_end_date'
            ];
            
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                throw new Exception("No fields to update");
            }
            
            $sql = "UPDATE appointments SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            // Update resources if provided
            if (isset($data['resource_ids'])) {
                $this->resourceManager->assignResourcesToAppointment(
                    $appointmentId,
                    $data['resource_ids'],
                    $data['status'] ?? $appointment['status']
                );
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error updating appointment: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get appointment by ID
     */
    public function getAppointmentById($id, $includeResources = true) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, s.name as service_name, u.full_name as requester_name
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            JOIN users u ON a.user_id = u.id
            WHERE a.id = ?
        ");
        
        $stmt->execute([$id]);
        $appointment = $stmt->fetch();
        
        if ($appointment && $includeResources) {
            $appointment['resources'] = $this->resourceManager->getAppointmentResources($id);
        }
        
        return $appointment ?: null;
    }
    
    /**
     * Get appointment by reference number
     */
    public function getAppointmentByReference($referenceNumber, $includeResources = true) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, s.name as service_name, u.full_name as requester_name
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            JOIN users u ON a.user_id = u.id
            WHERE a.reference_number = ?
        ");
        
        $stmt->execute([$referenceNumber]);
        $appointment = $stmt->fetch();
        
        if ($appointment && $includeResources) {
            $appointment['resources'] = $this->resourceManager->getAppointmentResources($appointment['id']);
        }
        
        return $appointment ?: null;
    }
    
    /**
     * Get appointments with filters
     */
    public function getAppointments($filters = []) {
        $where = [];
        $params = [];
        
        // Apply filters
        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = 'a.status = :status';
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['service_id'])) {
            $where[] = 'a.service_id = :service_id';
            $params[':service_id'] = $filters['service_id'];
        }
        
        if (!empty($filters['start_date'])) {
            $where[] = 'DATE(a.start_time) >= :start_date';
            $params[':start_date'] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where[] = 'DATE(a.end_time) <= :end_date';
            $params[':end_date'] = $filters['end_date'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(a.reference_number LIKE :search OR a.title LIKE :search OR a.description LIKE :search)';
            $params[':search'] = "%{$filters['search']}%";
        }
        
        // Build the query
        $sql = "
            SELECT a.*, s.name as service_name, u.full_name as requester_name,
                   (SELECT COUNT(*) FROM appointment_resources ar WHERE ar.appointment_id = a.id) as resource_count
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            JOIN users u ON a.user_id = u.id
        ";
        
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        // Add sorting
        $orderBy = $filters['order_by'] ?? 'a.start_time';
        $orderDir = isset($filters['order_dir']) && strtoupper($filters['order_dir']) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY $orderBy $orderDir";
        
        // Add pagination if needed
        if (isset($filters['limit'])) {
            $sql .= ' LIMIT :limit';
            $params[':limit'] = (int)$filters['limit'];
            
            if (isset($filters['offset'])) {
                $sql .= ' OFFSET :offset';
                $params[':offset'] = (int)$filters['offset'];
            }
        }
        
        $stmt = $this->pdo->prepare($sql);
        
        // Bind parameters with proper types
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        
        $stmt->execute();
        $appointments = $stmt->fetchAll();
        
        // Include resources if requested
        if (!empty($filters['include_resources'])) {
            foreach ($appointments as &$appointment) {
                $appointment['resources'] = $this->resourceManager->getAppointmentResources($appointment['id']);
            }
        }
        
        return $appointments;
    }
    
    /**
     * Get appointments for a calendar view
     */
    public function getCalendarAppointments($start, $end, $filters = []) {
        $sql = "
            SELECT 
                a.id, 
                a.reference_number,
                a.title,
                a.description,
                a.start_time as start,
                a.end_time as end,
                a.status,
                s.name as service_name,
                r.color_code as backgroundColor,
                r.id as resource_id,
                r.name as resource_name
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            LEFT JOIN appointment_resources ar ON a.id = ar.appointment_id
            LEFT JOIN resources r ON ar.resource_id = r.id
            WHERE 
                ((a.start_time BETWEEN :start AND :end) OR 
                 (a.end_time BETWEEN :start AND :end) OR
                 (a.start_time <= :start AND a.end_time >= :end))
        ";
        
        $params = [
            ':start' => $start,
            ':end' => $end
        ];
        
        // Apply additional filters
        if (!empty($filters['user_id'])) {
            $sql .= ' AND a.user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = [];
                foreach ($filters['status'] as $i => $status) {
                    $param = ":status_$i";
                    $placeholders[] = $param;
                    $params[$param] = $status;
                }
                $sql .= ' AND a.status IN (' . implode(', ', $placeholders) . ')';
            } else {
                $sql .= ' AND a.status = :status';
                $params[':status'] = $filters['status'];
            }
        }
        
        if (!empty($filters['resource_id'])) {
            $sql .= ' AND r.id = :resource_id';
            $params[':resource_id'] = $filters['resource_id'];
        }
        
        $sql .= ' ORDER BY a.start_time';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $events = [];
        while ($row = $stmt->fetch()) {
            $eventId = $row['id'];
            
            if (!isset($events[$eventId])) {
                $events[$eventId] = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'start' => $row['start'],
                    'end' => $row['end'],
                    'status' => $row['status'],
                    'service' => $row['service_name'],
                    'reference' => $row['reference_number'],
                    'description' => $row['description'],
                    'resources' => [],
                    'backgroundColor' => $row['backgroundColor']
                ];
            }
            
            if (!empty($row['resource_id'])) {
                $events[$eventId]['resources'][] = [
                    'id' => $row['resource_id'],
                    'name' => $row['resource_name']
                ];
            }
        }
        
        return array_values($events);
    }
    
    /**
     * Delete an appointment
     */
    public function deleteAppointment($id) {
        $this->pdo->beginTransaction();
        
        try {
            // First delete related records
            $this->pdo->prepare("DELETE FROM appointment_resources WHERE appointment_id = ?")->execute([$id]);
            
            // Then delete the appointment
            $stmt = $this->pdo->prepare("DELETE FROM appointments WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            $this->pdo->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error deleting appointment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update appointment status
     */
    public function updateAppointmentStatus($id, $status) {
        $allowedStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
        
        if (!in_array($status, $allowedStatuses)) {
            throw new Exception("Invalid status");
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE appointments 
            SET status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        
        return $stmt->execute([$status, $id]);
    }
    
    /**
     * Check for scheduling conflicts
     */
    public function checkForConflicts($startTime, $endTime, $excludeAppointmentId = null, $resourceIds = []) {
        $conflicts = [];
        
        // Check for time overlap with other appointments
        $sql = "
            SELECT a.*, u.full_name as requester_name, s.name as service_name
            FROM appointments a
            JOIN users u ON a.user_id = u.id
            JOIN services s ON a.service_id = s.id
            WHERE 
                a.status != 'cancelled'
                AND (
                    (a.start_time < :end_time AND a.end_time > :start_time)
                )
        ";
        
        $params = [
            ':start_time' => $startTime,
            ':end_time' => $endTime
        ];
        
        if ($excludeAppointmentId) {
            $sql .= ' AND a.id != :exclude_id';
            $params[':exclude_id'] = $excludeAppointmentId;
        }
        
        // If resource IDs are provided, check for conflicts with those resources
        if (!empty($resourceIds)) {
            $placeholders = [];
            foreach ($resourceIds as $i => $resourceId) {
                $param = ":resource_$i";
                $placeholders[] = $param;
                $params[$param] = $resourceId;
            }
            
            $sql .= " 
                AND a.id IN (
                    SELECT ar.appointment_id 
                    FROM appointment_resources ar 
                    WHERE ar.resource_id IN (" . implode(', ', $placeholders) . ")
                )
            ";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        while ($conflict = $stmt->fetch()) {
            // Get resources for the conflicting appointment
            $conflict['resources'] = $this->resourceManager->getAppointmentResources($conflict['id']);
            $conflicts[] = $conflict;
        }
        
        return $conflicts;
    }
    
    /**
     * Create recurring appointments based on a pattern
     */
    private function createRecurringAppointments($parentId, $data) {
        $parent = $this->getAppointmentById($parentId, false);
        if (!$parent) {
            throw new Exception("Parent appointment not found");
        }
        
        $pattern = $data['recurrence_pattern'];
        $endDate = new DateTime($data['recurrence_end_date']);
        $currentDate = new DateTime($data['start_time']);
        $duration = strtotime($parent['end_time']) - strtotime($parent['start_time']);
        
        // Parse the pattern (e.g., "WEEKLY:MO,WE,FR" or "MONTHLY:15")
        list($frequency, $details) = explode(':', $pattern, 2);
        $frequency = strtoupper(trim($frequency));
        
        $occurrences = [];
        $currentDate->modify('+1 day'); // Start from the next day
        
        switch ($frequency) {
            case 'DAILY':
                $interval = new DateInterval('P1D');
                break;
                
            case 'WEEKLY':
                $days = array_map('strtoupper', array_map('trim', explode(',', $details)));
                $dayMap = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];
                $dayNumbers = [];
                
                foreach ($days as $day) {
                    if (isset($dayMap[$day])) {
                        $dayNumbers[] = $dayMap[$day];
                    }
                }
                
                if (empty($dayNumbers)) {
                    return; // No valid days specified
                }
                
                // Find the next occurrence of each day in the week
                $currentWeek = (int)$currentDate->format('W');
                
                while ($currentDate <= $endDate) {
                    $currentDay = (int)$currentDate->format('w'); // 0 (Sun) to 6 (Sat)
                    
                    if (in_array($currentDay, $dayNumbers)) {
                        $occurrences[] = clone $currentDate;
                    }
                    
                    $currentDate->modify('+1 day');
                    
                    // If we've moved to a new week, check if we've passed the end date
                    if ((int)$currentDate->format('W') !== $currentWeek) {
                        $currentWeek = (int)$currentDate->format('W');
                        
                        // If the next week would be after the end date, stop
                        $nextWeek = (clone $currentDate)->modify('+1 week');
                        if ($nextWeek > $endDate) {
                            break;
                        }
                    }
                }
                
                break;
                
            case 'MONTHLY':
                $dayOfMonth = (int)$details;
                
                while ($currentDate <= $endDate) {
                    $currentDate->setDate(
                        $currentDate->format('Y'),
                        $currentDate->format('m'),
                        min($dayOfMonth, $currentDate->format('t')) // Handle months with fewer days
                    );
                    
                    if ($currentDate <= $endDate) {
                        $occurrences[] = clone $currentDate;
                    }
                    
                    $currentDate->modify('first day of next month');
                }
                
                break;
                
            default:
                throw new Exception("Unsupported recurrence pattern: $frequency");
        }
        
        // If we have a simple interval (like daily), generate the occurrences
        if (empty($occurrences) && isset($interval)) {
            while ($currentDate <= $endDate) {
                $occurrences[] = clone $currentDate;
                $currentDate->add($interval);
            }
        }
        
        // Create the recurring appointments
        foreach ($occurrences as $occurrence) {
            $startTime = clone $occurrence;
            $startTime->setTime(
                (new DateTime($parent['start_time']))->format('H'),
                (new DateTime($parent['start_time']))->format('i'),
                (new DateTime($parent['start_time']))->format('s')
            );
            
            $endTime = (clone $startTime)->modify("+$duration seconds");
            
            // Skip if this occurrence is in the past
            if ($startTime < new DateTime()) {
                continue;
            }
            
            // Create the appointment
            $appointmentData = [
                'user_id' => $parent['user_id'],
                'service_id' => $parent['service_id'],
                'title' => $parent['title'],
                'description' => $parent['description'],
                'start_time' => $startTime->format('Y-m-d H:i:s'),
                'end_time' => $endTime->format('Y-m-d H:i:s'),
                'status' => $parent['status'],
                'is_recurring' => 0, // Child appointments are not recurring themselves
                'parent_appointment_id' => $parentId
            ];
            
            $this->createAppointment($appointmentData);
        }
    }
}
