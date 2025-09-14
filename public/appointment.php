<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Validate appointment ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid appointment ID';
    header('Location: appointments.php');
    exit();
}

$appointment_id = (int)$_GET['id'];

require_once 'config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

try {
    // Get appointment details
    $query = "
        SELECT a.*, 
               s.name as service_name, 
               u.full_name as requester_name,
               u.email as requester_email,
               DATE(a.start_time) as appointment_date,
               TIME(a.start_time) as appointment_time
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        throw new Exception('Appointment not found');
    }
    
    // Check permissions
    if ($_SESSION['role'] !== 'admin' && $appointment['user_id'] !== $_SESSION['user_id']) {
        $_SESSION['error'] = 'You do not have permission to view this appointment';
        header('Location: appointments.php');
        exit();
    }
    
    // Format dates
    $start_date = new DateTime($appointment['start_time']);
    $end_date = new DateTime($appointment['end_time']);
    
    // Set page title
    $page_title = 'Appointment: ' . htmlspecialchars($appointment['title']);
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: appointments.php');
    exit();
}
?>

<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/appointments.php">Appointments</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($appointment['title']); ?></li>
        </ol>
    </nav>
    
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="appointments.php" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
        
        <?php if ($_SESSION['role'] === 'admin' || $appointment['user_id'] === $_SESSION['user_id']): ?>
            <a href="appointment-edit.php?id=<?php echo $appointment_id; ?>" class="btn btn-sm btn-primary me-2">
                <i class="bi bi-pencil"></i> Edit
            </a>
        <?php endif; ?>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                <i class="bi bi-trash"></i> Delete
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Status Alert -->
<?php if ($appointment['status'] === 'pending'): ?>
    <div class="alert alert-warning d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div>
            This appointment is pending approval.
            <?php if ($_SESSION['role'] === 'admin'): ?>
                Please review and approve or reject it.
            <?php else: ?>
                You will be notified once it's approved.
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Main Content -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Appointment Details</h5>
                <span class="badge bg-<?php 
                    echo match($appointment['status']) {
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        'rejected' => 'danger',
                        default => 'secondary'
                    };
                ?> text-uppercase">
                    <?php echo ucfirst($appointment['status']); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h4 class="card-title"><?php echo htmlspecialchars($appointment['title']); ?></h4>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($appointment['description'])); ?></p>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small">Date & Time</h6>
                        <p class="mb-3">
                            <i class="bi bi-calendar-event me-2"></i>
                            <?php echo $start_date->format('l, F j, Y'); ?>
                        </p>
                        <p class="mb-3">
                            <i class="bi bi-clock me-2"></i>
                            <?php echo $start_date->format('g:i A') . ' - ' . $end_date->format('g:i A'); ?>
                        </p>
                        
                        <h6 class="text-muted text-uppercase small mt-4">Service</h6>
                        <p class="mb-3">
                            <i class="bi bi-bookmark me-2"></i>
                            <?php echo htmlspecialchars($appointment['service_name']); ?>
                        </p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small">Requester</h6>
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <?php echo strtoupper(substr($appointment['requester_name'], 0, 1)); ?>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0"><?php echo htmlspecialchars($appointment['requester_name']); ?></h6>
                                <?php if (!empty($appointment['requester_email'])): ?>
                                    <div class="text-muted small">
                                        <i class="bi bi-envelope me-1"></i> 
                                        <a href="mailto:<?php echo htmlspecialchars($appointment['requester_email']); ?>" class="text-reset">
                                            <?php echo htmlspecialchars($appointment['requester_email']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($appointment['notes'])): ?>
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="text-muted text-uppercase small">Additional Notes</h6>
                        <div class="bg-light p-3 rounded">
                            <?php echo nl2br(htmlspecialchars($appointment['notes'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Admin Actions -->
        <?php if ($_SESSION['role'] === 'admin' && $appointment['status'] === 'pending'): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Admin Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <form action="appointment-status.php" method="post" class="d-inline">
                            <input type="hidden" name="id" value="<?php echo $appointment_id; ?>">
                            <input type="hidden" name="status" value="confirmed">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Approve
                            </button>
                        </form>
                        
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="#" class="list-group-item list-group-item-action" onclick="window.print()">
                    <i class="bi bi-printer me-2"></i> Print
                </a>
                <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#emailModal">
                    <i class="bi bi-envelope me-2"></i> Email Details
                </a>
                <?php if ($appointment['status'] === 'confirmed' && strtotime($appointment['start_time']) > time()): ?>
                    <form action="appointment-status.php" method="post" class="list-group-item list-group-item-action">
                        <input type="hidden" name="id" value="<?php echo $appointment_id; ?>">
                        <input type="hidden" name="status" value="cancelled">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" class="btn btn-link text-danger p-0 border-0 bg-transparent" 
                                onclick="return confirm('Are you sure you want to cancel this appointment?');">
                            <i class="bi bi-x-circle me-2"></i> Cancel Appointment
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Service Information -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Service Information</h5>
            </div>
            <div class="card-body">
                <h6><?php echo htmlspecialchars($appointment['service_name']); ?></h6>
                <a href="service.php?id=<?php echo $appointment['service_id']; ?>" class="btn btn-sm btn-outline-primary">
                    View Service Details
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this appointment?</p>
                <p class="mb-0"><strong>Title:</strong> <?php echo htmlspecialchars($appointment['title']); ?></p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="appointment-delete.php" method="post" class="d-inline">
                    <input type="hidden" name="id" value="<?php echo $appointment_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete Appointment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="appointment-status.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Please provide a reason for rejecting this appointment:</p>
                    <input type="hidden" name="id" value="<?php echo $appointment_id; ?>">
                    <input type="hidden" name="status" value="rejected">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Reject Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Email Modal -->
<div class="modal fade" id="emailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="appointment-email.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Email Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="email_to" class="form-label">To</label>
                        <input type="email" class="form-control" id="email_to" name="email_to" 
                               value="<?php echo htmlspecialchars($appointment['requester_email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email_subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="email_subject" name="email_subject"
                               value="Appointment Details: <?php echo htmlspecialchars($appointment['title']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email_message" class="form-label">Message</label>
                        <textarea class="form-control" id="email_message" name="email_message" rows="5">Dear <?php echo htmlspecialchars($appointment['requester_name']); ?>,

Here are the details of your appointment:

Title: <?php echo htmlspecialchars($appointment['title']); ?>
Date: <?php echo $start_date->format('l, F j, Y'); ?>
Time: <?php echo $start_date->format('g:i A') . ' - ' . $end_date->format('g:i A'); ?>

Please arrive 10 minutes before your scheduled time.

Best regards,
[Your Name]</textarea>
                    </div>
                    <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Send Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
