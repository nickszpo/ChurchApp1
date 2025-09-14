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
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        if (!empty($username) && !empty($password) && !empty($full_name) && !empty($email)) {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (username, password, full_name, email, phone, bio, role) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$username, $hashed_password, $full_name, $email, $phone, $bio, 'priest']);
                $message = 'Priest added successfully';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error adding priest: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = 'Username, password, full name, and email are required';
            $messageType = 'danger';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = ?');
                $stmt->execute([$id, 'priest']);
                $message = 'Priest deleted successfully';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error deleting priest: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Get all priests
$priests = $pdo->query("SELECT id, username, full_name, email, phone, bio, is_active, created_at FROM users WHERE role = 'priest' ORDER BY full_name")->fetchAll();

$page_title = 'Priests';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Priests</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPriestModal">
            <i class="bi bi-plus-circle"></i> Add Priest
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
        <h5 class="mb-0">Priest Management</h5>
    </div>
    <div class="card-body">
        <?php if (count($priests) > 0): ?>
            <div class="row g-3">
                <?php foreach ($priests as $priest): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <div>
                                        <h6 class="card-title mb-0"><?php echo htmlspecialchars($priest['full_name']); ?></h6>
                                        <small class="text-muted">@<?php echo htmlspecialchars($priest['username']); ?></small>
                                    </div>
                                </div>
                                
                                <?php if (!empty($priest['bio'])): ?>
                                    <p class="card-text text-muted small"><?php echo htmlspecialchars($priest['bio']); ?></p>
                                <?php endif; ?>
                                
                                <div class="small text-muted">
                                    <?php if (!empty($priest['email'])): ?>
                                        <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($priest['email']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($priest['phone'])): ?>
                                        <div><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($priest['phone']); ?></div>
                                    <?php endif; ?>
                                    <div><i class="bi bi-circle-fill text-<?php echo $priest['is_active'] ? 'success' : 'danger'; ?>"></i> 
                                        <?php echo $priest['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="btn-group btn-group-sm w-100">
                                    <button type="button" class="btn btn-outline-primary" onclick="editPriest(<?php echo $priest['id']; ?>)">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="deletePriest(<?php echo $priest['id']; ?>, '<?php echo addslashes($priest['full_name']); ?>')">
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
                <i class="bi bi-person-badge text-muted" style="font-size: 3rem;"></i>
                <h5 class="mt-3">No Priests Found</h5>
                <p class="text-muted">Add your first priest to get started.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPriestModal">
                    <i class="bi bi-plus-circle"></i> Add Priest
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Priest Modal -->
<div class="modal fade" id="addPriestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Add Priest</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Priest</button>
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
                <p>Are you sure you want to delete the priest <strong id="deletePriestName"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deletePriestId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deletePriest(id, name) {
    document.getElementById('deletePriestId').value = id;
    document.getElementById('deletePriestName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function editPriest(id) {
    // TODO: Implement edit functionality
    alert('Edit functionality coming soon!');
}
</script>

<?php include 'includes/footer.php'; ?>