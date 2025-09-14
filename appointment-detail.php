<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

require_once 'config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

// Include the AppointmentManager and ResourceManager
require_once 'includes/AppointmentManager.php';
require_once 'includes/ResourceManager.php';
$appointmentManager = new AppointmentManager($pdo);
$resourceManager = new ResourceManager($pdo);

// Get appointment ID from URL
$appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$appointmentId) {
    $_SESSION['error'] = 'No appointment ID provided.';
    header('Location: /appointments.php');
    exit();
}

// Get appointment details
$appointment = $appointmentManager->getAppointmentById($appointmentId, true);

// Check if appointment exists and user has permission to view it
if (!$appointment || ($_SESSION['role'] !== 'admin' && $appointment['user_id'] != $_SESSION['user_id'])) {
    $_SESSION['error'] = 'Appointment not found or access denied.';
    header('Location: /appointments.php');
    exit();
}

// Format dates
$startDate = new DateTime($appointment['start_time']);
$endDate = new DateTime($appointment['end_time']);
$duration = $startDate->diff($endDate);

$page_title = 'Appointment Details';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Appointment Details</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/appointments.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Back to Appointments
                    </a>
                    <?php if ($_SESSION['role'] === 'admin' || $appointment['user_id'] == $_SESSION['user_id']): ?>
                        <a href="/appointment-edit.php?id=<?= $appointment['id'] ?>" class="btn btn-primary me-2">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Appointment Information</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-3">Reference #</dt>
                                <dd class="col-sm-9"><?= htmlspecialchars($appointment['reference_number']) ?></dd>

                                <dt class="col-sm-3">Service</dt>
                                <dd class="col-sm-9"><?= htmlspecialchars($appointment['service_name']) ?></dd>

                                <dt class="col-sm-3">Date & Time</dt>
                                <dd class="col-sm-9">
                                    <?= $startDate->format('l, F j, Y') ?><br>
                                    <?= $startDate->format('g:i A') ?> - <?= $endDate->format('g:i A') ?>
                                    <span class="text-muted">(<?= $duration->format('%h hr %i min') ?>)</span>
                                </dd>

                                <?php if (!empty($appointment['description'])): ?>
                                    <dt class="col-sm-3">Description</dt>
                                    <dd class="col-sm-9"><?= nl2br(htmlspecialchars($appointment['description'])) ?></dd>
                                <?php endif; ?>

                                <dt class="col-sm-3">Status</dt>
                                <dd class="col-sm-9">
                                    <?php
                                    $statusClass = [
                                        'pending' => 'bg-warning',
                                        'confirmed' => 'bg-success',
                                        'cancelled' => 'bg-danger',
                                        'completed' => 'bg-info'
                                    ][$appointment['status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $statusClass ?>">
                                        <?= ucfirst($appointment['status']) ?>
                                    </span>
                                </dd>

                                <dt class="col-sm-3">Created</dt>
                                <dd class="col-sm-9">
                                    <?= (new DateTime($appointment['created_at']))->format('M j, Y g:i A') ?>
                                </dd>

                                <?php if ($appointment['updated_at'] !== $appointment['created_at']): ?>
                                    <dt class="col-sm-3">Last Updated</dt>
                                    <dd class="col-sm-9">
                                        <?= (new DateTime($appointment['updated_at']))->format('M j, Y g:i A') ?>
                                    </dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>

                    <?php if (!empty($appointment['resources'])): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Assigned Resources</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Location</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($appointment['resources'] as $resource): ?>
                                                <tr>
                                                    <td>
                                                        <a href="/resource-detail.php?id=<?= $resource['id'] ?>">
                                                            <?= htmlspecialchars($resource['name']) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= htmlspecialchars($resource['type'] ?? 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($resource['location'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <?php
                                                        $statusClass = [
                                                            'available' => 'bg-success',
                                                            'in_use' => 'bg-warning',
                                                            'maintenance' => 'bg-danger'
                                                        ][$resource['status'] ?? 'available'] ?? 'bg-secondary';
                                                        ?>
                                                        <span class="badge <?= $statusClass ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $resource['status'] ?? 'N/A')) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Attendee</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 60px; height: 60px; font-size: 24px;">
                                        <?= strtoupper(substr($appointment['requester_name'], 0, 1)) ?>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-0"><?= htmlspecialchars($appointment['requester_name']) ?></h6>
                                    <small class="text-muted">
                                        <?= $appointment['user_id'] == $_SESSION['user_id'] ? 'You' : 'Attendee' ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'confirmed'): ?>
                                    <?php if ($_SESSION['role'] === 'admin' || $appointment['user_id'] == $_SESSION['user_id']): ?>
                                        <a href="/appointment-edit.php?id=<?= $appointment['id'] ?>" class="btn btn-outline-primary mb-2">
                                            <i class="bi bi-pencil"></i> Edit Appointment
                                        </a>
                                        
                                        <?php if ($appointment['status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-outline-success mb-2" data-bs-toggle="modal" data-bs-target="#confirmModal">
                                                <i class="bi bi-check-circle"></i> Confirm
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-outline-danger mb-2" data-bs-toggle="modal" data-bs-target="#cancelModal">
                                            <i class="bi bi-x-circle"></i> Cancel
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <a href="#" class="btn btn-outline-secondary mb-2" data-bs-toggle="modal" data-bs-target="#changeResourceModal">
                                            <i class="bi bi-arrow-repeat"></i> Change Resources
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <a href="#" class="btn btn-outline-secondary" onclick="window.print()">
                                    <i class="bi bi-printer"></i> Print Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Confirm Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">Confirm Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to confirm this appointment?</p>
                <p class="mb-0"><strong>Service:</strong> <?= htmlspecialchars($appointment['service_name']) ?></p>
                <p class="mb-0"><strong>Date:</strong> <?= $startDate->format('l, F j, Y') ?></p>
                <p><strong>Time:</strong> <?= $startDate->format('g:i A') ?> - <?= $endDate->format('g:i A') ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <form action="/api/appointment.php" method="POST" style="display: inline;">
                    <input type="hidden" name="_method" value="PATCH">
                    <input type="hidden" name="id" value="<?= $appointment['id'] ?>">
                    <input type="hidden" name="status" value="confirmed">
                    <button type="submit" class="btn btn-success">Confirm Appointment</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelModalLabel">Cancel Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="/api/appointment.php" method="POST">
                <div class="modal-body">
                    <p>Are you sure you want to cancel this appointment?</p>
                    <p class="mb-0"><strong>Service:</strong> <?= htmlspecialchars($appointment['service_name']) ?></p>
                    <p class="mb-0"><strong>Date:</strong> <?= $startDate->format('l, F j, Y') ?></p>
                    <p><strong>Time:</strong> <?= $startDate->format('g:i A') ?> - <?= $endDate->format('g:i A') ?></p>
                    
                    <div class="mb-3 mt-3">
                        <label for="cancelReason" class="form-label">Reason for cancellation (optional):</label>
                        <textarea class="form-control" id="cancelReason" name="cancel_reason" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <input type="hidden" name="_method" value="PATCH">
                    <input type="hidden" name="id" value="<?= $appointment['id'] ?>">
                    <input type="hidden" name="status" value="cancelled">
                    <button type="submit" class="btn btn-danger">Cancel Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Resource Modal -->
<?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="modal fade" id="changeResourceModal" tabindex="-1" aria-labelledby="changeResourceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeResourceModalLabel">Change Resources</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="/api/appointment.php" method="POST">
                    <div class="modal-body">
                        <p>Select resources to assign to this appointment:</p>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Get all resources
                                    $resources = $resourceManager->getAllResources();
                                    $assignedResourceIds = array_column($appointment['resources'] ?? [], 'id');
                                    
                                    foreach ($resources as $resource): 
                                        $isChecked = in_array($resource['id'], $assignedResourceIds);
                                    ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input" 
                                                       name="resource_ids[]" value="<?= $resource['id'] ?>"
                                                       <?= $isChecked ? 'checked' : '' ?>>
                                            </td>
                                            <td><?= htmlspecialchars($resource['name']) ?></td>
                                            <td><?= htmlspecialchars($resource['type'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php
                                                $status = $resource['status'] ?? 'available';
                                                $statusClass = [
                                                    'available' => 'bg-success',
                                                    'in_use' => 'bg-warning',
                                                    'maintenance' => 'bg-danger'
                                                ][$status] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?= $statusClass ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $status)) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <input type="hidden" name="_method" value="PATCH">
                        <input type="hidden" name="id" value="<?= $appointment['id'] ?>">
                        <button type="submit" class="btn btn-primary">Update Resources</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
@media print {
    .no-print, .no-print * {
        display: none !important;
    }
    
    .card {
        border: 1px solid #dee2e6;
        box-shadow: none;
    }
    
    .container-fluid {
        max-width: 100%;
    }
}

.avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}
</style>

<?php include 'includes/footer.php'; ?>
