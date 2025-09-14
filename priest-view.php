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

// Check if priest ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid priest ID';
    header('Location: priests.php');
    exit();
}

// Get priest details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'priest'");
$stmt->execute([$_GET['id']]);
$priest = $stmt->fetch();

if (!$priest) {
    $_SESSION['error'] = 'Priest not found';
    header('Location: priests.php');
    exit();
}

// Set page title
$page_title = 'View Priest: ' . htmlspecialchars($priest['first_name'] . ' ' . $priest['last_name']);
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <?php echo htmlspecialchars($priest['first_name'] . ' ' . $priest['last_name']); ?>
                    <span class="badge bg-<?php echo $priest['is_active'] ? 'success' : 'secondary'; ?> fs-6">
                        <?php echo $priest['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="priests.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Back to Priests
                    </a>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="priest-edit.php?id=<?php echo $priest['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Priest Information</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-3">Full Name</dt>
                                <dd class="col-sm-9"><?php echo htmlspecialchars($priest['first_name'] . ' ' . $priest['last_name']); ?></dd>

                                <dt class="col-sm-3">Email</dt>
                                <dd class="col-sm-9">
                                    <a href="mailto:<?php echo htmlspecialchars($priest['email']); ?>">
                                        <?php echo htmlspecialchars($priest['email']); ?>
                                    </a>
                                </dd>

                                <?php if (!empty($priest['phone'])): ?>
                                <dt class="col-sm-3">Phone</dt>
                                <dd class="col-sm-9">
                                    <a href="tel:<?php echo htmlspecialchars($priest['phone']); ?>">
                                        <?php echo htmlspecialchars($priest['phone']); ?>
                                    </a>
                                </dd>
                                <?php endif; ?>

                                <?php if (!empty($priest['bio'])): ?>
                                <dt class="col-sm-3">Biography</dt>
                                <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($priest['bio'])); ?></dd>
                                <?php endif; ?>

                                <dt class="col-sm-3">Status</dt>
                                <dd class="col-sm-9">
                                    <span class="badge bg-<?php echo $priest['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $priest['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </dd>

                                <dt class="col-sm-3">Member Since</dt>
                                <dd class="col-sm-9">
                                    <?php 
                                    $date = new DateTime($priest['created_at']);
                                    echo $date->format('F j, Y'); 
                                    ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <a href="mailto:<?php echo htmlspecialchars($priest['email']); ?>" class="list-group-item list-group-item-action">
                                <i class="bi bi-envelope me-2"></i> Send Email
                            </a>
                            <?php if (!empty($priest['phone'])): ?>
                            <a href="tel:<?php echo htmlspecialchars($priest['phone']); ?>" class="list-group-item list-group-item-action">
                                <i class="bi bi-telephone me-2"></i> Call
                            </a>
                            <?php endif; ?>
                            <a href="appointments.php?priest_id=<?php echo $priest['id']; ?>" class="list-group-item list-group-item-action">
                                <i class="bi bi-calendar3 me-2"></i> View Schedule
                            </a>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="priest-edit.php?id=<?php echo $priest['id']; ?>" class="list-group-item list-group-item-action">
                                <i class="bi bi-pencil me-2"></i> Edit Profile
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
