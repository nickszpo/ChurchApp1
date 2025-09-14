<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit();
}

require_once 'config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

// Initialize variables
$priest = [
    'id' => null,
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'is_active' => 1,
    'bio' => ''
];

$is_edit = false;
$page_title = 'Add New Priest';
$errors = [];

// Check if we're editing an existing priest
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'priest'");
    $stmt->execute([$_GET['id']]);
    $priest = $stmt->fetch();
    
    if ($priest) {
        $is_edit = true;
        $page_title = 'Edit Priest';
    } else {
        $_SESSION['error'] = 'Priest not found';
        header('Location: priests.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = 'Invalid request';
        header('Location: priests.php');
        exit();
    }

    // Get form data
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($full_name)) $errors[] = 'Full name is required';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    
    // Check if email already exists (for new priests or when email is changed)
    if (!$is_edit || $email !== $priest['email']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email is already in use';
        }
    }

    // No password required for priest accounts

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($is_edit) {
                // Update existing priest
                $sql = "UPDATE users SET 
                            full_name = ?, 
                            email = ?, 
                            phone = ?, 
                            bio = ?, 
                            is_active = ?,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ? AND role = 'priest'";
                
                $params = [
                    $full_name,
                    $email,
                    $phone,
                    $bio,
                    $is_active ? 1 : 0,
                    $priest['id']
                ];
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $_SESSION['success'] = 'Priest updated successfully';
            } else {
                // Create new priest with a default password
                $default_password = 'welcome123';
                $password_hash = password_hash($default_password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO users 
                    (username, full_name, email, phone, bio, password, role, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'priest', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                
                // Generate username from email (everything before @)
                $username = strtok($email, '@');
                
                $stmt->execute([
                    $username,
                    $full_name,
                    $email,
                    $phone,
                    $bio,
                    $password_hash,
                    $is_active ? 1 : 0
                ]);
                
                $_SESSION['success'] = 'Priest added successfully. Default password: ' . $default_password;
            }
            
            $pdo->commit();
            header('Location: priests.php');
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
            error_log('Priest Edit Error: ' . $e->getMessage());
        }
    }
    
    // Update priest array with submitted values for form repopulation
    $priest = [
        'id' => $priest['id'] ?? null,
        'full_name' => $full_name,
        'email' => $email,
        'phone' => $phone,
        'bio' => $bio,
        'is_active' => $is_active
    ];
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $page_title; ?></h1>
                <a href="priests.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Priests
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
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($priest['full_name'] ?? ($priest['first_name'] . ' ' . ($priest['last_name'] ?? ''))); ?>" required>
                            <div class="invalid-feedback">Please enter full name.</div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($priest['email']); ?>" required>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($priest['phone']); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label">Biography</label>
                            <textarea class="form-control" id="bio" name="bio" rows="4"><?php 
                                echo htmlspecialchars($priest['bio']); 
                            ?></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                   value="1" <?php echo $priest['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="priests.php" class="btn btn-outline-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> <?php echo $is_edit ? 'Update' : 'Save'; ?> Priest
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
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
            
            // Check if passwords match
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords do not match");
                event.preventDefault();
                event.stopPropagation();
            } else if (confirmPassword) {
                confirmPassword.setCustomValidity('');
            }
            
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php include 'includes/footer.php'; ?>
