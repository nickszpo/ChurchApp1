<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

// Pagination
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page - 1) * $per_page : 0;

// Filters
$status = $_GET['status'] ?? '';
$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build the query
$query = "
    SELECT a.*, s.name as service_name, u.full_name as requester_name,
           (SELECT COUNT(*) FROM appointment_resources ar WHERE ar.appointment_id = a.id) as resource_count
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.user_id = u.id
    WHERE 1=1
";

$params = [];

// Apply filters
if (!empty($status)) {
    $query .= " AND a.status = ?";
    $params[] = $status;
}

if ($service_id > 0) {
    $query .= " AND a.service_id = ?";
    $params[] = $service_id;
}

if (!empty($search)) {
    $query .= " AND (a.reference_number LIKE ? OR a.title LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($date_from)) {
    $query .= " AND DATE(a.start_time) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(a.end_time) <= ?";
    $params[] = $date_to;
}

// For non-admin users, only show their own appointments
if ($_SESSION['role'] !== 'admin') {
    $query .= " AND a.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

// Count total records for pagination
$count_query = "SELECT COUNT(*) as total FROM ($query) as count_table";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Add sorting and pagination to the main query
$query .= " ORDER BY a.start_time DESC LIMIT ?, ?";
$params[] = $start;
$params[] = $per_page;

// Fetch appointments
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Get services for filter dropdown
$services = $pdo->query("SELECT id, name FROM services ORDER BY name")->fetchAll();

// Set page title
$page_title = 'Appointments';
?>

<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Appointments</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="new-appointment.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> New Appointment
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-funnel"></i> Filters
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by reference, title or name">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="service_id" class="form-label">Service</label>
                <select class="form-select" id="service_id" name="service_id">
                    <option value="">All Services</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?php echo $service['id']; ?>" <?php echo $service_id == $service['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($service['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-funnel"></i> Apply Filters
                </button>
                <a href="appointments.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Appointments Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar-week"></i> Appointments (<?php echo $total_records; ?>)</span>
        <div>
            <a href="appointments-export.php?<?php echo http_build_query($_GET); ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-download"></i> Export
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (count($appointments) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ref #</th>
                            <th>Title</th>
                            <th>Service</th>
                            <th>Date & Time</th>
                            <th>Requester</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($appointment['reference_number']); ?></td>
                                <td>
                                    <a href="appointment.php?id=<?php echo $appointment['id']; ?>">
                                        <?php echo htmlspecialchars($appointment['title']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                <td>
                                    <?php 
                                        $start = new DateTime($appointment['start_time']);
                                        $end = new DateTime($appointment['end_time']);
                                        echo $start->format('M j, Y h:i A') . ' - ' . $end->format('h:i A');
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($appointment['requester_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($appointment['status']) {
                                            'pending' => 'warning',
                                            'confirmed' => 'success',
                                            'completed' => 'info',
                                            'cancelled' => 'danger',
                                            'rejected' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['user_id'] == $appointment['user_id']): ?>
                                            <a href="appointment-edit.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['role'] === 'admin'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" 
                                                onclick="confirmDelete(<?php echo $appointment['id']; ?>, '<?php echo addslashes($appointment['title']); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                </div>
                <h5>No appointments found</h5>
                <p class="text-muted">
                    <?php echo isset($_GET['search']) ? 'Try adjusting your search or filter criteria' : 'Create your first appointment to get started'; ?>
                </p>
                <a href="new-appointment.php" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-circle"></i> New Appointment
                </a>
            </div>
        <?php endif; ?>
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
                <p>Are you sure you want to delete the appointment <strong id="deleteAppointmentTitle"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="post" action="appointment-delete.php" class="d-inline">
                    <input type="hidden" name="id" id="deleteAppointmentId">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Confirm before deleting
function confirmDelete(id, title) {
    document.getElementById('deleteAppointmentId').value = id;
    document.getElementById('deleteAppointmentTitle').textContent = title;
    
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}

// Initialize datepickers
if (typeof flatpickr !== 'undefined') {
    flatpickr('#date_from, #date_to', {
        dateFormat: 'Y-m-d',
        allowInput: true
    });
}
</script>

<?php include 'includes/footer.php'; ?>
