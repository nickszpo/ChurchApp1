<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/ResourceManager.php';

$db = Database::getInstance();
$pdo = $db->getConnection();
$resourceManager = new ResourceManager($pdo);

$errors = [];
$success = '';

// Get list of services, priests, and resources
$services = [];
$priests = [];
$resources = [];
try {
    // Get active services
    $stmt = $pdo->query("SELECT id, name, description FROM services ORDER BY name");
    $services = $stmt->fetchAll();
    
    // Get active priests with explicit column selection
    $sql = "SELECT id, full_name FROM users WHERE role = 'priest' AND is_active = 1 ORDER BY full_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $priests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
    // Get active resources
    $resources = $resourceManager->getAllResources();
} catch (PDOException $e) {
    $errors[] = "Error loading data. Please try again later.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        // Get and validate form data
        $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
        $priest_id = !empty($_POST['priest_id']) ? (int)$_POST['priest_id'] : null;
        $appointment_date = trim($_POST['appointment_date'] ?? '');
        $appointment_time = trim($_POST['appointment_time'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $status = trim($_POST['status'] ?? 'pending');
        $resource_ids = isset($_POST['resource_ids']) ? $_POST['resource_ids'] : [];
        
        // Combine date and time for start_time and end_time
        if (!empty($appointment_date) && !empty($start_time)) {
            $start_time = $appointment_date . ' ' . $start_time . ':00';
        }
        if (!empty($appointment_date) && !empty($end_time)) {
            $end_time = $appointment_date . ' ' . $end_time . ':00';
        }
        
        // Convert string resource IDs to integers and filter out any invalid values
        $resource_ids = array_filter(array_map('intval', (array)$resource_ids), function($id) {
            return $id > 0;
        });
        
        // Validation
        $errors = [];
        
        if (empty($service_id)) {
            $errors[] = 'Please select a service';
        }
        
        if (empty($appointment_date)) {
            $errors[] = 'Please select an appointment date';
        }
        
        if (empty($start_time)) {
            $errors[] = 'Please select a start time';
        }
        
        if (empty($end_time)) {
            $errors[] = 'Please select an end time';
        } elseif (strtotime($end_time) <= strtotime($start_time)) {
            $errors[] = 'End time must be after start time';
        }
        
        if (empty($title)) {
            $errors[] = 'Please enter an appointment title';
        }
        
        if (empty($full_name)) {
            $errors[] = 'Please enter your full name';
        }
        
        if (empty($phone)) {
            $errors[] = 'Please enter a phone number';
        } elseif (!preg_match('/^[0-9\-\+\(\)\s]{10,20}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number';
        }
        
        if (empty($email)) {
            $errors[] = 'Please enter an email address';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        // If no validation errors, save the appointment
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Generate a unique reference number
                $referenceNumber = 'APP-' . strtoupper(uniqid());
                
                // Check which columns exist in the appointments table
                // $stmt = $pdo->query("PRAGMA table_info(appointments)"); // Commented out for PostgreSQL compatibility
                $columns = $stmt->fetchAll();
                $existingColumns = array_column($columns, 'name');
                
                // Build dynamic INSERT statement based on existing columns
                $insertColumns = [];
                $insertValues = [];
                $placeholders = [];
                
                // Always include these basic columns
                if (in_array('service_id', $existingColumns)) {
                    $insertColumns[] = 'service_id';
                    $insertValues[] = $service_id;
                    $placeholders[] = '?';
                }
                
                if (in_array('user_id', $existingColumns)) {
                    $insertColumns[] = 'user_id';
                    $insertValues[] = $_SESSION['user_id'];
                    $placeholders[] = '?';
                }
                
                if (in_array('priest_id', $existingColumns)) {
                    $insertColumns[] = 'priest_id';
                    $insertValues[] = $priest_id;
                    $placeholders[] = '?';
                }
                
                if (in_array('title', $existingColumns)) {
                    $insertColumns[] = 'title';
                    $insertValues[] = $title;
                    $placeholders[] = '?';
                }
                
                if (in_array('description', $existingColumns)) {
                    $insertColumns[] = 'description';
                    $insertValues[] = $description;
                    $placeholders[] = '?';
                }
                
                if (in_array('start_time', $existingColumns)) {
                    $insertColumns[] = 'start_time';
                    $insertValues[] = $start_time;
                    $placeholders[] = '?';
                }
                
                if (in_array('end_time', $existingColumns)) {
                    $insertColumns[] = 'end_time';
                    $insertValues[] = $end_time;
                    $placeholders[] = '?';
                }
                
                if (in_array('contact_name', $existingColumns)) {
                    $insertColumns[] = 'contact_name';
                    $insertValues[] = $full_name;
                    $placeholders[] = '?';
                }
                
                if (in_array('contact_phone', $existingColumns)) {
                    $insertColumns[] = 'contact_phone';
                    $insertValues[] = $phone;
                    $placeholders[] = '?';
                }
                
                if (in_array('contact_email', $existingColumns)) {
                    $insertColumns[] = 'contact_email';
                    $insertValues[] = $email;
                    $placeholders[] = '?';
                }
                
                if (in_array('status', $existingColumns)) {
                    $insertColumns[] = 'status';
                    $insertValues[] = $status;
                    $placeholders[] = '?';
                }
                
                if (in_array('reference_number', $existingColumns)) {
                    $insertColumns[] = 'reference_number';
                    $insertValues[] = $referenceNumber;
                    $placeholders[] = '?';
                }
                
                // Add timestamps if they exist
                if (in_array('created_at', $existingColumns)) {
                    $insertColumns[] = 'created_at';
                    $insertValues[] = date('Y-m-d H:i:s');
                    $placeholders[] = '?';
                }
                
                if (in_array('updated_at', $existingColumns)) {
                    $insertColumns[] = 'updated_at';
                    $insertValues[] = date('Y-m-d H:i:s');
                    $placeholders[] = '?';
                }
                
                // Build and execute the dynamic INSERT statement
                $sql = "INSERT INTO appointments (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($insertValues);
                
                $appointment_id = $pdo->lastInsertId();
                
                // Assign resources if any selected
                if (!empty($resource_ids)) {
                    $stmt = $pdo->prepare("INSERT INTO appointment_resources (appointment_id, resource_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
                    foreach ($resource_ids as $resource_id) {
                        $stmt->execute([$appointment_id, $resource_id]);
                    }
                }
                
                $pdo->commit();
                
                $_SESSION['success'] = 'Appointment created successfully!';
                header('Location: /ChurchApp1/appointment.php?id=' . $appointment_id);
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = 'Error creating appointment: ' . $e->getMessage();
                error_log('Appointment creation error: ' . $e->getMessage());
                error_log('Error details: ' . print_r($e, true));
                error_log('SQL State: ' . $e->getCode());
                error_log('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            }
        }
    }
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'New Appointment';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">New Appointment</h1>
                <a href="/appointments.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Appointments
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="mb-3">
                            <label for="service_id" class="form-label">Service <span class="text-danger">*</span></label>
                            <select class="form-select" id="service_id" name="service_id" required>
                                <option value="">-- Select a service --</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?php echo $service['id']; ?>" <?php echo (isset($_POST['service_id']) && $_POST['service_id'] == $service['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(strtoupper($service['name'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a service.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="priest_id" class="form-label">Assigned Priest (Optional)</label>
                            <select class="form-select" id="priest_id" name="priest_id" style="min-width: 250px;">
                                <option value="">-- Select a priest --</option>
                                <?php 
                                if (!empty($priests) && is_array($priests)): 
                                    foreach ($priests as $priest): 
                                        if (!isset($priest['id'], $priest['full_name'])) continue;
                                        $selected = (isset($_POST['priest_id']) && $_POST['priest_id'] == $priest['id']) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo (int)$priest['id']; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($priest['full_name']); ?>
                                        </option>
                                        <?php
                                    endforeach;
                                else:
                                    ?>
                                    <option value="" disabled>No priests available</option>
                                    <?php
                                endif; 
                                ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="appointment_date" class="form-label">Appointment Date <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                                    <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                           value="<?php echo htmlspecialchars($_POST['appointment_date'] ?? date('Y-m-d')); ?>" required>
                                </div>
                                <div class="invalid-feedback">Please select an appointment date.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="appointment_time" class="form-label">Appointment Time <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                    <input type="time" class="form-control" id="appointment_time" name="appointment_time" 
                                           value="<?php echo htmlspecialchars($_POST['appointment_time'] ?? ''); ?>" required>
                                </div>
                                <div class="invalid-feedback">Please select an appointment time.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-clock-fill"></i></span>
                                    <input type="time" class="form-control" id="start_time" name="start_time" 
                                           value="<?php echo htmlspecialchars($_POST['start_time'] ?? ''); ?>" required>
                                </div>
                                <div class="invalid-feedback">Please select a start time.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-clock-fill"></i></span>
                                    <input type="time" class="form-control" id="end_time" name="end_time" 
                                           value="<?php echo htmlspecialchars($_POST['end_time'] ?? ''); ?>" required>
                                </div>
                                <div class="invalid-feedback">Please select an end time.</div>
                            </div>
                        </div>
                        
                        <!-- Calendar Widget -->
                        <div class="mb-4">
                            <label class="form-label">Select Date & Time</label>
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div id="calendar-widget"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="time-slots">
                                                <h6>Available Time Slots</h6>
                                                <div class="time-grid" id="time-slots">
                                                    <!-- Time slots will be populated by JavaScript -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Appointment Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                            <div class="invalid-feedback">Please enter a title for the appointment.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <h5 class="mt-4 mb-3">Contact Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please enter your full name.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please enter a valid phone number.</div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                        
                        <h5 class="mb-3">Required Resources</h5>
                        <p class="text-muted">Select a service to see required resources</p>
                        
                        <div class="mb-4">
                            <label class="form-label">Status</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="status_pending" value="pending" 
                                       <?php echo (!isset($_POST['status']) || (isset($_POST['status']) && $_POST['status'] === 'pending')) ? 'checked' : ''; ?> required>
                                <label class="form-check-label" for="status_pending">
                                    Pending
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="status_confirmed" value="confirmed" 
                                       <?php echo (isset($_POST['status']) && $_POST['status'] === 'confirmed') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="status_confirmed">
                                    Confirmed
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="status_cancelled" value="cancelled" 
                                       <?php echo (isset($_POST['status']) && $_POST['status'] === 'cancelled') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="status_cancelled">
                                    Cancelled
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="status_completed" value="completed" 
                                       <?php echo (isset($_POST['status']) && $_POST['status'] === 'completed') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="status_completed">
                                    Completed
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Select Required Resources</label>
                            <?php if (!empty($resources)): ?>
                                <div class="row g-3">
                                    <?php foreach ($resources as $resource): ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="resource_ids[]" 
                                                       value="<?php echo $resource['id']; ?>"
                                                       id="resource_<?php echo $resource['id']; ?>"
                                                       <?php echo (isset($_POST['resource_ids']) && in_array($resource['id'], (array)$_POST['resource_ids'])) ? 'checked' : ''; ?>>
                                                <label class="form-check-label d-flex align-items-center" for="resource_<?php echo $resource['id']; ?>">
                                                    <span class="me-2" style="display: inline-block; width: 16px; height: 16px; background-color: <?php echo $resource['color_code'] ?? '#3b82f6'; ?>; border-radius: 3px;"></span>
                                                    <?php echo htmlspecialchars($resource['name']); ?>
                                                    <?php if (!empty($resource['capacity'])): ?>
                                                        <small class="text-muted ms-2">(Capacity: <?php echo $resource['capacity']; ?>)</small>
                                                    <?php endif; ?>
                                                </label>
                                                <?php if (!empty($resource['description'])): ?>
                                                    <div class="form-text"><?php echo htmlspecialchars($resource['description']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">No resources available for selection.</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/appointments.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-calendar-plus me-2"></i>Create Appointment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Calendar and Time Picker Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize calendar
    initializeCalendar();
    
    // Initialize time slots
    initializeTimeSlots();
    
    // Sync form fields with calendar selection
    syncFormFields();
    
    // Ensure the priest dropdown is properly initialized
    const priestSelect = document.getElementById('priest_id');
    if (priestSelect) {
        console.log('Priest select element found:', priestSelect);
        console.log('Number of options:', priestSelect.options.length);
    }
});

function initializeCalendar() {
    const calendarEl = document.getElementById('calendar-widget');
    if (!calendarEl) return;
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 300,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek'
        },
        selectable: true,
        selectMirror: true,
        dayMaxEvents: true,
        select: function(info) {
            const selectedDate = info.startStr;
            document.getElementById('appointment_date').value = selectedDate;
            updateTimeSlots(selectedDate);
            calendar.unselect();
        },
        dateClick: function(info) {
            const selectedDate = info.dateStr;
            document.getElementById('appointment_date').value = selectedDate;
            updateTimeSlots(selectedDate);
        }
    });
    
    calendar.render();
}

function initializeTimeSlots() {
    const timeSlotsContainer = document.getElementById('time-slots');
    if (!timeSlotsContainer) return;
    
    // Generate time slots (9 AM to 5 PM, 30-minute intervals)
    const timeSlots = [];
    for (let hour = 9; hour < 17; hour++) {
        for (let minute = 0; minute < 60; minute += 30) {
            const time = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
            const displayTime = formatTime(time);
            timeSlots.push({ value: time, display: displayTime });
        }
    }
    
    // Render time slots
    timeSlotsContainer.innerHTML = '';
    timeSlots.forEach(slot => {
        const slotElement = document.createElement('div');
        slotElement.className = 'time-slot';
        slotElement.textContent = slot.display;
        slotElement.dataset.time = slot.value;
        slotElement.addEventListener('click', function() {
            selectTimeSlot(this, slot.value);
        });
        timeSlotsContainer.appendChild(slotElement);
    });
}

function updateTimeSlots(selectedDate) {
    // In a real application, you would check for existing appointments
    // and disable unavailable time slots
    const timeSlots = document.querySelectorAll('.time-slot');
    timeSlots.forEach(slot => {
        slot.classList.remove('disabled');
        // Here you would check if the time slot is available
        // For now, we'll just enable all slots
    });
}

function selectTimeSlot(element, time) {
    // Remove previous selection
    document.querySelectorAll('.time-slot').forEach(slot => {
        slot.classList.remove('selected');
    });
    
    // Add selection to clicked element
    element.classList.add('selected');
    
    // Update form fields
    document.getElementById('appointment_time').value = time;
    document.getElementById('start_time').value = time;
    
    // Calculate end time (add 1 hour by default)
    const startTime = new Date(`2000-01-01T${time}`);
    const endTime = new Date(startTime.getTime() + 60 * 60 * 1000); // Add 1 hour
    const endTimeString = endTime.toTimeString().slice(0, 5);
    document.getElementById('end_time').value = endTimeString;
}

function formatTime(time) {
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

function syncFormFields() {
    // Sync appointment_date with start_time and end_time
    const appointmentDate = document.getElementById('appointment_date');
    const appointmentTime = document.getElementById('appointment_time');
    const startTime = document.getElementById('start_time');
    const endTime = document.getElementById('end_time');
    
    if (appointmentDate && appointmentTime && startTime && endTime) {
        // When date changes, update time slots
        appointmentDate.addEventListener('change', function() {
            updateTimeSlots(this.value);
        });
        
        // When time changes, update start and end times
        appointmentTime.addEventListener('change', function() {
            startTime.value = this.value;
            // Auto-calculate end time
            if (this.value) {
                const start = new Date(`2000-01-01T${this.value}`);
                const end = new Date(start.getTime() + 60 * 60 * 1000);
                endTime.value = end.toTimeString().slice(0, 5);
            }
        });
    }
}

// Client-side form validation
(function () {
    'use strict'
    
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation')
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            
            form.classList.add('was-validated')
        }, false)
    })
})()

// Show service description when selected
const serviceSelect = document.getElementById('service_id');
if (serviceSelect) {
    serviceSelect.addEventListener('change', function() {
        // This will be handled by the PHP code that shows/hides descriptions
    });
}
</script>

<?php include 'includes/footer.php'; ?>
