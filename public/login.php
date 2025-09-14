<?php
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/database.php';
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Log login (if audit_logs table exists)
            try {
                $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)');
                $stmt->execute([$user['id'], 'login', 'users', $user['id']]);
            } catch (PDOException $e) {
                // Ignore if audit_logs table doesn't exist
                error_log('Could not log login: ' . $e->getMessage());
            }
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - St. Thomas Aquinas Parish Church Event and Resource Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            background-color: #f5f5f5;
        }
        .login-container {
            max-width: 400px;
            padding: 15px;
            margin: 0 auto;
        }
        .login-form {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .form-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .form-logo img {
            max-width: 150px;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-form">
                <div class="form-logo">
                    <h2>St. Thomas Aquinas Parish Church Event and Resource Management System </h2>
                    <p class="text-muted">Please sign in to continue</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Sign In</button>
                    </div>
                </form>
                
                <div class="mt-3 text-center">
                    <p class="mb-0">
                        <a href="#" class="text-decoration-none">Forgot your password?</a>
                    </p>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <p class="text-muted">
                    &copy; <?php echo date('Y'); ?> St. Thomas Aquinas Parish Church Event and Resource Management System 
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
