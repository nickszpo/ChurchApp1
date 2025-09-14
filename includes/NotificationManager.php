<?php

class NotificationManager {
    private $pdo;
    private $appointmentManager;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->appointmentManager = new AppointmentManager($pdo);
    }
    
    /**
     * Create a new notification
     */
    public function createNotification($userId, $title, $message, $type = 'system', $relatedId = null) {
        $sql = "INSERT INTO notifications (user_id, title, message, type, related_id) 
                VALUES (:user_id, :title, :message, :type, :related_id)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':title' => $title,
            ':message' => $message,
            ':type' => $type,
            ':related_id' => $relatedId
        ]);
    }
    
    /**
     * Get notifications for a user
     */
    public function getUserNotifications($userId, $limit = 10, $unreadOnly = false) {
        $sql = "SELECT * FROM notifications WHERE user_id = :user_id";
        $params = [':user_id' => $userId];
        
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT :limit";
        }
        
        $stmt = $this->pdo->prepare($sql);
        
        if ($limit > 0) {
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        }
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId = null) {
        $sql = "UPDATE notifications SET is_read = 1 WHERE id = :id";
        $params = [':id' => $notificationId];
        
        if ($userId !== null) {
            $sql .= " AND user_id = :user_id";
            $params[':user_id'] = $userId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($userId) {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }
    
    /**
     * Delete a notification
     */
    public function deleteNotification($notificationId, $userId = null) {
        $sql = "DELETE FROM notifications WHERE id = :id";
        $params = [':id' => $notificationId];
        
        if ($userId !== null) {
            $sql .= " AND user_id = :user_id";
            $params[':user_id'] = $userId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Send appointment confirmation email
     */
    public function sendAppointmentConfirmation($appointmentId) {
        $appointment = $this->appointmentManager->getAppointmentById($appointmentId);
        if (!$appointment) {
            return false;
        }
        
        // Get user details
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$appointment['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user || empty($user['email'])) {
            return false;
        }
        
        $to = $user['email'];
        $subject = "Appointment Confirmation: " . $appointment['title'];
        
        // Format the date and time
        $startDate = new DateTime($appointment['start_time']);
        $endDate = new DateTime($appointment['end_time']);
        $formattedDate = $startDate->format('l, F j, Y');
        $formattedTime = $startDate->format('g:i A') . ' - ' . $endDate->format('g:i A');
        
        // Get resources if any
        $resources = $this->appointmentManager->getAppointmentResources($appointmentId);
        $resourceList = '';
        if (!empty($resources)) {
            $resourceList = "\n\nResources:\n";
            foreach ($resources as $resource) {
                $resourceList .= "- {$resource['name']}";
                if (!empty($resource['notes'])) {
                    $resourceList .= " ({$resource['notes']})";
                }
                $resourceList .= "\n";
            }
        }
        
        $message = "
        Hello {$user['full_name']},
        
        Your appointment has been confirmed with the following details:
        
        Reference: {$appointment['reference_number']}
        Service: {$appointment['service_name']}
        Date: $formattedDate
        Time: $formattedTime
        Status: " . ucfirst($appointment['status']) . "
        
        $resourceList
        
        You can view or manage your appointment by logging into your account.
        
        Thank you for choosing our services.
        
        Best regards,
        Church App Team
        ";
        
        // For now, we'll just log the email. In a production environment, you would use a mailer library.
        $this->logEmail($to, $subject, $message);
        
        // Create a notification for the user
        $this->createNotification(
            $user['id'],
            'Appointment Confirmed',
            "Your appointment for {$appointment['title']} on $formattedDate has been confirmed.",
            'appointment',
            $appointmentId
        );
        
        return true;
    }
    
    /**
     * Send appointment reminder
     */
    public function sendAppointmentReminder($appointmentId, $hoursBefore = 24) {
        $appointment = $this->appointmentManager->getAppointmentById($appointmentId);
        if (!$appointment) {
            return false;
        }
        
        $appointmentTime = new DateTime($appointment['start_time']);
        $now = new DateTime();
        $diffHours = ($appointmentTime->getTimestamp() - $now->getTimestamp()) / 3600;
        
        // Only send reminder if within the specified hours before
        if ($diffHours > $hoursBefore || $diffHours <= 0) {
            return false;
        }
        
        // Get user details
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$appointment['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user || empty($user['email'])) {
            return false;
        }
        
        $to = $user['email'];
        $subject = "Reminder: Upcoming Appointment - " . $appointment['title'];
        
        // Format the date and time
        $startDate = new DateTime($appointment['start_time']);
        $endDate = new DateTime($appointment['end_time']);
        $formattedDate = $startDate->format('l, F j, Y');
        $formattedTime = $startDate->format('g:i A') . ' - ' . $endDate->format('g:i A');
        
        $message = "
        Hello {$user['full_name']},
        
        This is a friendly reminder about your upcoming appointment:
        
        Service: {$appointment['service_name']}
        Date: $formattedDate
        Time: $formattedTime
        
        We look forward to seeing you!
        
        If you need to reschedule or cancel, please log in to your account.
        
        Best regards,
        Church App Team
        ";
        
        // For now, we'll just log the email. In a production environment, you would use a mailer library.
        $this->logEmail($to, $subject, $message);
        
        // Create a notification for the user
        $this->createNotification(
            $user['id'],
            'Appointment Reminder',
            "Reminder: You have an appointment for {$appointment['title']} tomorrow at " . $startDate->format('g:i A'),
            'reminder',
            $appointmentId
        );
        
        return true;
    }
    
    /**
     * Send appointment status update
     */
    public function sendAppointmentStatusUpdate($appointmentId, $oldStatus, $newStatus) {
        $appointment = $this->appointmentManager->getAppointmentById($appointmentId);
        if (!$appointment) {
            return false;
        }
        
        // Get user details
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$appointment['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user || empty($user['email'])) {
            return false;
        }
        
        $to = $user['email'];
        $subject = "Appointment Update: " . $appointment['title'] . " - " . ucfirst($newStatus);
        
        // Format the date and time
        $startDate = new DateTime($appointment['start_time']);
        $formattedDate = $startDate->format('l, F j, Y');
        $formattedTime = $startDate->format('g:i A');
        
        $message = "
        Hello {$user['full_name']},
        
        The status of your appointment has been updated from " . ucfirst($oldStatus) . " to " . ucfirst($newStatus) . ".
        
        Appointment Details:
        
        Reference: {$appointment['reference_number']}
        Service: {$appointment['service_name']}
        Date: $formattedDate
        Time: $formattedTime
        New Status: " . ucfirst($newStatus) . "
        
        ";
        
        if ($newStatus === 'cancelled') {
            $message .= "We're sorry to see you had to cancel. If this was a mistake, please contact us to reschedule.\n\n";
        } elseif ($newStatus === 'confirmed') {
            $message .= "Your appointment has been confirmed. We look forward to seeing you!\n\n";
        } elseif ($newStatus === 'completed') {
            $message .= "Thank you for your appointment. We hope to see you again soon!\n\n";
        }
        
        $message .= "You can view or manage your appointment by logging into your account.\n\n";
        $message .= "Best regards,\nChurch App Team\n";
        
        // For now, we'll just log the email. In a production environment, you would use a mailer library.
        $this->logEmail($to, $subject, $message);
        
        // Create a notification for the user
        $this->createNotification(
            $user['id'],
            'Appointment ' . ucfirst($newStatus),
            "Your appointment for {$appointment['title']} has been updated to: " . ucfirst($newStatus),
            'appointment',
            $appointmentId
        );
        
        return true;
    }
    
    /**
     * Log email (placeholder for actual email sending)
     */
    private function logEmail($to, $subject, $message) {
        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/email_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        
        $logEntry = "[$timestamp] To: $to\nSubject: $subject\n" . str_repeat('-', 50) . "\n$message\n\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Process scheduled notifications (to be called by a cron job)
     */
    public function processScheduledNotifications() {
        // Get appointments starting in the next 24 hours that haven't had reminders sent
        $reminderTime = (new DateTime())->modify('+24 hours')->format('Y-m-d H:i:s');
        
        $sql = "
            SELECT a.id 
            FROM appointments a
            LEFT JOIN notifications n ON n.related_id = a.id AND n.type = 'reminder' AND n.title LIKE 'Appointment Reminder%'
            WHERE a.start_time <= :reminder_time 
            AND a.start_time > datetime('now')
            AND a.status = 'confirmed'
            AND n.id IS NULL
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':reminder_time' => $reminderTime]);
        $appointments = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $sent = 0;
        foreach ($appointments as $appointmentId) {
            if ($this->sendAppointmentReminder($appointmentId, 24)) {
                $sent++;
            }
        }
        
        return [
            'total_appointments' => count($appointments),
            'reminders_sent' => $sent
        ];
    }
}
