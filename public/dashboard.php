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

// Get upcoming appointments (next 7 days)
$upcoming_query = "
    SELECT a.*, s.name as service_name, 
           u.full_name as requester_name 
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.user_id = u.id
    WHERE a.start_time >= datetime('now')
    AND a.start_time <= datetime('now', '+7 days')
    ORDER BY a.start_time ASC
    LIMIT 5
";
$upcoming_appointments = $pdo->query($upcoming_query)->fetchAll();

// Get pending appointments
$pending_query = "
    SELECT a.*, s.name as service_name, 
           u.full_name as requester_name 
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.user_id = u.id
    WHERE a.status = 'pending'
    ORDER BY a.created_at DESC
    LIMIT 5
";
$pending_appointments = $pdo->query($pending_query)->fetchAll();

// Get recent announcements
$announcements_query = "
    SELECT a.*, u.full_name as author_name 
    FROM announcements a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.is_pinned DESC, a.created_at DESC
    LIMIT 3
";
$recent_announcements = $pdo->query($announcements_query)->fetchAll();
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">Share</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                        <span data-feather="calendar"></span>
                        This week
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Upcoming Appointments</h5>
                            <?php 
                            $count = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE start_time >= datetime('now') AND status != 'cancelled'")->fetch()['count'];
                            echo '<h2 class="card-text">' . $count . '</h2>';
                            ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Pending Approval</h5>
                            <?php 
                            $count = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'")->fetch()['count'];
                            echo '<h2 class="card-text">' . $count . '</h2>';
                            ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Completed This Month</h5>
                            <?php 
                            $count = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed' AND strftime('%Y-%m', start_time) = strftime('%Y-%m', 'now')")->fetch()['count'];
                            echo '<h2 class="card-text">' . $count . '</h2>';
                            ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Active Priests</h5>
                            <?php 
                            $count = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'priest'")->fetch()['count'];
                            echo '<h2 class="card-text">' . $count . '</h2>';
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Upcoming Appointments -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Upcoming Appointments</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($upcoming_appointments) > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($upcoming_appointments as $appointment): ?>
                                        <a href="appointment.php?id=<?php echo $appointment['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($appointment['title']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y', strtotime($appointment['start_time'])); ?>
                                                </small>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars($appointment['service_name']); ?></p>
                                            <small class="text-muted">
                                                <?php echo date('g:i A', strtotime($appointment['start_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($appointment['end_time'])); ?>
                                            </small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="p-3 text-center text-muted">No upcoming appointments</div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer text-end">
                            <a href="appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                </div>

                <!-- Pending Approvals -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Pending Approvals</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($pending_appointments) > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($pending_appointments as $appointment): ?>
                                        <a href="appointment.php?id=<?php echo $appointment['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($appointment['title']); ?></h6>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars($appointment['service_name']); ?></p>
                                            <small class="text-muted">
                                                Requested by <?php echo htmlspecialchars($appointment['requester_name']); ?> on 
                                                <?php echo date('M j, Y', strtotime($appointment['created_at'])); ?>
                                            </small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="p-3 text-center text-muted">No pending approvals</div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer text-end">
                            <a href="appointments.php?status=pending" class="btn btn-sm btn-outline-warning">View All Pending</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Announcements -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Announcements</h5>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="announcements.php?action=new" class="btn btn-sm btn-primary">New Announcement</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (count($recent_announcements) > 0): ?>
                        <?php foreach ($recent_announcements as $announcement): ?>
                            <div class="mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">
                                        <?php if ($announcement['is_pinned']): ?>
                                            <i class="bi bi-pin-angle-fill text-warning" title="Pinned"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($announcement['created_at'])); ?>
                                    </small>
                                </div>
                                <p class="mb-1"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                <small class="text-muted">
                                    Posted by <?php echo htmlspecialchars($announcement['author_name']); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">No announcements yet</div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-end">
                    <a href="announcements.php" class="btn btn-sm btn-outline-secondary">View All Announcements</a>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
