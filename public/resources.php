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
require_once 'includes/ResourceManager.php';

$db = Database::getInstance();
$pdo = $db->getConnection();
$resourceManager = new ResourceManager($pdo);

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);
        $location = trim($_POST['location'] ?? '');
        $color_code = trim($_POST['color_code'] ?? '#3b82f6');
        
        if (!empty($name)) {
            try {
                $resourceManager->addResource([
                    'name' => $name,
                    'description' => $description,
                    'capacity' => $capacity > 0 ? $capacity : null,
                    'location' => $location,
                    'color_code' => $color_code
                ]);
                $message = 'Resource added successfully';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error adding resource: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = 'Resource name is required';
            $messageType = 'danger';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $resourceManager->deleteResource($id);
                $message = 'Resource deleted successfully';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error deleting resource: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Get all resources
$resources = $resourceManager->getAllResources(true);

$page_title = 'Resources';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Resources</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addResourceModal">
            <i class="bi bi-plus-circle"></i> Add Resource
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
        <h5 class="mb-0">Resource Management</h5>
    </div>
    <div class="card-body">
        <?php if (count($resources) > 0): ?>
            <div class="row g-3">
                <?php foreach ($resources as $resource): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="me-2" style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($resource['color_code']); ?>; border-radius: 4px;"></span>
                                    <h6 class="card-title mb-0"><?php echo htmlspecialchars($resource['name']); ?></h6>
                                </div>
                                <?php if (!empty($resource['description'])): ?>
                                    <p class="card-text text-muted small"><?php echo htmlspecialchars($resource['description']); ?></p>
                                <?php endif; ?>
                                <div class="small text-muted">
                                    <?php if (!empty($resource['capacity'])): ?>
                                        <div><i class="bi bi-people"></i> Capacity: <?php echo $resource['capacity']; ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($resource['location'])): ?>
                                        <div><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($resource['location']); ?></div>
                                    <?php endif; ?>
                                    <div><i class="bi bi-circle-fill text-<?php echo $resource['is_active'] ? 'success' : 'danger'; ?>"></i> 
                                        <?php echo $resource['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="btn-group btn-group-sm w-100">
                                    <button type="button" class="btn btn-outline-primary" onclick="editResource(<?php echo $resource['id']; ?>)">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="deleteResource(<?php echo $resource['id']; ?>, '<?php echo addslashes($resource['name']); ?>')">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-collection text-muted" style="font-size: 3rem;"></i>
                <h5 class="mt-3">No Resources Found</h5>
                <p class="text-muted">Add your first resource to get started.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addResourceModal">
                    <i class="bi bi-plus-circle"></i> Add Resource
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Resource Modal -->
<div class="modal fade" id="addResourceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Add Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="capacity" class="form-label">Capacity</label>
                                <input type="number" class="form-control" id="capacity" name="capacity" min="1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="color_code" class="form-label">Color</label>
                                <input type="color" class="form-control" id="color_code" name="color_code" value="#3b82f6">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Resource</button>
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
                <p>Are you sure you want to delete the resource <strong id="deleteResourceName"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteResourceId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteResource(id, name) {
    document.getElementById('deleteResourceId').value = id;
    document.getElementById('deleteResourceName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function editResource(id) {
    // TODO: Implement edit functionality
    alert('Edit functionality coming soon!');
}
</script>

<?php include 'includes/footer.php'; ?>