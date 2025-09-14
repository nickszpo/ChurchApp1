<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'You do not have permission to access this page';
    header('Location: dashboard.php');
    exit();
}

require_once 'config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = (int)($_POST['duration_minutes'] ?? 60);
        
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare('INSERT INTO services (name, description, duration_minutes) VALUES (?, ?, ?)');
                $stmt->execute([$name, $description, $duration]);
                $message = 'Service added successfully';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error adding service: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = 'Service name is required';
            $messageType = 'danger';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM services WHERE id = ?');
                $stmt->execute([$id]);
                $message = 'Service deleted successfully';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error deleting service: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Get all services
$services = $pdo->query('SELECT * FROM services ORDER BY name')->fetchAll();

$page_title = 'Services';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Services</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
            <i class="bi bi-plus-circle"></i> Add Service
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Service Management</h5>
    </div>
    <div class="card-body">
        <?php if (count($services) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($service['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($service['description']); ?></td>
                                <td><?php echo $service['duration_minutes']; ?> minutes</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" onclick="editService(<?php echo $service['id']; ?>)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" onclick="deleteService(<?php echo $service['id']; ?>, '<?php echo addslashes($service['name']); ?>')">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-list-check text-muted" style="font-size: 3rem;"></i>
                <h5 class="mt-3">No Services Found</h5>
                <p class="text-muted">Add your first service to get started.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                    <i class="bi bi-plus-circle"></i> Add Service
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Service Modal -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Add Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="name" class="form-label">Service Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="duration_minutes" class="form-label">Duration (minutes) *</label>
                        <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" value="60" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the service <strong id="deleteServiceName"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteServiceId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteService(id, name) {
    document.getElementById('deleteServiceId').value = id;
    document.getElementById('deleteServiceName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function editService(id) {
    // TODO: Implement edit functionality
    alert('Edit functionality coming soon!');
}
</script>

<?php include 'includes/footer.php'; ?>
