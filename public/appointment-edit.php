<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

// Check if appointment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid appointment ID';
    header('Location: /appointments.php');
    exit();
}

$appointment_id = (int)$_GET['id'];

require_once 'config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

// Get services for dropdown
$services = $pdo->query("SELECT id, name FROM services WHERE is_active = 1 ORDER BY name")->fetchAll();

// Get resources for selection
$resources = $pdo->query("SELECT id, name, type_id FROM resources WHERE is_active = 1 ORDER BY name")->fetchAll();

// Get resource types for grouping
$resource_types = $pdo->query("SELECT id, name FROM resource_types ORDER BY name")->fetchAll();

try {
    // Get appointment details
    $query = "
        SELECT a.*, 
               GROUP_CONCAT(ar.resource_id) as resource_ids
        FROM appointments a
        LEFT JOIN appointment_resources ar ON a.id = ar.appointment_id
        WHERE a.id = ?
        GROUP BY a.id
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        throw new Exception('Appointment not found');
    }
    
    // Check permissions - only admin or the owner can edit
    if ($_SESSION['role'] !== 'admin' && $appointment['user_id'] !== $_SESSION['user_id']) {
        $_SESSION['error'] = 'You do not have permission to edit this appointment';
        header('Location: /appointments.php');
        exit();
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Invalid request';
            header('Location: /appointments.php');
            exit();
        }
        
        // Get form data
        $title = trim($_POST['title'] ?? '');
        $service_id = (int)($_POST['service_id'] ?? 0);
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $attendees = !empty($_POST['attendees']) ? (int)$_POST['attendees'] : null;
        $resource_ids = $_POST['resource_ids'] ?? [];
        
        // Validate required fields
        $errors = [];
        
        if (empty($title)) {
            $errors[] = 'Title is required';
        }
        
        if ($service_id <= 0) {
            $errors[] = 'Please select a service';
        }
        
        if (empty($start_time)) {
            $errors[] = 'Start time is required';
        } elseif (!strtotime($start_time)) {
            $errors[] = 'Invalid start time format';
        }
        
        if (empty($end_time)) {
            $errors[] = 'End time is required';
        } elseif (!strtotime($end_time)) {
            $errors[] = 'Invalid end time format';
        }
        
        if (strtotime($start_time) >= strtotime($end_time)) {
            $errors[] = 'End time must be after start time';
        }
        
        // If no validation errors, update the appointment
        if (empty($errors)) {
            $pdo->beginTransaction();
            
            try {
                // Update appointment
                $query = "
                    UPDATE appointments 
                    SET title = ?, 
                        service_id = ?, 
                        start_time = ?, 
                        end_time = ?, 
                        location = ?, 
                        description = ?, 
                        notes = ?, 
                        attendees = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    $title,
                    $service_id,
                    $start_time,
                    $end_time,
                    $location,
                    $description,
                    $notes,
                    $attendees,
                    $appointment_id
                ]);
                
                // Update resources
                $pdo->exec("DELETE FROM appointment_resources WHERE appointment_id = $appointment_id");
                
                if (!empty($resource_ids)) {
                    $stmt = $pdo->prepare("INSERT INTO appointment_resources (appointment_id, resource_id) VALUES (?, ?)");
                    foreach ($resource_ids as $resource_id) {
                        $stmt->execute([$appointment_id, (int)$resource_id]);
                    }
                }
                
                // Log the update
                $log_query = "
                    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
                    VALUES (?, 'update', 'appointment', ?, ?)
                ";
                $log_stmt = $pdo->prepare($log_query);
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    $appointment_id,
                    'Appointment updated'
                ]);
                
                $pdo->commit();
                
                $_SESSION['success'] = 'Appointment updated successfully';
                header('Location: /appointment.php?id=' . $appointment_id);
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }
    
    // Set default values from database if not submitting
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $title = $appointment['title'];
        $service_id = $appointment['service_id'];
        $start_time = date('Y-m-d\TH:i', strtotime($appointment['start_time']));
        $end_time = date('Y-m-d\TH:i', strtotime($appointment['end_time']));
        $location = $appointment['location'];
        $description = $appointment['description'];
        $notes = $appointment['notes'];
        $attendees = $appointment['attendees'];
        $selected_resources = !empty($appointment['resource_ids']) ? explode(',', $appointment['resource_ids']) : [];
    }
    
    // Set page title
    $page_title = 'Edit Appointment: ' . htmlspecialchars($appointment['title']);
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: /appointments.php');
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Appointment</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="/appointment.php?id=<?php echo $appointment_id; ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Appointment
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h5>Please fix the following errors:</h5>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" id="appointmentForm">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Appointment Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo htmlspecialchars($title); ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="service_id" class="form-label">Service <span class="text-danger">*</span></label>
                                <select class="form-select" id="service_id" name="service_id" required>
                                    <option value="">-- Select Service --</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?php echo $service['id']; ?>" 
                                            <?php echo $service_id == $service['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($service['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($location); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="start_time" name="start_time" 
                                       value="<?php echo htmlspecialchars($start_time); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="end_time" name="end_time" 
                                       value="<?php echo htmlspecialchars($end_time); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Internal Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($notes); ?></textarea>
                        <div class="form-text">These notes are only visible to staff and administrators.</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Resources -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Resources</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#resourcesModal">
                        <i class="bi bi-plus"></i> Add
                    </button>
                </div>
                <div class="card-body">
                    <div id="selectedResources">
                        <?php if (!empty($selected_resources)): ?>
                            <?php foreach ($selected_resources as $resource_id): 
                                $resource = array_filter($resources, function($r) use ($resource_id) {
                                    return $r['id'] == $resource_id;
                                });
                                $resource = reset($resource);
                                if ($resource): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                        <div>
                                            <i class="bi bi-box-seam me-2"></i>
                                            <?php echo htmlspecialchars($resource['name']); ?>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-resource" data-id="<?php echo $resource['id']; ?>">
                                            <i class="bi bi-x"></i>
                                        </button>
                                        <input type="hidden" name="resource_ids[]" value="<?php echo $resource['id']; ?>">
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted mb-0">No resources selected</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Additional Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Additional Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="attendees" class="form-label">Expected Attendees</label>
                        <input type="number" class="form-control" id="attendees" name="attendees" 
                               min="1" value="<?php echo $attendees; ?>">
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="notify_user" name="notify_user" checked>
                        <label class="form-check-label" for="notify_user">Notify requester of changes</label>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                    <a href="/appointment.php?id=<?php echo $appointment_id; ?>" class="btn btn-outline-secondary w-100">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Resources Modal -->
<div class="modal fade" id="resourcesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Resources</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php foreach ($resource_types as $type): ?>
                    <div class="mb-4">
                        <h6><?php echo htmlspecialchars($type['name']); ?></h6>
                        <div class="row row-cols-1 row-cols-md-2 g-3">
                            <?php 
                            $type_resources = array_filter($resources, function($r) use ($type) {
                                return $r['type_id'] == $type['id'];
                            });
                            
                            foreach ($type_resources as $resource): 
                                $is_selected = in_array($resource['id'], $selected_resources);
                            ?>
                                <div class="col">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="form-check">
                                                <input class="form-check-input resource-checkbox" type="checkbox" 
                                                       value="<?php echo $resource['id']; ?>" 
                                                       id="resource_<?php echo $resource['id']; ?>"
                                                       <?php echo $is_selected ? 'checked' : ''; ?>>
                                                <label class="form-check-label w-100" for="resource_<?php echo $resource['id']; ?>">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($resource['name']); ?></h6>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveResources">Save Selection</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize date/time pickers
    if (typeof flatpickr !== 'undefined') {
        flatpickr('#start_time', {
            enableTime: true,
            dateFormat: 'Y-m-d\\TH:i',
            minDate: 'today',
            time_24hr: false,
            onChange: function(selectedDates, dateStr, instance) {
                // Update end time min date
                const endTimePicker = document.querySelector('#end_time')._flatpickr;
                endTimePicker.set('minDate', dateStr);
                
                // If end time is before start time, update it
                if (endTimePicker.selectedDates[0] < selectedDates[0]) {
                    endTimePicker.setDate(new Date(selectedDates[0].getTime() + 60 * 60 * 1000));
                }
            }
        });
        
        flatpickr('#end_time', {
            enableTime: true,
            dateFormat: 'Y-m-d\\TH:i',
            minDate: 'today',
            time_24hr: false
        });
    }
    
    // Handle resource selection
    const resourcesModal = document.getElementById('resourcesModal');
    if (resourcesModal) {
        const modal = new bootstrap.Modal(resourcesModal);
        const saveBtn = document.getElementById('saveResources');
        const selectedResourcesDiv = document.getElementById('selectedResources');
        
        // Save selected resources
        saveBtn.addEventListener('click', function() {
            const selected = [];
            const checkboxes = resourcesModal.querySelectorAll('.resource-checkbox:checked');n            
            checkboxes.forEach(checkbox => {
                selected.push(parseInt(checkbox.value));
            });
            
            // Update hidden inputs
            const hiddenInputs = document.querySelectorAll('input[name="resource_ids[]"]');
            hiddenInputs.forEach(input => input.remove());
            
            // Update UI
            selectedResourcesDiv.innerHTML = '';
            
            if (selected.length === 0) {
                selectedResourcesDiv.innerHTML = '<p class="text-muted mb-0">No resources selected</p>';
                return;
            }
            
            selected.forEach(id => {
                const resource = <?php echo json_encode(array_column($resources, null, 'id')); ?>[id];
                if (resource) {
                    const div = document.createElement('div');
                    div.className = 'd-flex justify-content-between align-items-center mb-2 p-2 border rounded';
                    div.innerHTML = `
                        <div>
                            <i class="bi bi-box-seam me-2"></i>
                            ${resource.name}
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-resource" data-id="${id}">
                            <i class="bi bi-x"></i>
                        </button>
                        <input type="hidden" name="resource_ids[]" value="${id}">
                    `;
                    selectedResourcesDiv.appendChild(div);
                }
            });
            
            modal.hide();
        });
        
        // Handle remove resource
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-resource')) {
                const button = e.target.closest('.remove-resource');
                const resourceId = parseInt(button.dataset.id);
                const resourceDiv = button.closest('.border');
                
                // Remove from UI
                resourceDiv.remove();
                
                // If no resources left, show message
                if (!selectedResourcesDiv.querySelector('.border')) {
                    selectedResourcesDiv.innerHTML = '<p class="text-muted mb-0">No resources selected</p>';
                }
                
                // Uncheck in modal
                const checkbox = resourcesModal.querySelector(`.resource-checkbox[value="${resourceId}"]`);
                if (checkbox) {
                    checkbox.checked = false;
                }
            }
        });
    }
    
    // Form validation
    const form = document.getElementById('appointmentForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const startTime = new Date(document.getElementById('start_time').value);
            const endTime = new Date(document.getElementById('end_time').value);
            
            if (startTime >= endTime) {
                e.preventDefault();
                alert('End time must be after start time');
                return false;
            }
            
            return true;
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
