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

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
        
        if (!empty($title) && !empty($content)) {
            try {
                $stmt = $pdo->prepare('INSERT INTO announcements (user_id, title, content, is_pinned) VALUES (?, ?, ?, ?)');
                $stmt->execute([$_SESSION['user_id'], $title, $content, $is_pinned]);
                $message = 'Announcement added successfully';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error adding announcement: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = 'Title and content are required';
            $messageType = 'danger';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // Only allow deletion by admin or the author
                $stmt = $pdo->prepare('DELETE FROM announcements WHERE id = ? AND (user_id = ? OR ? = ?)');
                $stmt->execute([$id, $_SESSION['user_id'], $_SESSION['role'], 'admin']);
                $message = 'Announcement deleted successfully';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error deleting announcement: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Get all announcements with author names
$announcements = $pdo->query("
    SELECT a.*, u.full_name as author_name 
    FROM announcements a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.is_pinned DESC, a.created_at DESC
")->fetchAll();

$page_title = 'Announcements';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Announcements</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
            <i class="bi bi-plus-circle"></i> New Announcement
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <?php if (count($announcements) > 0): ?>
        <?php foreach ($announcements as $announcement): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <?php if ($announcement['is_pinned']): ?>
                                <i class="bi bi-pin-angle-fill text-warning" title="Pinned"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($announcement['title']); ?>
                        </h6>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <?php if ($_SESSION['role'] === 'admin' || $announcement['user_id'] == $_SESSION['user_id']): ?>
                                    <li><a class="dropdown-item" href="#" onclick="editAnnouncement(<?php echo $announcement['id']; ?>)">
                                        <i class="bi bi-pencil me-2"></i> Edit
                                    </a></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo addslashes($announcement['title']); ?>')">
                                        <i class="bi bi-trash me-2"></i> Delete
                                    </a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                    </div>
                    <div class="card-footer text-muted small">
                        <div class="d-flex justify-content-between">
                            <span>By <?php echo htmlspecialchars($announcement['author_name']); ?></span>
                            <span><?php echo date('M j, Y', strtotime($announcement['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="text-center py-5">
                <i class="bi bi-megaphone text-muted" style="font-size: 3rem;"></i>
                <h5 class="mt-3">No Announcements</h5>
                <p class="text-muted">Create your first announcement to get started.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                    <i class="bi bi-plus-circle"></i> New Announcement
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add Announcement Modal -->
<div class="modal fade" id="addAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="content" class="form-label">Content *</label>
                        <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                    </div>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_pinned" name="is_pinned">
                            <label class="form-check-label" for="is_pinned">
                                Pin this announcement
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Post Announcement</button>
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
                <p>Are you sure you want to delete the announcement <strong id="deleteAnnouncementTitle"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteAnnouncementId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteAnnouncement(id, title) {
    document.getElementById('deleteAnnouncementId').value = id;
    document.getElementById('deleteAnnouncementTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function editAnnouncement(id) {
    // TODO: Implement edit functionality
    alert('Edit functionality coming soon!');
}
</script>

<?php include 'includes/footer.php'; ?>
